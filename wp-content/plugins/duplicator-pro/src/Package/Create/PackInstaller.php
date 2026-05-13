<?php

namespace Duplicator\Package\Create;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Installer\Core\Security;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Installer\Package\DescriptorFileInfo;
use Duplicator\Installer\Package\DescriptorPackageInfo;
use Duplicator\Installer\Package\DescriptorPlugin;
use Duplicator\Installer\Package\DescriptorTheme;
use Duplicator\Installer\Package\DescriptorWpInfo;
use Duplicator\Installer\Package\InstallerDescriptors;
use Duplicator\Libs\DupArchive\DupArchiveEngine;
use Duplicator\Libs\Index\FileIndexManager;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Snap\SnapCode;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapOrigFileManager;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\WpConfig\WPConfigTransformer;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Create\BuildProgress;
use Duplicator\Libs\Scan\ScanIterator;
use Duplicator\Libs\Scan\ScanNodeInfo;
use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Libs\Snap\SnapDB;
use Duplicator\Libs\WpUtils\WpUtilsMultisite;
use Duplicator\Package\AbstractPackage;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Utils\ZipArchiveExtended;
use Exception;
use stdClass;
use Throwable;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use VendorDuplicator\Amk\JsonSerialize\AbstractJsonSerializable;
use ZipArchive;

/**
 * Classes for building the Backup installer extra files
 */
class PackInstaller extends AbstractJsonSerializable
{
    const INSTALLER_SERVER_EXTENSION                      = '.php.bak';
    const DEFAULT_INSTALLER_FILE_NAME_WITHOUT_HASH        = 'installer.php';
    const DEFAULT_INSTALLER_BACKUP_FILE_NAME_WITHOUT_HASH = 'installer-backup.php';
    const CONFIG_ORIG_FILE_FOLDER_PREFIX                  = 'source_site_';
    const CONFIG_ORIG_FILE_USERINI_ID                     = 'userini';
    const CONFIG_ORIG_FILE_HTACCESS_ID                    = 'htaccess';
    const CONFIG_ORIG_FILE_WPCONFIG_ID                    = 'wpconfig';
    const CONFIG_ORIG_FILE_PHPINI_ID                      = 'phpini';
    const CONFIG_ORIG_FILE_WEBCONFIG_ID                   = 'webconfig';

    protected ?string $File;
    /** @var int<0,max> */
    public $Size = 0;
    //SETUP
    /** @var int ENUM ArchiveDescriptor::SECURE_MODE_* */
    public $OptsSecureOn = ArchiveDescriptor::SECURE_MODE_NONE;
    /** @var string */
    public $passowrd = '';
    /** @var string */
    public $OptsSecurePass = ''; // Old installer password managed before 4.5.3,
    /** @var bool */
    public $OptsSkipScan = false;
    //BASIC
    /** @var string */
    public $OptsDBHost = '';
    /** @var string */
    public $OptsDBName = '';
    /** @var string */
    public $OptsDBUser = '';
    //CPANEL
    /** @var string */
    public $OptsCPNLHost = '';
    /** @var string */
    public $OptsCPNLUser = '';
    /** @var string */
    public $OptsCPNLPass = '';
    /** @var bool */
    public $OptsCPNLEnable = false;
    /** @var bool */
    public $OptsCPNLConnect = false;
    //CPANEL DB
    //1 = Create New, 2 = Connect Remove
    /** @var string */
    public $OptsCPNLDBAction = 'create';
    /** @var string */
    public $OptsCPNLDBHost = '';
    /** @var string */
    public $OptsCPNLDBName = '';
    /** @var string */
    public $OptsCPNLDBUser = '';

    /** @var SnapOrigFileManager */
    protected $origFileManger;
    protected \Duplicator\Package\AbstractPackage $Package;
    /** @var int<0,max> */
    public $numFilesAdded = 0;
    /** @var int<0,max> */
    public $numDirsAdded = 0;
    /** @var ?WPConfigTransformer */
    private $configTransformer;

    /**
     * CLass constructor
     *
     * @param AbstractPackage $package Backup
     */
    public function __construct(AbstractPackage $package)
    {
        $this->Package = $package;
        $this->File    = $package->getNameHash() . '_' . self::DEFAULT_INSTALLER_FILE_NAME_WITHOUT_HASH;
        $this->loadInit();
    }

    /**
     * Init after load
     *
     * @return void
     */
    protected function loadInit()
    {
        $this->origFileManger = new SnapOrigFileManager(
            WpArchiveUtils::getArchiveListPaths('home'),
            DUPLICATOR_SSDIR_PATH_TMP,
            $this->getPrimaryInternalHash()
        );

        if (($wpConfigPath = SnapWP::getWPConfigPath()) !== false) {
            $this->configTransformer = new WPConfigTransformer($wpConfigPath);
        }
    }

    /**
     * Get internal hash by installer file name
     *
     * @return string
     */
    protected function getPrimaryInternalHash(): string
    {
        if (($archiveInfo = ArchiveDescriptor::getArchiveNameParts($this->File)) === false) {
            throw new Exception("Can't get archive info from filename: {$this->File}");
        }
        return $archiveInfo['packageHash'];
    }

    /**
     * Return serialize data for json encode
     *
     * @return array<string,mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data = get_object_vars($this);
        foreach (['origFileManger', 'Package', 'configTransformer'] as $removeProp) {
            unset($data[$removeProp]);
        }
        $data['OptsSecurePass'] = ''; // empty old password
        $data['passowrd']       = CryptBlowfish::encryptIfAvaiable($data['passowrd'], null, true);

        return $data;
    }

    /**
     * Called after json decode
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->loadInit();

        if (strlen($this->OptsSecurePass) > 0) {
            $this->passowrd = base64_decode($this->OptsSecurePass);
        } elseif (strlen($this->passowrd) > 0) {
            $this->passowrd = CryptBlowfish::decryptIfAvaiable($this->passowrd, null, true);
        }

        $this->OptsSecurePass = '';
    }

    /**
     * Returns real and normalized path to the saved installer file at default backup location
     *
     * @return string
     */
    public function getSafeFilePath()
    {
        return SnapIO::safePath(DUPLICATOR_SSDIR_PATH . "/" . $this->getInstallerLocalName());
    }

    /**
     * Return local fil name
     *
     * @return string
     */
    public function getInstallerLocalName()
    {
        return pathinfo($this->File, PATHINFO_FILENAME) . self::INSTALLER_SERVER_EXTENSION;
    }

    /**
     * Get the installer file name
     *
     * @return string
     */
    public function getInstallerName()
    {
        return $this->File;
    }

    /**
     * Get the download name based on installer name mode setting
     *
     * @return string
     */
    public function getDownloadName()
    {
        $global = GlobalEntity::getInstance();

        switch ($global->installer_name_mode) {
            case GlobalEntity::INSTALLER_NAME_MODE_SIMPLE:
                return self::DEFAULT_INSTALLER_FILE_NAME_WITHOUT_HASH;
            case GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH:
            default:
                $info = pathinfo($this->getInstallerName());
                return $info['basename'];
        }
    }

    /**
     * Return true if a installer security system is enabled
     *
     * @return bool
     */
    public function isSecure()
    {
        return $this->OptsSecureOn != ArchiveDescriptor::SECURE_MODE_NONE;
    }

    /**
     * Build
     *
     * @param BuildProgress $build_progress Build progress
     *
     * @return void
     */
    public function build(BuildProgress $build_progress): void
    {
        DupLog::trace("building installer");
        $success = false;
        if ($this->createEnhancedInstallerFiles()) {
            $success = $this->addExtraFiles();
        }

        if ($success) {
            $build_progress->installer_built = true;
        } else {
            DupLog::infoTrace("Error in create_enhanced_installer_files, set build failed");
            $build_progress->failed = true;
        }
    }

    /**
     * Create enhanced installer files
     *
     * @return bool
     */
    private function createEnhancedInstallerFiles(): bool
    {
        $success = false;
        if ($this->createEnhancedInstaller()) {
            $success = $this->createArchiveConfigFile();
        } else {
            DupLog::infoTrace("Error in create_enhanced_installer, set build failed");
        }

        return $success;
    }

    /**
     * Create installer.php file
     *
     * @return bool
     */
    private function createEnhancedInstaller(): bool
    {
        $success            = true;
        $archive_filepath   = SnapIO::safePath("{$this->Package->StorePath}/{$this->Package->Archive->getFileName()}");
        $installer_filepath = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . "/" . $this->getInstallerLocalName();
        $template_filepath  = DUPLICATOR____PATH . '/installer/installer.tpl';
        // Replace the @@ARCHIVE@@ token
        $header             = <<<HEADER
<?php
/* ------------------------------ NOTICE ----------------------------------

If you're seeing this text when browsing to the installer, it means your
web server is not set up properly.

Please contact your host and ask them to enable "PHP" processing on your
account.
----------------------------- NOTICE --------------------------------- */
HEADER;
        $installer_contents = $header . SnapCode::getSrcClassCode($template_filepath, false, true) . "\n/* " . DUPLICATOR_INSTALLER_EOF_MARKER . " */";
        // $installer_contents     = file_get_contents($template_filepath);
        // $csrf_class_contents = file_get_contents($csrf_class_filepath);

        $dupExpanderCoder  = '';
        $bootPath          = DUPLICATOR____PATH . '/installer/dup-installer/src/Bootstrap/';
        $dupExpanderCoder .= SnapCode::getSrcClassCode($bootPath . 'BootstrapRunner.php') . "\n";
        $dupExpanderCoder .= SnapCode::getSrcClassCode(DUPLICATOR____PATH . '/src/Libs/Shell/Shell.php') . "\n";
        $dupExpanderCoder .= SnapCode::getSrcClassCode(DUPLICATOR____PATH . '/src/Libs/Shell/ShellOutput.php') . "\n";
        $dupExpanderCoder .= SnapCode::getSrcClassCode($bootPath . 'BootstrapUtils.php') . "\n";
        $dupExpanderCoder .= SnapCode::getSrcClassCode($bootPath . 'BootstrapView.php') . "\n";
        $dupExpanderCoder .= SnapCode::getSrcClassCode($bootPath . 'LogHandler.php') . "\n";
        $dupExpanderCoder .= SnapCode::getSrcClassCode(DUPLICATOR____PATH . '/installer/dup-installer/src/Utils/SecureCsrf.php') . "\n";

        if ($this->Package->build_progress->current_build_mode == PackageArchive::BUILD_MODE_DUP_ARCHIVE) {
            $dupLib            = DUPLICATOR____PATH . '/src/Libs/DupArchive/';
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'DupArchive.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'DupArchiveExpandBasicEngine.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'Headers/AbstractDupArchiveHeader.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'Headers/DupArchiveDirectoryHeader.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'Headers/DupArchiveFileHeader.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'Headers/DupArchiveGlobHeader.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'Headers/DupArchiveHeader.php') . "\n";
            $dupExpanderCoder .= SnapCode::getSrcClassCode($dupLib . 'Info/DupArchiveExpanderInfo.php') . "\n";
        }

        $search_array           = [
            '#@@DUP_INSTALLER_CLASSES_EXPANDER@@#',
            '@@ARCHIVE@@',
            '@@VERSION@@',
            '@@ARCHIVE_SIZE@@',
            '@@PACKAGE_HASH@@',
            '@@SECONDARY_PACKAGE_HASH@@',
        ];
        $package_hash           = $this->Package->getPrimaryInternalHash();
        $secondary_package_hash = $this->Package->getSecondaryInternalHash();
        $replace_array          = [
            $dupExpanderCoder,
            (string) $this->Package->Archive->getFileName(),
            DUPLICATOR_VERSION,
            (string) filesize($archive_filepath),
            $package_hash,
            $secondary_package_hash,
        ];
        $installer_contents     = str_replace($search_array, $replace_array, $installer_contents);
        if (@file_put_contents($installer_filepath, $installer_contents) === false) {
            $details = sprintf(__('Couldn\'t write to %s', 'duplicator-pro'), $installer_filepath);
            DupLog::error(__('Error writing installer contents', 'duplicator-pro'), $details);
            $success = false;
        }

        if ($success) {
            $this->Size = @filesize($installer_filepath);
        }

        return $success;
    }

    /**
     * Create archive.txt file
     *
     * @return bool
     */
    private function createArchiveConfigFile(): bool
    {
        global $wpdb;
        if (is_multisite()) {
            restore_current_blog();
        }

        $global                  = GlobalEntity::getInstance();
        $success                 = true;
        $archive_config_filepath = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . "/{$this->Package->getNameHash()}_archive.txt";
        $ac                      = new ArchiveDescriptor();
        $extension               = strtolower($this->Package->Archive->Format);

        //READ-ONLY: COMPARE VALUES
        $ac->created                 = $this->Package->getCreated();
        $ac->version_dup             = DUPLICATOR_VERSION;
        $ac->version_wp              = $this->Package->VersionWP;
        $ac->version_db              = $this->Package->VersionDB;
        $ac->version_php             = $this->Package->VersionPHP;
        $ac->version_os              = $this->Package->VersionOS;
        $ac->dbInfo                  = $this->Package->Database->info->cloneToArchiveDbInfo();
        $ac->packInfo                = new DescriptorPackageInfo();
        $ac->packInfo->packageId     = $this->Package->getId();
        $ac->packInfo->packageName   = $this->Package->getName();
        $ac->packInfo->packageHash   = $this->Package->getPrimaryInternalHash();
        $ac->packInfo->secondaryHash = $this->Package->getSecondaryInternalHash();
        $ac->fileInfo                = new DescriptorFileInfo();
        $ac->fileInfo->fileCount     = $this->Package->Archive->FileCount;
        $ac->fileInfo->dirCount      = $this->Package->Archive->DirCount;
        $ac->fileInfo->size          = $this->Package->Archive->Size;
        $ac->wpInfo                  = $this->getWpInfo();

        //READ-ONLY: GENERAL
        $ac->installer_backup_name = $this->getInstallerBackupName();
        $ac->package_name          = "{$this->Package->getNameHash()}_archive.{$extension}";
        $ac->package_hash          = $this->Package->getPrimaryInternalHash();
        $ac->package_notes         = $this->Package->notes;
        $ac->opts_delete           = DUPLICATOR_OPTS_DELETE;
        $ac->blogname              = sanitize_text_field(get_option('blogname'));
        $ac->defaultStorageId      = StoragesUtil::getDefaultStorageId();
        $ac->exportOnlyDB          = $this->Package->isDBOnly();
        $ac->components            = $this->Package->components;

        //PRE-FILLED: GENERAL
        $ac->secure_on   = $this->OptsSecureOn;
        $ac->secure_pass = $ac->secure_on ? Security::passwordHash($this->passowrd) : '';

        $ac->mu_mode        = SnapWP::getMode();
        $ac->wp_tableprefix = $wpdb->base_prefix;
        $ac->mu_generation  = SnapWP::getMuGeneration();
        $ac->mu_is_filtered = !empty($this->Package->Multisite->FilterSites);
        $ac->mu_siteadmins  = array_values(get_super_admins());
        $filteredTables     = ($this->Package->Database->FilterOn ? explode(',', $this->Package->Database->FilterTables) : []);
        $ac->subsites       = WpUtilsMultisite::getSubsites(
            $this->Package->Multisite->FilterSites,
            $filteredTables,
            $this->Package->Archive->FilterInfo->Dirs->Instance
        );
        $ac->main_site_id   = get_main_site_id();

        //BRAND
        $ac->brand = $this->brandSetup($this->Package->Brand_ID);

        //LICENSING
        $ac->license_type  = LicenseData::getInstance()->getLicenseType();
        $ac->license_limit = LicenseData::getInstance()->getLicenseLimit();

        // OVERWRITE PARAMS
        $ac->overwriteInstallerParams = apply_filters('duplicator_overwrite_params_data', $this->getPrefillParams());
        $json                         = JsonSerialize::serialize($ac, JSON_PRETTY_PRINT);

        if (file_put_contents($archive_config_filepath, $json) === false) {
            DupLog::error("Error writing archive config", "Couldn't write archive config at $archive_config_filepath");
            $success = false;
        }

        return $success;
    }

    /**
     * Get prefill installer params
     *
     * @return array<string,array{formStatus?:string,value:mixed}>
     */
    private function getPrefillParams(): array
    {
        $result = [];
        if (strlen($this->OptsDBHost) > 0) {
            $result['dbhost'] = ['value' => $this->OptsDBHost];
        }

        if (strlen($this->OptsDBName) > 0) {
            $result['dbname'] = ['value' => $this->OptsDBName];
        }

        if (strlen($this->OptsDBUser) > 0) {
            $result['dbuser'] = ['value' => $this->OptsDBUser];
        }

        if (filter_var($this->OptsCPNLEnable, FILTER_VALIDATE_BOOLEAN)) {
            $result['view_mode'] = ['value' => 'cpnl'];
        }

        if (strlen($this->OptsCPNLDBAction) > 0) {
            $result['cpnl-dbaction'] = ['value' => $this->OptsCPNLDBAction];
        }

        if (strlen($this->OptsCPNLHost) > 0) {
            $result['cpnl-host'] = ['value' => $this->OptsCPNLHost];
        }

        if (strlen($this->OptsCPNLUser) > 0) {
            $result['cpnl-user'] = ['value' => $this->OptsCPNLUser];
        }

        if (strlen($this->OptsCPNLPass) > 0) {
            $result['cpnl-pass'] = ['value' => $this->OptsCPNLPass];
        }

        if (strlen($this->OptsCPNLDBHost) > 0) {
            $result['cpnl-dbhost'] = ['value' => $this->OptsCPNLDBHost];
        }

        if (strlen($this->OptsCPNLDBName) > 0) {
            $result['cpnl-dbname-txt'] = ['value' => $this->OptsCPNLDBName];
        }

        if (strlen($this->OptsCPNLDBUser) > 0) {
            $result['cpnl-dbuser-txt'] = ['value' => $this->OptsCPNLDBUser];
        }

        return $result;
    }

    /**
     * Return list of extra files to att to archive
     *
     * @param bool $checkExists Check if file exists
     *
     * @return array<array{sourcePath:string,archivePath:string,label:string}>
     */
    private function getExtraFilesLists($checkExists = true): array
    {
        $dscMng = $this->Package->getDescriptorMng();
        $result = [];

        $result[] = [
            'sourcePath'  => SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . "/" . $this->getInstallerLocalName(),
            'archivePath' => $this->getInstallerBackupName(),
            'label'       => 'installer backup file',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/installer/dup-installer',
            'archivePath' => 'dup-installer',
            'label'       => 'dup installer folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Snap',
            'archivePath' => 'dup-installer/libs/Snap',
            'label'       => 'dup snaplib folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Shell',
            'archivePath' => 'dup-installer/libs/Shell',
            'label'       => 'dup shell folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Chunking',
            'archivePath' => 'dup-installer/libs/Chunking',
            'label'       => 'dup snaplib folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/DupArchive',
            'archivePath' => 'dup-installer/libs/DupArchive',
            'label'       => 'dup snaplib folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Binary',
            'archivePath' => 'dup-installer/libs/Binary',
            'label'       => 'dup snaplib folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Index',
            'archivePath' => 'dup-installer/libs/Index',
            'label'       => 'dup snaplib folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Scan',
            'archivePath' => 'dup-installer/libs/Scan',
            'label'       => 'dup snaplib folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/WpConfig',
            'archivePath' => 'dup-installer/libs/WpConfig',
            'label'       => 'lib config folder',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/src/Libs/Certificates',
            'archivePath' => 'dup-installer/libs/Certificates',
            'label'       => 'SSL certificates',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/vendor-prefixed/andreamk/jsonserialize',
            'archivePath' => 'dup-installer/vendor-prefixed/andreamk/jsonserialize',
            'label'       => 'Requests library',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/vendor-prefixed/rmccue/requests',
            'archivePath' => 'dup-installer/vendor-prefixed/rmccue/requests',
            'label'       => 'Requests library',
        ];

        $result[] = [
            'sourcePath'  => DUPLICATOR____PATH . '/assets/css/font-awesome',
            'archivePath' => 'dup-installer/assets/font-awesome',
            'label'       => 'font awesome',
        ];

        $result[] = [
            'sourcePath'  => $this->origFileManger->getMainFolder(),
            'archivePath' => 'dup-installer/' . $dscMng->getName(InstallerDescriptors::TYPE_ORIG_FILES),
            'label'       => 'original files folder',
        ];

        $result[] = [
            'sourcePath'  => SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . "/{$this->Package->getNameHash()}_archive.txt",
            'archivePath' => 'dup-installer/' . $dscMng->getName(InstallerDescriptors::TYPE_ARCHIVE_CONFIG),
            'label'       => 'archive descriptor file',
        ];

        $result[] = [
            'sourcePath'  => SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . "/{$this->Package->getNameHash()}_scan.json",
            'archivePath' => 'dup-installer/' . $dscMng->getName(InstallerDescriptors::TYPE_SCAN),
            'label'       => 'scan file',
        ];

        $result[] = [
            'sourcePath'  => SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . '/' . $this->Package->getIndexFileName(),
            'archivePath' => 'dup-installer/' . $dscMng->getName(InstallerDescriptors::TYPE_INDEX),
            'label'       => 'index file',
        ];

        $result[] = [
            'sourcePath'  => $this->getManualExtractFilePath(),
            'archivePath' => 'dup-installer/' . $dscMng->getName(InstallerDescriptors::TYPE_MANUAL_EXTRACT),
            'label'       => 'manual extract file',
        ];

        foreach (\Duplicator\Core\Addons\AddonsManager::getInstance()->getEnabledAddons() as $addon) {
            if (!is_readable($addon->getAddonInstallerPath())) {
                continue;
            }

            $result[] = [
                'sourcePath'  => $addon->getAddonInstallerPath(),
                'archivePath' => 'dup-installer/addons/' . basename($addon->getAddonInstallerPath()),
                'label'       => 'addon ' . $addon->getSlug(),
            ];
        }

        $result[] = [
            'sourcePath'  => $this->Package->Database->getStorePath(),
            'archivePath' => 'dup-installer/' . $dscMng->getName(InstallerDescriptors::TYPE_DB_DUMP),
            'label'       => 'Sql dump file',
        ];

        if ($checkExists) {
            foreach ($result as $item) {
                if (!is_readable($item['sourcePath'])) {
                    throw new Exception('INSTALLER FILES: "' . $item['label'] . '" doesn\'t exist ' . $item['sourcePath']);
                }
            }
        }

        return $result;
    }

    /**
     * Get wpInfo object
     *
     * @return DescriptorWpInfo
     */
    private function getWpInfo(): DescriptorWpInfo
    {
        $wpInfo               = new DescriptorWpInfo();
        $wpInfo->version      = $this->Package->VersionWP;
        $wpInfo->is_multisite = is_multisite();
        $wpInfo->network_id   = function_exists('get_current_network_id') ? get_current_network_id() : 1;

        $wpInfo->targetRoot  = WpArchiveUtils::getTargetRootPath();
        $wpInfo->targetPaths = PackageArchive::getScanPaths();
        $wpInfo->adminUsers  = SnapWP::getAdminUserLists();

        $pluginFiltes = (
            in_array(BuildComponents::COMP_PLUGINS_ACTIVE, $this->Package->components) ?
            SnapWP::PLUGIN_INFO_ACTIVE :
            SnapWP::PLUGIN_INFO_ALL
        );
        if (!in_array(BuildComponents::COMP_PLUGINS, $this->Package->components)) {
            $pathFilters = true;
        } else {
            $pathFilters = $this->Package->Archive->FilterDirsAll;
        }
        $pluginsData = SnapWP::getPluginsInfo($pluginFiltes, $pathFilters);
        foreach ($pluginsData as $pluginData) {
            $wpInfo->plugins[$pluginData['slug']] = new DescriptorPlugin($pluginData);
        }
        $themesData = SnapWP::getThemesInfo();
        foreach ($themesData as $themeData) {
            $wpInfo->themes[$themeData['slug']] = new DescriptorTheme($themeData);
        }

        $this->addDefineIfExists($wpInfo->configs->defines, 'ABSPATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DB_CHARSET');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DB_COLLATE');
        $this->addDefineIfExists(
            $wpInfo->configs->defines,
            'MYSQL_CLIENT_FLAGS',
            [
                SnapDB::class,
                'getMysqlConnectFlagsFromMaskVal',
            ]
        );
        $this->addDefineIfExists($wpInfo->configs->defines, 'AUTH_KEY');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SECURE_AUTH_KEY');
        $this->addDefineIfExists($wpInfo->configs->defines, 'LOGGED_IN_KEY');
        $this->addDefineIfExists($wpInfo->configs->defines, 'NONCE_KEY');
        $this->addDefineIfExists($wpInfo->configs->defines, 'AUTH_SALT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SECURE_AUTH_SALT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'LOGGED_IN_SALT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'NONCE_SALT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_SITEURL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_HOME');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_CONTENT_DIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_CONTENT_URL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_PLUGIN_DIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_PLUGIN_URL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'PLUGINDIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'UPLOADS');
        $this->addDefineIfExists($wpInfo->configs->defines, 'AUTOSAVE_INTERVAL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_POST_REVISIONS');
        $this->addDefineIfExists($wpInfo->configs->defines, 'COOKIE_DOMAIN');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_ALLOW_MULTISITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'ALLOW_MULTISITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'MULTISITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DOMAIN_CURRENT_SITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'PATH_CURRENT_SITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SITE_ID_CURRENT_SITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'BLOG_ID_CURRENT_SITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SUBDOMAIN_INSTALL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'VHOST');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SUNRISE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'NOBLOGREDIRECT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_DEBUG');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SCRIPT_DEBUG');
        $this->addDefineIfExists($wpInfo->configs->defines, 'CONCATENATE_SCRIPTS');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_DEBUG_LOG');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_DEBUG_DISPLAY');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_MEMORY_LIMIT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_MAX_MEMORY_LIMIT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_CACHE');

        // wp super cache define
        $this->addDefineIfExists($wpInfo->configs->defines, 'WPCACHEHOME');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_TEMP_DIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'CUSTOM_USER_TABLE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'CUSTOM_USER_META_TABLE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WPLANG');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_LANG_DIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SAVEQUERIES');
        $this->addDefineIfExists($wpInfo->configs->defines, 'FS_CHMOD_DIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'FS_CHMOD_FILE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'FS_METHOD');
        /**
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_BASE');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_CONTENT_DIR');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_PLUGIN_DIR');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_PUBKEY');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_PRIKEY');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_USER');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_PASS');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_HOST');
          $this->addDefineIfExists($wpInfo->configs->defines, 'FTP_SSL');
         * */
        $this->addDefineIfExists($wpInfo->configs->defines, 'ALTERNATE_WP_CRON');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DISABLE_WP_CRON');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_CRON_LOCK_TIMEOUT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'COOKIEPATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'SITECOOKIEPATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'ADMIN_COOKIE_PATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'PLUGINS_COOKIE_PATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'TEMPLATEPATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'STYLESHEETPATH');
        $this->addDefineIfExists($wpInfo->configs->defines, 'EMPTY_TRASH_DAYS');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_ALLOW_REPAIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DO_NOT_UPGRADE_GLOBAL_TABLES');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DISALLOW_FILE_EDIT');
        $this->addDefineIfExists($wpInfo->configs->defines, 'DISALLOW_FILE_MODS');
        $this->addDefineIfExists($wpInfo->configs->defines, 'FORCE_SSL_ADMIN');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_HTTP_BLOCK_EXTERNAL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_ACCESSIBLE_HOSTS');
        $this->addDefineIfExists($wpInfo->configs->defines, 'AUTOMATIC_UPDATER_DISABLED');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WP_AUTO_UPDATE_CORE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'IMAGE_EDIT_OVERWRITE');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WPMU_PLUGIN_DIR');
        $this->addDefineIfExists($wpInfo->configs->defines, 'WPMU_PLUGIN_URL');
        $this->addDefineIfExists($wpInfo->configs->defines, 'MUPLUGINDIR');

        $originalUrls                            = WpArchiveUtils::getOriginalUrls();
        $wpInfo->configs->realValues->siteUrl    = $originalUrls['abs'];
        $wpInfo->configs->realValues->homeUrl    = $originalUrls['home'];
        $wpInfo->configs->realValues->loginUrl   = $originalUrls['login'];
        $wpInfo->configs->realValues->contentUrl = $originalUrls['wpcontent'];
        $wpInfo->configs->realValues->uploadBaseUrl = $originalUrls['uploads'];
        $wpInfo->configs->realValues->pluginsUrl    = $originalUrls['plugins'];
        $wpInfo->configs->realValues->mupluginsUrl  = $originalUrls['muplugins'];
        $wpInfo->configs->realValues->themesUrl     = $originalUrls['themes'];
        $wpInfo->configs->realValues->originalPaths = [];
        $originalpaths                              = WpArchiveUtils::getOriginalPaths();
        foreach ($originalpaths as $key => $val) {
            $originalpaths[$key] = untrailingslashit($val);
        }
        $wpInfo->configs->realValues->originalPaths = (object) $originalpaths;
        $wpInfo->configs->realValues->archivePaths  = (object) array_merge(
            $originalpaths,
            WpArchiveUtils::getArchiveListPaths()
        );
        return $wpInfo;
    }

    /**
     * Check if $define is defined and add a prop to $obj
     *
     * @param object        $obj               object to add prop
     * @param string        $define            constant name
     * @param null|callable $transformCallback if it is different from null the function is applied to the value
     *
     * @return boolean return true if define is added of false
     */
    private function addDefineIfExists($obj, string $define, $transformCallback = null): bool
    {
        if (!defined($define)) {
            return false;
        }

        $obj->{$define} = new stdClass();

        if (is_callable($transformCallback)) {
            $obj->{$define}->value = call_user_func($transformCallback, constant($define));
        } else {
            if ($transformCallback !== null) {
                throw new Exception('transformCallback isn\'t callable');
            }
            $obj->{$define}->value = constant($define);
        }

        if (!is_null($this->configTransformer)) {
            $obj->{$define}->inWpConfig = $this->configTransformer->exists('constant', $define);
        } else {
            $obj->{$define}->inWpConfig = false;
        }

        return true;
    }

    /**
     * Brand setup
     *
     * @param int $id brand id
     *
     * @return array{isDefault:bool,name:string,logo:string,enabled:bool,style:array<string,mixed>}
     */
    private function brandSetup(int $id): array
    {
        // initialize brand
        $brand = BrandEntity::getByIdOrDefault((int) $id);

        // Prepare default fields
        $brand_property_default = [
            'name'      => 'Duplicator Professional',
            'isDefault' => true,
            'logo'      => '',
            'enabled'   => false,
            'style'     => [],
        ];

        // Returns property
        $brand_property = [];

        // Is default brand selected?
        $brand_property['isDefault'] = $brand->isDefault();

        // Set brand name
        $brand_property['name'] = $brand_property['isDefault'] ? 'Duplicator Professional' : $brand->name;

        // Set logo and hosted images path
        $brand_property['logo'] = $brand->logo;

        $arr_img = [];
        // Find images
        if (preg_match_all('/<img.*?src="([^"]+)".*?>/', $brand->logo, $arr_img, PREG_PATTERN_ORDER) >= 1) {
            // https://regex101.com/r/eEyf5S/2
            // Fix hosted image url path
            if (count($brand->attachments) > 0 && count($arr_img[1]) === count($brand->attachments)) {
                foreach ($arr_img[1] as $i => $find) {
                    $brand_property['logo'] = str_replace($find, 'assets/images/brand' . $brand->attachments[$i], $brand_property['logo']);
                }
            }
        }
        $brand_property['logo'] = stripslashes($brand_property['logo']);

        // Set is enabled
        if (!empty($brand_property['logo']) && $brand->active) {
            $brand_property['enabled'] = true;
        }
        $brand_property['style'] = $brand->style;

        // Merge data properly
        $brand_property = array_replace($brand_property_default, $brand_property);
        return $brand_property;
    }

    /**
     * Get archive full path
     *
     * @return string
     */
    public function getArchiveFullPath()
    {
        return SnapIO::safePath($this->Package->StorePath) . '/' . $this->Package->Archive->getFileName();
    }

    /**
     *  CreateZipBackup
     *  Puts an installer zip file in the archive for backup purposes.
     *
     * @return bool
     */
    private function addExtraFiles(): bool
    {
        $success          = false;
        $archive_filepath = SnapIO::safePath("{$this->Package->StorePath}/{$this->Package->Archive->getFileName()}");

        $this->initConfigFiles();
        $this->createManualExtractCheckFile();
        $this->addExtraFilesToIndex();

        DupLog::trace("Add extra files: Current build mode = " . $this->Package->build_progress->current_build_mode);
        if ($this->Package->build_progress->current_build_mode == PackageArchive::BUILD_MODE_ZIP_ARCHIVE) {
            $success = $this->zipArchiveAddExtra();
        } elseif ($this->Package->build_progress->current_build_mode == PackageArchive::BUILD_MODE_SHELL_EXEC) {
            // Adding the shellexec fail text fix
            if (($success = $this->shellZipAddExtra()) == false) {
                $error_text    = __("Problem adding installer to archive", 'duplicator-pro');
                $fix_text      = __("Click on button to set archive engine to DupArchive.", 'duplicator-pro');
                $system_global = SystemGlobalEntity::getInstance();
                $system_global->addQuickFix(
                    $error_text,
                    $fix_text,
                    [
                        'global' => ['archive_build_mode' => 3],
                    ]
                );
            }
        } elseif ($this->Package->build_progress->current_build_mode == PackageArchive::BUILD_MODE_DUP_ARCHIVE) {
            $success = $this->dupArchiveAddExtra();
        }

        try {
            $archive_config_filepath = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . "/{$this->Package->getNameHash()}_archive.txt";
            // No sense keeping these files
            @unlink($archive_config_filepath);
            $this->origFileManger->deleteMainFolder();
            $this->deleteManualExtractCheckFile();
        } catch (Exception $e) {
            DupLog::infoTrace("Error clean temp installer file, but continue. Message: " . $e->getMessage());
        }

        $this->Package->Archive->Size = @filesize($archive_filepath);
        return $success;
    }

    /**
     * Check if index need include installer files
     *
     * @return bool
     */
    protected static function isIndexIncludeInstallerFiles(): bool
    {
        return DUPLICATOR_INDEX_INCLUDE_INSTALLER_FILES;
    }

    /**
     * Add extra files to index
     *
     * @return void
     */
    public function addExtraFilesToIndex(): void
    {
        if (self::isIndexIncludeInstallerFiles() === false) {
            return;
        }

        $indexManager = $this->Package->Archive->getIndexManager();
        $extraFiles   = $this->getExtraFilesLists();
        usort($extraFiles, fn($a, $b): int => strcmp($a['archivePath'], $b['archivePath']));

        foreach ($extraFiles as $extraItem) {
            $sourcePath  = $extraItem['sourcePath'];
            $archivePath = $extraItem['archivePath'];

            FileIndexManager::setRootPathMap($sourcePath, $archivePath);
            if (!is_dir($sourcePath)) {
                $indexManager->add(FileIndexManager::LIST_TYPE_INSTALLER, new ScanNodeInfo($sourcePath));
                continue;
            }

            $archivePath = SnapIO::trailingslashit($archivePath);
            $iterator    = new ScanIterator($sourcePath, [], ScanIterator::SORT_ASC);
            foreach ($iterator as $node) {
                if ($node->isDir()) {
                    continue;
                }

                $indexManager->add(FileIndexManager::LIST_TYPE_INSTALLER, $node);
            }
        }

        $indexManager->save();
    }

    /**
     * Get installer backup name
     *
     * @return string
     */
    public function getInstallerBackupName()
    {
        return $this->Package->getNameHash() . '_' . self::DEFAULT_INSTALLER_BACKUP_FILE_NAME_WITHOUT_HASH;
    }

    /**
     * Add extra files in duparchive
     *
     * @return bool
     */
    private function dupArchiveAddExtra(): bool
    {
        $logger = new \Duplicator\Package\Create\DupArchive\Logger();
        DupArchiveEngine::init($logger, null);

        $archivePath   = $this->getArchiveFullPath();
        $extraPoistion = filesize($archivePath);

        $password = $this->Package->Archive->getArchivePassword();

        foreach ($this->getExtraFilesLists() as $extraItem) {
            if (is_dir($extraItem['sourcePath'])) {
                $result               = DupArchiveEngine::addDirectoryToArchiveST(
                    $archivePath,
                    $extraItem['sourcePath'],
                    $extraItem['archivePath'],
                    $password,
                    true
                );
                $this->numFilesAdded += $result->numFilesAdded;
                $this->numDirsAdded  += $result->numDirsAdded;
            } else {
                DupArchiveEngine::addRelativeFileToArchiveST(
                    $archivePath,
                    $extraItem['sourcePath'],
                    $extraItem['archivePath'],
                    $password
                );
                $this->numFilesAdded++;
            }
        }

        // store extra files position
        $src  = json_encode([DupArchiveEngine::EXTRA_FILES_POS_KEY => $extraPoistion]);
        $src .= str_repeat("\0", DupArchiveEngine::INDEX_FILE_SIZE - strlen($src));
        DupArchiveEngine::replaceFileContent(
            $archivePath,
            $src,
            DupArchiveEngine::INDEX_FILE_NAME,
            $password,
            0,
            3000
        );

        return true;
    }

    /**
     * Add extra files in zip archive
     *
     * @return bool
     */
    private function zipArchiveAddExtra(): bool
    {
        $zipArchive = new ZipArchiveExtended($this->getArchiveFullPath());
        $zipArchive->setCompressed($this->Package->build_progress->current_build_compression);
        if ($this->Package->Archive->isArchiveEncrypt()) {
            $zipArchive->setEncrypt(true, $this->Package->Archive->getArchivePassword());
        }

        if ($zipArchive->open() !== true) {
            throw new \Exception("Couldn't open zip archive ");
        }

        DupLog::trace("Successfully opened zip");

        foreach ($this->getExtraFilesLists() as $extraItem) {
            if (is_dir($extraItem['sourcePath'])) {
                $zipArchive->addDir($extraItem['sourcePath'], $extraItem['archivePath']);
            } else {
                $zipArchive->addFile($extraItem['sourcePath'], $extraItem['archivePath']);
            }
        }

        if ($zipArchive->close() === false) {
            throw new \Exception("Couldn't close zip archive ");
        }

        DupLog::trace('After ziparchive close when adding installer');

        $this->zipArchiveCheck();
        return true;
    }

    /**
     * Check zip archive
     *
     * @return void
     */
    private function zipArchiveCheck(): void
    {
        /* ------ ZIP CONSISTENCY CHECK ------ */
        DupLog::trace("Running ZipArchive consistency check");
        $zip = new ZipArchive();

        // ZipArchive::CHECKCONS will enforce additional consistency checks
        $res = $zip->open($this->getArchiveFullPath(), ZipArchive::CHECKCONS);
        if ($res !== true) {
            $consistency_error = sprintf(__('ERROR: Cannot open created archive. Error code = %1$s', 'duplicator-pro'), $res);
            DupLog::trace($consistency_error);
            switch ($res) {
                case ZipArchive::ER_NOZIP:
                    $consistency_error = __('ERROR: Archive is not valid zip archive.', 'duplicator-pro');
                    break;
                case ZipArchive::ER_INCONS:
                    $consistency_error = __("ERROR: Archive doesn't pass consistency check.", 'duplicator-pro');
                    break;
                case ZipArchive::ER_CRC:
                    $consistency_error = __("ERROR: Archive checksum is bad.", 'duplicator-pro');
                    break;
            }

            throw new \Exception($consistency_error);
        }

        $failed = false;
        foreach ($this->getInstallerPathsForIntegrityCheck() as $path) {
            if ($zip->locateName($path) === false) {
                $failed = true;
                $msg    = sprintf(__('Couldn\'t find %1$s in archive', 'duplicator-pro'), $path);
                DupLog::infoTrace($msg);
            }
        }

        if ($failed) {
            DupLog::info(__('ARCHIVE CONSISTENCY TEST: FAIL', 'duplicator-pro'));
            throw new \Exception("Zip for Backup " . $this->Package->getId() . " didn't passed consistency test");
        } else {
            DupLog::info(__('ARCHIVE CONSISTENCY TEST: PASS', 'duplicator-pro'));
            DupLog::trace("Zip for Backup " . $this->Package->getId() . " passed consistency test");
        }

        $zip->close();
    }

    /**
     * Add extra files in shell zip
     *
     * @return bool
     */
    private function shellZipAddExtra(): bool
    {
        $tmpExtraFolder = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP) . '/extras/';

        if (file_exists($tmpExtraFolder)) {
            if (SnapIO::rrmdir($tmpExtraFolder) === false) {
                throw new \Exception("Error deleting $tmpExtraFolder");
            }
        }
        if (!wp_mkdir_p($tmpExtraFolder)) {
            throw new \Exception("Error creating extras directory");
        }
        SnapIO::createSilenceIndex($tmpExtraFolder);

        foreach ($this->getExtraFilesLists() as $extraItem) {
            $destPath = $tmpExtraFolder . $extraItem['archivePath'];

            if (!wp_mkdir_p(dirname($destPath))) {
                throw new \Exception("Error creating extras directory, Couldn't create " . dirname($destPath));
            }

            if (!SnapIO::rcopy($extraItem['sourcePath'], $destPath)) {
                throw new \Exception("Error copy " . $extraItem['sourcePath'] . ' to ' . $destPath);
            }
        }

        //-- STAGE 1 ADD
        $params = Shell::getCompressionParam($this->Package->build_progress->current_build_compression);
        if (strlen($this->Package->Archive->getArchivePassword()) > 0) {
            $params .= ' --password ' . escapeshellarg($this->Package->Archive->getArchivePassword());
        }
        $params       .= ' -g -rq';
        $paramsPostfix = ' -x "index.php"';

        $command  = 'cd ' . escapeshellarg(SnapIO::safePath($tmpExtraFolder));
        $command .= ' && ' . escapeshellcmd(ShellZipUtils::getShellExecZipPath()) . ' ' . $params . ' ';
        $command .= escapeshellarg($this->getArchiveFullPath()) . ' .[!.]* *' . $paramsPostfix;
        DupLog::infoTrace('EXECUTING SHELL COMMAND');

        DupLog::infoTrace("SHELL COMMAND: $command");
        $shellOutput = Shell::runCommandBuffered($command);
        if ($shellOutput->getCode() != 0 && !$shellOutput->isEmpty()) {
            throw new \Exception("Error excecuting shell command: " . $command . ' MSG: ' . $shellOutput->getOutputAsString());
        }

        $this->shellZipFilesCheck();

        if (!SnapIO::rrmdir($tmpExtraFolder)) {
            DupLog::trace("Couldn't recursively delete {$tmpExtraFolder}");
        }
        return true;
    }

    /**
     * Check if extra files are in the archive
     *
     * @return bool
     */
    private function shellZipFilesCheck(): bool
    {
        if (Shell::getExeFilepath('unzip') == null) {
            DupLog::trace("unzip doesn't exist so not doing the extra file check");
            return false;
        }
        $filesToValidate = $this->getInstallerPathsForIntegrityCheck();
        DupLog::infoTrace('CHECK FILES ' . \Duplicator\Libs\Snap\SnapLog::v2str($filesToValidate));

        $params = '-Z1';

        // Verify the essential extras got in there
        $extraCountString = "unzip " . $params . ' ' .
            escapeshellarg($this->getArchiveFullPath()) . " | grep '^\(" . implode("\|", $filesToValidate) . "\)' | wc -l";
        DupLog::info("Executing extra count string $extraCountString");

        $shellOutput = Shell::runCommandBuffered($extraCountString . ' | awk \'{print $1 }\'');
        $extraCount  = ($shellOutput->getCode() >= 0)
            ? trim($shellOutput->getOutputAsString())
            : null;

        if (is_numeric($extraCount)) {
            // Accounting for the sql and installer back files
            if ($extraCount != count($filesToValidate)) {
                throw new \Exception("Tried to verify core extra files but one or more were missing. Count = $extraCount");
            }
        } else {
            throw new \Exception("Error retrieving extra count in shell zip " . $extraCount);
        }

        DupLog::trace("Core extra files confirmed to be in the archive");
        return true;
    }

    /**
     * Creates the original_files_ folder in the tmp directory where all config files are saved
     * to be later added to the archives
     *
     * @return void
     */
    public function initConfigFiles(): void
    {
        $this->origFileManger->init();
        $configFilePaths = $this->getConfigFilePaths();
        foreach ($configFilePaths as $identifier => $path) {
            if ($path !== false) {
                try {
                    $this->origFileManger->addEntry($identifier, $path, SnapOrigFileManager::MODE_COPY, self::CONFIG_ORIG_FILE_FOLDER_PREFIX . $identifier);
                } catch (Exception $ex) {
                    DupLog::infoTrace("Error while handling config files: " . $ex->getMessage());
                }
            }
        }

        //Clean sensitive information from wp-config.php file.
        self::cleanTempWPConfArkFilePath($this->origFileManger->getEntryStoredPath(self::CONFIG_ORIG_FILE_WPCONFIG_ID));
    }

    /**
     * Gets config files path
     *
     * @return string[] array of config files in identifier => path format
     */
    public function getConfigFilePaths()
    {
        $home        = WpArchiveUtils::getArchiveListPaths('home');
        $configFiles = [
            self::CONFIG_ORIG_FILE_USERINI_ID   => $home . '/.user.ini',
            self::CONFIG_ORIG_FILE_PHPINI_ID    => $home . '/php.ini',
            self::CONFIG_ORIG_FILE_WEBCONFIG_ID => $home . '/web.config',
            self::CONFIG_ORIG_FILE_HTACCESS_ID  => $home . '/.htaccess',
            self::CONFIG_ORIG_FILE_WPCONFIG_ID  => SnapWP::getWPConfigPath(),
        ];
        foreach ($configFiles as $identifier => $path) {
            if (!file_exists($path)) {
                unset($configFiles[$identifier]);
            }
        }

        return $configFiles;
    }

    /**
     * Get path list for integrity check
     *
     * @return string[]
     */
    public function getInstallerPathsForIntegrityCheck()
    {
        $filesToValidate = [
            'dup-installer/api/class.api.php',
            'dup-installer/assets/index.php',
            'dup-installer/classes/index.php',
            'dup-installer/ctrls/index.php',
            'dup-installer/src/Utils/Autoloader.php',
            'dup-installer/templates/default/page-help.php',
            'dup-installer/main.installer.php',
        ];

        foreach ($this->getExtraFilesLists() as $extraItem) {
            if (is_file($extraItem['sourcePath'])) {
                $filesToValidate[] = $extraItem['archivePath'];
            } else {
                if (file_exists(trailingslashit($extraItem['sourcePath']) . 'index.php')) {
                    $filesToValidate[] = ltrim(trailingslashit($extraItem['archivePath']), '\\/') . 'index.php';
                } else {
                    // SKIP CHECK
                }
            }
        }

        return array_unique($filesToValidate);
    }

    /**
     * Create manual extract check file
     *
     * @return bool
     */
    private function createManualExtractCheckFile(): bool
    {
        $file_path = $this->getManualExtractFilePath();
        return SnapIO::filePutContents($file_path, '');
    }

    /**
     * Get manual extract check file path
     *
     * @return string
     */
    private function getManualExtractFilePath(): string
    {
        $tmp = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP);
        return $tmp . '/dup-manual-extract__' . $this->Package->getPrimaryInternalHash();
    }

    /**
     * Delete manual extract check file
     *
     * @return void
     */
    private function deleteManualExtractCheckFile(): void
    {
        SnapIO::rm($this->getManualExtractFilePath());
    }

    /**
     * Clear out sensitive database connection information
     *
     * @param string $temp_conf_ark_file_path Temp config file path
     *
     * @return void
     */
    private static function cleanTempWPConfArkFilePath($temp_conf_ark_file_path): void
    {
        try {
            if (function_exists('token_get_all')) {
                $transformer = new WPConfigTransformer($temp_conf_ark_file_path);
                $constants   = [
                    'DB_NAME',
                    'DB_USER',
                    'DB_PASSWORD',
                    'DB_HOST',
                ];
                foreach ($constants as $constant) {
                    if ($transformer->exists('constant', $constant)) {
                        $transformer->update('constant', $constant, '');
                    }
                }
            }
        } catch (Throwable $e) {
            DupLog::infoTrace("Can\'t inizialize wp-config transformer Message: " . $e->getMessage());
        }
    }
}
