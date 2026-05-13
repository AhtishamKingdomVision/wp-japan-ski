<?php

/**
 * Staging package - extends PackageImporter with staging-specific parameters
 */

declare(strict_types=1);

namespace Duplicator\Addons\StagingAddon\Package;

use Duplicator\Addons\StagingAddon\Controllers\StagingPageController;
use Duplicator\Addons\StagingAddon\Models\StagingEntity;
use Duplicator\Addons\StagingAddon\StagingAddon;
use Duplicator\Installer\Bootstrap\BootstrapRunner;
use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Package\Import\PackageImporter;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\AbstractPackageDeployer;
use Exception;

/**
 * Extends PackageImporter with staging-specific parameters
 */
class StagingPackage extends PackageImporter
{
    /** @var StagingEntity */
    protected StagingEntity $stagingEntity;

    /**
     * Constructor
     *
     * @param string          $archivePath   Path to the archive file
     * @param StagingEntity   $stagingEntity Staging entity
     * @param AbstractPackage $package       Source backup; supplies the archive password for SECURE_MODE_ARC_ENCRYPT
     */
    public function __construct(string $archivePath, StagingEntity $stagingEntity, AbstractPackage $package)
    {
        $this->stagingEntity = $stagingEntity;
        $this->archivePwd    = $package->Archive->getArchivePassword();
        parent::__construct($archivePath);
    }

    /**
     * Get the staging entity
     *
     * @return StagingEntity
     */
    public function getStagingEntity(): StagingEntity
    {
        return $this->stagingEntity;
    }

    /**
     * Locks parameter value so installer cannot change it
     *
     * @param mixed $value Parameter value
     *
     * @return array{value: mixed, status: string, formStatus: string}
     */
    private function owrParam(mixed $value): array
    {
        return [
            'value'      => $value,
            'status'     => 'owr',
            'formStatus' => 'st_infoonly',
        ];
    }

    /**
     * Return overwrite params for staging
     *
     * @return array<string, array{value: mixed, formStatus?: string}>
     */
    public function getOverwriteParams(): array
    {
        $params = parent::getOverwriteParams();

        $stagingPath = $this->stagingEntity->getPath();
        $stagingUrl  = $this->stagingEntity->getUrl();
        $dbPrefix    = $this->stagingEntity->getDbPrefix();

        // Staging-specific parameters - use 'base' template for full installation
        // Use 'status' => 'owr' (STATUS_OVERWRITE) to prevent installer from changing these values
        $stagingParams = [
            PrmMng::PARAM_TEMPLATE           => ['value' => 'base'],
            PrmMng::PARAM_INST_TYPE          => $this->owrParam(InstState::TYPE_SINGLE),
            PrmMng::PARAM_DB_ACTION          => $this->owrParam('removetables'),
            PrmMng::PARAM_URL_NEW            => $this->owrParam($stagingUrl),
            PrmMng::PARAM_PATH_NEW           => $this->owrParam($stagingPath),
            PrmMng::PARAM_DB_TABLE_PREFIX    => $this->owrParam($dbPrefix),
            PrmMng::PARAM_SITE_URL           => $this->owrParam($stagingUrl),
            PrmMng::PARAM_PATH_WP_CORE_NEW   => $this->owrParam($stagingPath),
            PrmMng::PARAM_PATH_CONTENT_NEW   => $this->owrParam($stagingPath . '/wp-content'),
            PrmMng::PARAM_PATH_PLUGINS_NEW   => $this->owrParam($stagingPath . '/wp-content/plugins'),
            PrmMng::PARAM_PATH_MUPLUGINS_NEW => $this->owrParam($stagingPath . '/wp-content/mu-plugins'),
            PrmMng::PARAM_PATH_UPLOADS_NEW   => $this->owrParam($stagingPath . '/wp-content/uploads'),
            PrmMng::PARAM_URL_CONTENT_NEW    => $this->owrParam($stagingUrl . '/wp-content'),
            PrmMng::PARAM_URL_PLUGINS_NEW    => $this->owrParam($stagingUrl . '/wp-content/plugins'),
            PrmMng::PARAM_URL_MUPLUGINS_NEW  => $this->owrParam($stagingUrl . '/wp-content/mu-plugins'),
            PrmMng::PARAM_URL_UPLOADS_NEW    => $this->owrParam($stagingUrl . '/wp-content/uploads'),
        ];

        // Merge staging params into parent params
        $result = array_merge($params, $stagingParams);

        // Add staging-specific data to PARAM_OVERWRITE_SITE_DATA
        if (!isset($result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value'])) {
            $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value'] = [];
        }

        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['stagingMode']       = true;
        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['mainSiteUrl']       = home_url();
        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['stagingPageUrl']    = StagingPageController::getStagingPageLink();
        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['stagingIdentifier'] = $this->stagingEntity->getIdentifier();
        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['table_prefix']      = $dbPrefix;
        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['colorScheme']       = $this->stagingEntity->getColorScheme();
        $result[PrmMng::PARAM_OVERWRITE_SITE_DATA]['value']['stagingTitle']      = $this->stagingEntity->getTitle();

        return $result;
    }

    /**
     * Get path mode - force to staging custom mode
     *
     * @return string
     */
    protected function getPathMode(): string
    {
        return self::PATH_MODE_CUSTOM;
    }

    /**
     * Creates staging folder if needed
     *
     * @return string|false
     */
    public function getInstallerFolderPath()
    {
        $stagingPath = $this->stagingEntity->getPath();

        if (!is_dir($stagingPath) && !wp_mkdir_p($stagingPath)) {
            return false;
        }

        return $stagingPath;
    }

    /**
     * Return installer folder URL for staging
     *
     * @return string|false
     */
    public function getInstallerFolderUrl()
    {
        return $this->stagingEntity->getUrl();
    }

    /**
     * Prepare staging site for installation
     *
     * @return string Installer link
     * @throws Exception
     */
    public function prepareToInstall()
    {
        $basePath = StagingAddon::getStagingBasePath();
        if (!is_dir($basePath) && !wp_mkdir_p($basePath)) {
            throw new Exception(
                sprintf(__('Cannot create staging directory: %s', 'duplicator-pro'), $basePath)
            );
        }

        return parent::prepareToInstall();
    }

    /**
     * Return installer link for staging
     *
     * @return string|false
     */
    public function getInstallLink()
    {
        $installerUrl = $this->getInstallerFolderUrl();
        if ($installerUrl === false) {
            return false;
        }

        if (!isset($this->info->packInfo->secondaryHash)) {
            return false;
        }

        $data = [
            'archive'    => $this->archive,
            'dup_folder' => 'dup-installer-' . $this->info->packInfo->secondaryHash,
        ];

        if (strlen($this->archivePwd) > 0) {
            $data[BootstrapRunner::NAME_PWD] = $this->archivePwd;
        }

        return trailingslashit($installerUrl) . $this->getInstallerName() . '?' . http_build_query($data);
    }

    /**
     * Get installer name - need to make it accessible
     *
     * @return string
     */
    protected function getInstallerName()
    {
        if (!isset($this->info->installer_backup_name)) {
            return 'installer.php';
        }

        $pathInfo = pathinfo($this->info->installer_backup_name);
        if (!isset($pathInfo['extension']) || $pathInfo['extension'] !== 'php') {
            return $pathInfo['filename'] . '.php';
        }
        return $this->info->installer_backup_name;
    }
}
