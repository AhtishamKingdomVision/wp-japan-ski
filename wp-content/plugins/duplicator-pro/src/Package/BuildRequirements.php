<?php

namespace Duplicator\Package;

use Duplicator\Utils\ManagedHost\ManagedHostMng;
use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\LicensingController;
use Duplicator\Core\Constants;
use Duplicator\Core\MigrationMng;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Libs\Snap\FunctionalityCheck;
use Duplicator\Libs\Snap\SnapOpenBasedir;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapNet;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\AbstractPackage;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Package\Archive\PackageArchive;
use Exception;

/**
 * Class used to get server info
 *
 * @package Duplicator\classes
 */
class BuildRequirements
{
    /**
     * Gets the system requirements which must pass to build a Backup
     *
     * @return array<string, mixed> An array of requirements
     */
    public static function getRequirments(): array
    {
        $dup_tests = [];
        StoragesUtil::getDefaultStorage()->initStorageDirectory(true);

        // PHP SUPPORT
        $dup_tests['PHP'] = [];
        $allRequiredPass  = FunctionalityCheck::checkList(self::getFunctionalitiesCheckList(), true, $noPassFuncs);

        foreach ($noPassFuncs as $func) {
            switch ($func->getType()) {
                case FunctionalityCheck::TYPE_FUNCTION:
                    $errorMessage = $func->getItemKey() . " function doesn't exist.";
                    break;
                case FunctionalityCheck::TYPE_CLASS:
                    $errorMessage = $func->getItemKey() . " class doesn't exist.";
                    break;
                default:
                    throw new Exception('Invalid item type');
            }
            // We will log even if non-required functionalities fail
            self::logRequirementFail('Fail', $errorMessage);
        }

        $dup_tests['PHP']['ALL'] = !in_array('Fail', $dup_tests['PHP']) && $allRequiredPass ? 'Pass' : 'Fail';

        //PERMISSIONS
        $home_path = WpArchiveUtils::getArchiveListPaths('home');
        if (strlen($home_path) === 0) {
            $home_path = DIRECTORY_SEPARATOR;
        }
        if (($handle_test = @opendir($home_path)) === false) {
            $dup_tests['IO']['WPROOT'] = 'Fail';
        } else {
            @closedir($handle_test);
            $dup_tests['IO']['WPROOT'] = 'Pass';
        }

        self::logRequirementFail($dup_tests['IO']['WPROOT'], $home_path . ' (home path) can\'t be opened.');

        $dup_tests['IO']['SSDIR'] = is_writeable(DUPLICATOR_SSDIR_PATH) ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['IO']['SSDIR'], DUPLICATOR_SSDIR_PATH . ' (DUPLICATOR_SSDIR_PATH) can\'t be writeable.');

        $dup_tests['IO']['SSTMP'] = is_writeable(DUPLICATOR_SSDIR_PATH_TMP) ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['IO']['SSTMP'], DUPLICATOR_SSDIR_PATH_TMP . ' (DUPLICATOR_SSDIR_PATH_TMP) can\'t be writeable.');

        $dup_tests['IO']['ALL'] = !in_array('Fail', $dup_tests['IO']) ? 'Pass' : 'Fail';

        //SERVER SUPPORT
        $db_version                    = WpDbUtils::getVersion();
        $dup_tests['SRV']['MYSQL_VER'] = version_compare($db_version, '5.0', '>=') ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['SRV']['MYSQL_VER'], 'MySQL version ' . $db_version . ' is lower than 5.0.');

        //mysqli_real_escape_string test
        $dup_tests['SRV']['MYSQL_ESC'] = WpDbUtils::mysqlEscapeTest() ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['SRV']['MYSQL_ESC'], "The function mysqli_real_escape_string is not escaping strings as expected.");


        $dup_tests['SRV']['ALL'] = !in_array('Fail', $dup_tests['SRV']) ? 'Pass' : 'Fail';

        //INSTALLATION FILES
        $hasInstallFiles             = count(MigrationMng::checkInstallerFilesList()) > 0;
        $dup_tests['RES']['INSTALL'] = !$hasInstallFiles ? 'Pass' : 'Fail';
        self::logRequirementFail($dup_tests['RES']['INSTALL'], 'Installer file(s) are exist on the server.');

        $dup_tests['Success'] = $dup_tests['PHP']['ALL'] == 'Pass' && $dup_tests['IO']['ALL'] == 'Pass' &&
            $dup_tests['SRV']['ALL'] == 'Pass' && $dup_tests['RES']['INSTALL'] == 'Pass';

        return $dup_tests;
    }

    /**
     * Cet list of functionalities to check
     *
     * @return FunctionalityCheck[]
     */
    public static function getFunctionalitiesCheckList(): array
    {
        $global = GlobalEntity::getInstance();
        $result = [];

        if ($global->getBuildMode() == PackageArchive::BUILD_MODE_ZIP_ARCHIVE) {
            $result[] = new FunctionalityCheck(
                FunctionalityCheck::TYPE_CLASS,
                \ZipArchive::class,
                true,
                'https://www.php.net/manual/en/class.ziparchive.php',
                '<i style="font-size:12px">'
                    . '<a href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-work-with-the-different-zip-engines" target="_blank">'
                    . esc_html__('Overview on how to enable ZipArchive', 'duplicator-pro') . '</i></a>'
            );
        }
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'json_encode',
            true,
            'https://www.php.net/manual/en/function.json-encode.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'token_get_all',
            true,
            'https://www.php.net/manual/en/function.token-get-all'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'file_get_contents',
            true,
            'https://www.php.net/manual/en/function.file-get-contents.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'file_put_contents',
            true,
            'https://www.php.net/manual/en/function.file-put-contents.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'mb_strlen',
            true,
            'https://www.php.net/manual/en/mbstring.installation.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'mb_detect_encoding',
            true,
            'https://www.php.net/manual/en/mbstring.installation.php'
        );
        $result[] = new FunctionalityCheck(
            FunctionalityCheck::TYPE_FUNCTION,
            'mb_convert_encoding',
            true,
            'https://www.php.net/manual/en/mbstring.installation.php'
        );

        return $result;
    }

    /**
     * Logs requirement fail status informative message
     *
     * @param string $testStatus   Either it is Pass or Fail
     * @param string $errorMessage Error message which should be logged
     *
     * @return void
     */
    private static function logRequirementFail(string $testStatus, string $errorMessage): void
    {
        if (empty($testStatus)) {
            throw new Exception('Exception: Empty $testStatus [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if (empty($errorMessage)) {
            throw new Exception('Exception: Empty $errorMessage [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        $validTestStatuses = [
            'Pass',
            'Fail',
        ];

        if (!in_array($testStatus, $validTestStatuses)) {
            throw new Exception('Exception: Invalid $testStatus value: ' . $testStatus . ' [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if ('Fail' == $testStatus) {
            DupLog::trace($errorMessage);
        }
    }

    /**
     * Gets the system checks which are not required
     *
     * @param AbstractPackage $package The Backup to check
     *
     * @return array<string,mixed> An array of system checks
     */
    public static function getChecks(AbstractPackage $package): array
    {
        $checks = [];

        //-----------------------------
        //PHP SETTINGS
        $testWebSrv = false;
        if (defined('WP_CLI') && WP_CLI) {
            $testWebSrv = true;
        } else {
            $serverSoftware = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_SOFTWARE', '');
            if (strlen($serverSoftware) > 0) {
                foreach (Constants::SERVER_LIST as $value) {
                    if (stristr($serverSoftware, (string) $value)) {
                        $testWebSrv = true;
                        break;
                    }
                }
            }
        }
        self::logCheckFalse($testWebSrv, 'Any out of server software (' . implode(', ', Constants::SERVER_LIST) . ') doesn\'t exist.');

        // True if open_basedir is disabled
        $testOpenBaseDir = !SnapOpenBasedir::isEnabled();
        self::logCheckFalse($testOpenBaseDir, 'open_basedir is enabled.');

        $pathsOutOpenbaseDir = array_filter($package->Archive->FilterInfo->Dirs->Unknown, fn($path): bool => !SnapOpenBasedir::isPathValid($path));
        self::logCheckFalse(empty($pathsOutOpenbaseDir), 'Some paths are out of open_basedir restriction: ' . implode(', ', $pathsOutOpenbaseDir));

        // If open_basedir is enabled, ensure no paths are outside its restrictions; if disabled, the check passes automatically.
        $openBasedirCheck = $testOpenBaseDir || empty($pathsOutOpenbaseDir);

        $max_execution_time = ini_get("max_execution_time");
        $testMaxExecTime    = ($max_execution_time > DUPLICATOR_SCAN_TIMEOUT) || (strcmp($max_execution_time, 'Off') == 0 || $max_execution_time == 0);

        if (strcmp($max_execution_time, 'Off') == 0) {
            $max_execution_time_error_message = 'max_execution_time should not be' . $max_execution_time;
        } else {
            $max_execution_time_error_message = 'max_execution_time (' . $max_execution_time . ') should not be lower than the DUPLICATOR_SCAN_TIMEOUT ' .
                DUPLICATOR_SCAN_TIMEOUT;
        }
        self::logCheckFalse($testMaxExecTime, $max_execution_time_error_message);

        $testMySqlConnect = function_exists('mysqli_connect');
        self::logCheckFalse($testMySqlConnect, 'mysqli_connect function doesn\'t exist.');

        $testURLFopen = SnapServer::isURLFopenEnabled();
        self::logCheckFalse($testURLFopen, 'URL Fopen isn\'t enabled.');

        $testCURL = SnapUtil::isCurlEnabled();
        self::logCheckFalse($testCURL, 'curl_init function doesn\'t exist.');

        $test64Bit = (bool) strstr(SnapUtil::getArchitectureString(), '64');
        self::logCheckFalse($test64Bit, 'This servers PHP architecture is NOT 64-bit.  Backups over 2GB are not possible.');

        $testMemory = SnapServer::memoryLimitCheck(DUPLICATOR_MIN_MEMORY_LIMIT);
        self::logCheckFalse($testCURL, 'memory_limit is less than DUPLICATOR_MIN_MEMORY_LIMIT: ' . DUPLICATOR_MIN_MEMORY_LIMIT);

        $checks['SRV']['Brand'] = DupPackage::isActiveBrandPrepared();
        $checks['SRV']['HOST']  = ManagedHostMng::getInstance()->getActiveHostings();

        $checks['SRV']['PHP']['websrv']        = $testWebSrv;
        $checks['SRV']['PHP']['openbase']      = $testOpenBaseDir;
        $checks['SRV']['PHP']['maxtime']       = $testMaxExecTime;
        $checks['SRV']['PHP']['mysqli']        = $testMySqlConnect;
        $checks['SRV']['PHP']['allowurlfopen'] = $testURLFopen;
        $checks['SRV']['PHP']['curlavailable'] = $testCURL;
        $checks['SRV']['PHP']['arch64bit']     = $test64Bit;
        $checks['SRV']['PHP']['minMemory']     = $testMemory;
        $checks['SRV']['PHP']['version']       = true; // now the plugin is activated only if the minimum version is valid, so this check is always true
        $allCheck                              = true;
        foreach ($checks['SRV']['PHP'] as $key => $check) {
            if ($check === false) {
                $allCheck = false;
                break;
            }
        }
        $checks['SRV']['PHP']['ALL'] = $allCheck;

        //-----------------------------
        //WORDPRESS SETTINGS

        //Core dir and files logic
        $testHasWpCoreFiltered = !$package->Archive->hasWpCoreFolderFiltered();

        $testIsMultisite = is_multisite();

        $checks['SRV']['WP']['version'] = true; // This check is always true because the plugin is activated only if the minimum version is valid
        $checks['SRV']['WP']['core']    = $testHasWpCoreFiltered;
        // $checks['SRV']['WP']['cache'] = $testCache;
        $checks['SRV']['WP']['ismu']     = $testIsMultisite;
        $checks['SRV']['WP']['ismuplus'] = License::can(License::CAPABILITY_MULTISITE_PLUS);

        if ($testIsMultisite) {
            $checks['SRV']['WP']['ALL'] = ($testHasWpCoreFiltered && $checks['SRV']['WP']['ismuplus']);
            self::logCheckFalse($checks['SRV']['WP']['ismuplus'], 'WP is multi-site setup and licence type is not Business Gold.');
        } else {
            $checks['SRV']['WP']['ALL'] = ($testHasWpCoreFiltered);
        }

        return $checks;
    }

    /**
     * Logs checks false informative message
     *
     * @param boolean $check        Either it is true or false
     * @param string  $errorMessage Error message which should be logged when check is false
     *
     * @return void
     */
    private static function logCheckFalse(bool $check, string $errorMessage): void
    {
        if (empty($errorMessage)) {
            throw new Exception('Exception: Empty $errorMessage [File: ' . __FILE__ . ', Ln: ' . __LINE__);
        }

        if (false === $check) {
            DupLog::trace($errorMessage);
        }
    }

    /**
     * Returns the server settings data
     *
     * @return array<mixed>
     */
    public static function getServerSettingsData(): array
    {
        $serverSettings = [];

        //GENERAL SETTINGS
        $serverSettings[] = [
            'title'    => __('General', 'duplicator-pro'),
            'settings' => self::getGeneralServerSettings(),
        ];

        //WORDPRESS SETTINGS
        $serverSettings[] = [
            'title'    => __('WordPress', 'duplicator-pro'),
            'settings' => self::getWordPressServerSettings(),
        ];

        //PHP SETTINGS
        $serverSettings[] = [
            'title'    => __('PHP', 'duplicator-pro'),
            'settings' => self::getPHPServerSettings(),
        ];

        //MYSQL SETTINGS
        $serverSettings[] = [
            'title'    => __('MySQL', 'duplicator-pro'),
            'settings' => self::getMysqlServerSettings(),
        ];

        // Paths Info
        $serverSettings[] = [
            'title'    => __('Paths Info', 'duplicator-pro'),
            'settings' => self::getPathsSettings(),
        ];

        //URLs info
        $urlsSettings = [];
        foreach (WpArchiveUtils::getOriginalURLs() as $key => $url) {
            $urlsSettings[] = [
                'label'    => __('URL ', 'duplicator-pro') . $key,
                'logLabel' => 'URL ' . $key,
                'value'    => $url,
            ];
        }

        $serverSettings[] = [
            'title'    => __('URLs Info', 'duplicator-pro'),
            'settings' => $urlsSettings,
        ];

        //Disk Space
        $home_path          = SnapWP::getHomePath(true);
        $space              = SnapIO::diskTotalSpace($home_path);
        $space_free         = SnapIO::diskFreeSpace($home_path);
        $serverDiskSettings = [
            [
                'label'           => __('Free Space', 'duplicator-pro'),
                'logLabel'        => 'Free Space',
                'value'           => sprintf(
                    __('%1$s%% -- %2$s from %3$s', 'duplicator-pro'),
                    round($space_free / $space * 100, 2),
                    SnapString::byteSize($space_free),
                    SnapString::byteSize($space)
                ),
                'valueNoteBottom' => __(
                    'Note: This value is the physical servers hard-drive allocation.
                    On shared hosts check your control panel for the "TRUE" disk space quota value.',
                    'duplicator-pro'
                ),
            ],
        ];

        $serverSettings[] = [
            'title'    => __('Server Disk', 'duplicator-pro'),
            'settings' => $serverDiskSettings,
        ];

        return $serverSettings;
    }

    /**
     * Returns the geleral server settings
     *
     * @return array<mixed>
     */
    private static function getGeneralServerSettings(): array
    {
        $serverSoftware = SnapUtil::sanitizeTextInput(
            INPUT_SERVER,
            'SERVER_SOFTWARE',
            __('Unknown', 'duplicator-pro')
        );

        return [
            [
                'label'     => __('Duplicator Version', 'duplicator-pro'),
                'logLabel'  => 'Duplicator Version',
                'value'     => DUPLICATOR_VERSION,
                'valueNote' => sprintf(
                    _x(
                        '- %1$sCheck WordPress Updates%2$s',
                        '%1$s and %2$s are the opening and closing anchor tags',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(LicensingController::getForceUpgradeCheckURL()) . '">',
                    '</a>'
                ),
            ],
            [
                'label'    => __('Operating System', 'duplicator-pro'),
                'logLabel' => 'Operating System',
                'value'    => PHP_OS,
            ],
            [
                'label'     => __('Timezone', 'duplicator-pro'),
                'logLabel'  => 'Timezone',
                'value'     => function_exists('wp_timezone_string') ? wp_timezone_string() :  __('Unknown', 'duplicator-pro'),
                'valueNote' => sprintf(
                    _x(
                        'This is a %1$sWordPress Setting%2$s',
                        '%1$s and %2$s are the opening and closing anchor tags',
                        'duplicator-pro'
                    ),
                    '<a href="options-general.php">',
                    '</a>'
                ),
            ],

            [
                'label'    => __('Server Time', 'duplicator-pro'),
                'logLabel' => 'Server Time',
                'value'    => current_time('Y-m-d H:i:s'),
            ],
            [
                'label'    => __('Web Server', 'duplicator-pro'),
                'logLabel' => 'Web Server',
                'value'    => $serverSoftware,
            ],
            [
                'label'    => __('Loaded PHP INI', 'duplicator-pro'),
                'logLabel' => 'Loaded PHP INI',
                'value'    => php_ini_loaded_file(),
            ],
            [
                'label'    => __('Server IP', 'duplicator-pro'),
                'logLabel' => 'Server IP',
                'value'    => (SnapNet::getServerIP() !== '') ? SnapNet::getServerIP() : __("Can't detect", 'duplicator-pro'),
            ],
            [
                'label'    => __('Outbound IP', 'duplicator-pro'),
                'logLabel' => 'Outbound IP',
                'value'    => (SnapNet::getOutboundIP() !== '') ? SnapNet::getOutboundIP() : __("Can't detect", 'duplicator-pro'),
            ],
            [
                'label'    => __('Client IP', 'duplicator-pro'),
                'logLabel' => 'Client IP',
                'value'    => (SnapNet::getClientIP() !== '') ? SnapNet::getClientIP() : __("Can't detect", 'duplicator-pro'),
            ],
            [
                'label'    => __('Host', 'duplicator-pro'),
                'logLabel' => 'Host',
                'value'    => parse_url(get_site_url(), PHP_URL_HOST),
            ],
            [
                'label'    => __('Duplicator Version', 'duplicator-pro'),
                'logLabel' => 'Duplicator Version',
                'value'    => DUPLICATOR_VERSION,
            ],
        ];
    }

    /**
     * Returns the WP server settings
     *
     * @return array<mixed>
     */
    private static function getWordPressServerSettings(): array
    {
        global $wp_version;
        $managedHosting = (ManagedHostMng::getInstance()->isManaged() === false) ?
            __('No managed hosting detected', 'duplicator-pro') :
            implode(', ', ManagedHostMng::getInstance()->getActiveHostings());

        return [
            [
                'label'    => __('WordPress Version', 'duplicator-pro'),
                'logLabel' => 'WordPress Version',
                'value'    => $wp_version,
            ],
            [
                'label'    => __('Language', 'duplicator-pro'),
                'logLabel' => 'Language',
                'value'    => get_bloginfo('language'),
            ],
            [
                'label'    => __('Charset', 'duplicator-pro'),
                'logLabel' => 'Charset',
                'value'    => get_bloginfo('charset'),
            ],
            [
                'label'    => __('Memory Limit', 'duplicator-pro'),
                'logLabel' => 'Memory Limit',
                'value'    => WP_MEMORY_LIMIT,
            ],
            [
                'label'    => __('Managed hosting', 'duplicator-pro'),
                'logLabel' => 'Managed hosting',
                'value'    => $managedHosting,
            ],
        ];
    }

    /**
     * Returns the PHP server settings
     *
     * @return array<mixed>
     */
    private static function getPHPServerSettings(): array
    {
        return [
            [
                'label'    => __('PHP Version', 'duplicator-pro'),
                'logLabel' => 'PHP Version',
                'value'    => phpversion(),
            ],
            [
                'label'    => __('PHP SAPI', 'duplicator-pro'),
                'logLabel' => 'PHP SAPI',
                'value'    => PHP_SAPI,
            ],
            [
                'label'    => __('User', 'duplicator-pro'),
                'logLabel' => 'User',
                'value'    => SnapServer::getPHPUser(),
            ],
            [
                'label'     => __('Memory Limit', 'duplicator-pro'),
                'logLabel'  => 'Memory Limit',
                'labelLink' => 'http://www.php.net/manual/en/ini.core.php#ini.memory-limit',
                'value'     => @ini_get('memory_limit'),
            ],
            [
                'label'    => __('Memory In Use', 'duplicator-pro'),
                'logLabel' => 'Memory In Use',
                'value'    => size_format(memory_get_usage(true)),
            ],
            [
                'label'        => __('Max Execution Time', 'duplicator-pro'),
                'logLabel'     => 'Max Execution Time',
                'labelLink'    => 'http://www.php.net/manual/en/info.configuration.php#ini.max-execution-time',
                'value'        => @ini_get('max_execution_time'),
                'valueNote'    => sprintf(
                    _x('(default) - %1$s', '%1$s = "is dynamic" or "value is fixed" based on settings', 'duplicator-pro'),
                    set_time_limit(0) ? __('is dynamic', 'duplicator-pro') : __('value is fixed', 'duplicator-pro')
                ),
                'valueTooltip' =>
                __(
                    'If the value shows dynamic then this means its possible for PHP to run longer than the default. 
                    If the value is fixed then PHP will not be allowed to run longer than the default.',
                    'duplicator-pro'
                ),
            ],
            [
                'label'     => __('open_basedir', 'duplicator-pro'),
                'logLabel'  => 'open_basedir',
                'labelLink' => 'http://php.net/manual/en/ini.core.php#ini.open-basedir',
                'value'     => empty(@ini_get('open_basedir')) ? __('Off', 'duplicator-pro') : @ini_get('open_basedir'),
            ],
            [
                'label'     => __('Shell (shell_exec)', 'duplicator-pro'),
                'logLabel'  => 'Shell (shell_exec)',
                'labelLink' => 'http://us3.php.net/shell_exec',
                'value'     => !Shell::hasDisabledFunctions('shell_exec') ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'     => __('Shell (popen)', 'duplicator-pro'),
                'logLabel'  => 'Shell (popen)',
                'labelLink' => 'http://us3.php.net/popen',
                'value'     => !Shell::hasDisabledFunctions('popen') ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'     => __('Shell (exec)', 'duplicator-pro'),
                'logLabel'  => 'Shell (exec)',
                'labelLink' => 'https://www.php.net/manual/en/function.exec.php',
                'value'     => !Shell::hasDisabledFunctions('exec') ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'    => __('Shell Exec Zip', 'duplicator-pro'),
                'logLabel' => 'Shell Exec Zip',
                'value'    => (ShellZipUtils::getShellExecZipPath() != null) ? __('Is Supported', 'duplicator-pro') : __('Not Supported', 'duplicator-pro'),
            ],
            [
                'label'     => __('Suhosin Extension', 'duplicator-pro'),
                'logLabel'  => 'Suhosin Extension',
                'labelLink' => 'https://suhosin.org/stories/index.html',
                'value'     => Shell::isSuhosinEnabled() ? __('Enabled', 'duplicator-pro') : __('Disabled', 'duplicator-pro'),
            ],
            [
                'label'    => __('Architecture', 'duplicator-pro'),
                'logLabel' => 'Architecture',
                'value'    => SnapUtil::getArchitectureString(),
            ],
            [
                'label'    => __('Error Log File', 'duplicator-pro'),
                'logLabel' => 'Error Log File',
                'value'    => @ini_get('error_log'),
            ],
        ];
    }

    /**
     * Returns the MySQL server settings
     *
     * @return array<mixed>
     */
    private static function getMysqlServerSettings(): array
    {
        return [
            [
                'label'    => __('Version', 'duplicator-pro'),
                'logLabel' => 'Version',
                'value'    => WpDbUtils::getVersion(),
            ],
            [
                'label'    => __('Charset', 'duplicator-pro'),
                'logLabel' => 'Charset',
                'value'    => DB_CHARSET,
            ],
            [
                'label'     => __('Wait Timeout', 'duplicator-pro'),
                'logLabel'  => 'Wait Timeout',
                'labelLink' => 'http://dev.mysql.com/doc/refman/5.0/en/server-system-variables.html#sysvar_wait_timeout',
                'value'     => WpDbUtils::getVariable('wait_timeout'),
            ],
            [
                'label'     => __('Max Allowed Packets', 'duplicator-pro'),
                'logLabel'  => 'Max Allowed Packets',
                'labelLink' => 'http://dev.mysql.com/doc/refman/5.0/en/server-system-variables.html#sysvar_max_allowed_packet',
                'value'     => WpDbUtils::getVariable('max_allowed_packet'),
            ],
            [
                'label'     => __('mysqldump Path', 'duplicator-pro'),
                'logLabel'  => 'mysqldump Path',
                'labelLink' => 'http://dev.mysql.com/doc/refman/5.0/en/mysqldump.html',
                'value'     => WpDbUtils::getMySqlDumpPath() !== false ? WpDbUtils::getMySqlDumpPath() : __('Path Not Found', 'duplicator-pro'),
            ],
        ];
    }

    /**
     * Returns the paths settings
     *
     * @return array<mixed>
     */
    private static function getPathsSettings(): array
    {
        $pathsSettings = [
            [
                'label'    => __('Target root path', 'duplicator-pro'),
                'logLabel' => 'Target root path',
                'value'    => WpArchiveUtils::getTargetRootPath(),
            ],
        ];

        foreach (WpArchiveUtils::getOriginalPaths() as $key => $origPath) {
            $pathsSettings[] = [
                'label'    => __('Original ', 'duplicator-pro') . $key,
                'logLabel' => 'Original ' . $key,
                'value'    => $origPath,
            ];
        }

        foreach (WpArchiveUtils::getArchiveListPaths() as $key => $archivePath) {
            $pathsSettings[] = [
                'label'    => __('Archive ', 'duplicator-pro') . $key,
                'logLabel' => 'Archive ' . $key,
                'value'    => $archivePath,
            ];
        }

        return $pathsSettings;
    }
}
