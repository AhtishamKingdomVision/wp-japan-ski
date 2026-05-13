<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\WpCliAddon;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\LockUtil;
use Duplicator\Package\Storage\Status\StatusChecker;
use Error;
use Exception;
use WP_CLI;

class DuplicatorCli
{
    const STORAGES_UPDATE_MIN_INTERVAL = 10;

    /**
     * DuplicatorCli constructor.
     */
    public function __construct()
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('duplicator', self::class);
        }
    }

    /**
     * Duplicator Info
     *
     * @return void
     */
    public function info(): void
    {
        WP_CLI::line('Duplicator WP-CLI Addon');
        WP_CLI::line('Duplicator Version: ' . DUPLICATOR_VERSION);
        WP_CLI::line('PHP Version: ' . PHP_VERSION);
        global $wp_version;
        WP_CLI::line('WordPress Version: ' . $wp_version);
    }

    /**
     * Check and update backup storage status, check if the archive file is available in the storage.
     *
     * ## OPTIONS
     *
     * --minInterval=<seconds>
     *      Minimum time interval in seconds between status checks for each backup. Default is 10 minutes (600 seconds).
     *      Only backups that haven't been checked within this interval will be processed. Minimum allowed value is 10 seconds.
     *
     * --delete
     *      Delete all backups that don't have at least one storage or error status.
     *
     * ## EXAMPLES
     *
     *     wp duplicator storagesUpdate
     *     wp duplicator storagesUpdate --minInterval=30
     *     wp duplicator storagesUpdate --delete
     *
     * @param scalar[]             $args      Command arguments
     * @param array<string,scalar> $assocArgs Command options
     *
     * @return void
     */
    public function storagesUpdate($args, $assocArgs = []): void
    {
        try {
            WP_CLI::log('Starting remote backup status check...');
            $minInterval = max(self::STORAGES_UPDATE_MIN_INTERVAL, $assocArgs['minInterval'] ?? StatusChecker::MIN_INTERVAL_WP_CLI);
            $delete      = isset($assocArgs['delete']) && (bool)$assocArgs['delete'];
            WP_CLI::log('Skipping backups checked in the last ' . $minInterval . ' seconds');
            if ($delete) {
                WP_CLI::log('Deleting backups that don\'t have at least one storage or error status...');
            }

            $totalProcessed = 0;
            do {
                if (($processed = StatusChecker::processNextChunk($minInterval)) < 0) {
                    throw new Exception('Failed to process next chunk');
                }
                WP_CLI::log('Processing ... total processed ' . $totalProcessed);
                $totalProcessed += $processed;
            } while ($processed > 0);

            WP_CLI::success(sprintf('Successfully checked %d backups.', $totalProcessed));

            if ($delete) {
                WP_CLI::log('Deleting backups that don\'t have at least one storage or error status...');
                $totalDeleted = 0;
                do {
                    $deleted       = PackageUtils::bulkDeletePackageWithoutStoragesChunk();
                    $totalDeleted += $deleted;
                    WP_CLI::log('Deleting ... total deleted ' . $totalDeleted);
                } while ($deleted > 0);
                WP_CLI::success(sprintf('Successfully deleted %d backups that don\'t have at least one storage or error status.', $totalDeleted));
            }
        } catch (Exception | Error $e) {
            WP_CLI::error($e->getMessage());
        }
    }

    /**
     * Build Duplicator Backup
     *
     * Options
     *
     * --template=<ID>
     *      The template id to use, if not specified the default template will be used
     *
     * --dir=<path>
     *      The directory to copy the backup to, if not specified the backup will not be copied
     *
     * --delete
     *      Delete the package after copying
     *
     * --phpsqldump
     *      If true use phpdump instead of mysqldump, default false and use mysqldump (Not implemented yet)
     *
     * --phpzip
     *      If true use php zip instead shell zip, default false and use shell zip
     *
     * --duparchive
     *      If true use dup archive engine, default false and use shell zip
     *
     * @param scalar[]             $args      Command arguments
     * @param array<string,scalar> $assocArgs Command options
     *
     * @return void
     */
    public function build($args, $assocArgs = []): void
    {
        try {
            $failed = false;
            WP_CLI::debug("Excecute Backup Build");
            DupLog::trace('WP-CLI: Backup Build Command');
            $assocArgs = wp_parse_args(
                $assocArgs,
                [
                    'phpsqldump' => false, // Not implemented yet
                    'phpzip'     => false, // If true use php zip instead shell zip, default false and use shell zip
                    'duparchive' => false, // If true use dup archive engine, default false and use shell zip
                    'template'   => 0, // The template id to use, if not specified the default template will be used
                    'dir'        => '', // The directory to copy the backup to, if not specified the backup will not be copied
                    'delete'     => false, // Delete the package after copying
                ]
            );

            $tempalteId = $assocArgs['template'];

            if (!is_writable(DUPLICATOR_SSDIR_PATH_TMP)) {
                throw new Exception('Current user does not have permission to write to the Duplicator temporary directory');
            }

            if (strlen($assocArgs['dir']) > 0 && !is_dir($assocArgs['dir'])) {
                throw new Exception('The directory specified does not exist');
            }

            if (strlen($assocArgs['dir']) > 0 && !is_writable($assocArgs['dir'])) {
                throw new Exception('The directory specified is not writable');
            }

            if ($tempalteId == 0) {
                if (($template = TemplateEntity::getDefaultTemplate()) == null) {
                    throw new Exception('No default template found');
                }
            } else {
                if (($template = TemplateEntity::getById($tempalteId)) == null) {
                    throw new Exception("Template {$tempalteId} not found");
                }
            }

            $homePath = SnapWP::getHomePath();
            if (!is_dir($homePath) || chdir($homePath) == false) {
                throw new Exception("Failed to change directory to {$homePath}");
            }

            if (!LockUtil::lockProcess()) {
                DupLog::trace("File locked so skipping");
                throw new Exception("Another cron already running so skipping");
            }

            $global = GlobalEntity::getInstance();
            if ($assocArgs['phpzip']) {
                $archiveMode = PackageArchive::BUILD_MODE_ZIP_ARCHIVE;
            } elseif ($assocArgs['duparchive']) {
                $archiveMode = PackageArchive::BUILD_MODE_DUP_ARCHIVE;
            } else {
                $archiveMode = PackageArchive::BUILD_MODE_SHELL_EXEC;
            }
            $global->setArchiveMode($archiveMode, PackageArchive::ZIP_MODE_SINGLE_THREAD, true, true);
            $global->setDbMode('mysql');

            $package = new DupPackage(
                DupPackage::EXEC_TYPE_MANUAL,
                [StoragesUtil::getDefaultStorageId()],
                $template,
                null
            );
            $package->save();
            WP_CLI::success("Building Backup[{$package->getId()}] {$package->getName()} ...");

            DupLog::trace('WP-CLI: Backup Build Command: Start build');
            WP_CLI::debug("Run Process");

            do {
                BackupBuild::process($package);
                WP_CLI::debug("Run build end Package status " . $package->getStatus());

                if ($package->getStatus() < AbstractPackage::STATUS_PRE_PROCESS) {
                    throw new Exception('Package status error: ' . $package->getStatus());
                }
            } while ($package->getStatus() < AbstractPackage::STATUS_COMPLETE);
            $package->save();

            if (strlen($assocArgs['dir']) > 0) {
                $backupFile    = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_ARCHIVE);
                $installerFile = $package->getLocalPackageFilePath(AbstractPackage::FILE_TYPE_INSTALLER);
                $targetDir     = $assocArgs['dir'];

                if (!file_exists($backupFile)) {
                    WP_CLI::warning("Backup file not found: {$backupFile}");
                    $failed = true;
                } else {
                    $backupFileTarget = $targetDir . '/' . basename($backupFile);
                    if (!copy($backupFile, $backupFileTarget)) {
                        WP_CLI::warning("Failed to copy backup file to {$backupFileTarget}");
                        $failed = true;
                    }
                }

                if (!file_exists($installerFile)) {
                    WP_CLI::warning("Installer file not found: {$installerFile}");
                    $failed = true;
                } else {
                    $installerFileTarget = $targetDir . '/' . basename($installerFile);
                    if (!copy($installerFile, $installerFileTarget)) {
                        WP_CLI::warning("Failed to copy installer file to {$installerFileTarget}");
                        $failed = true;
                    }
                }

                if (!$failed) {
                    WP_CLI::success("Backup files copied to {$targetDir}");
                }
            }

            if ($assocArgs['delete']) {
                $package->delete();
                WP_CLI::success("Backup {$package->getName()} Deleted");
            }
            WP_CLI::success("Backup {$package->getName()} Build Completed");
        } catch (Exception | Error $e) {
            WP_CLI::warning($e->getMessage());
            $failed = true;
        } finally {
            LockUtil::unlockProcess();
            DupLog::trace('WP-CLI: Backup Build Command end');
            DupLog::close();
        }

        if ($failed) {
            WP_CLI::error("Command Backup Build Failed");
        } else {
            WP_CLI::success("Command Backup Build Completed");
        }
    }

    /**
     * Duplicator Full Cleanup
     * Remove all Duplicator backup files and temporary files
     *
     * @return void
     */
    public function cleanup(): void
    {
        try {
            // first last package id
            $ids = DupPackage::getIdsByStatus();
            foreach ($ids as $id) {
                $package = DupPackage::getById($id);
                WP_CLI::line("Delete Backup[{$package->getId()}] {$package->getName()}");
                // A smooth deletion is not performed because it is a forced reset.
                DupPackage::forceDelete($id);
            }



            foreach (PackageUtils::getOrphanedPackageFiles() as $filepath) {
                if (is_writable($filepath)) {
                    WP_CLI::line("Delete Orphaned Backup File: {$filepath}");
                    unlink($filepath);
                } else {
                    WP_CLI::warning("Failed to delete Orphaned Backup File: {$filepath}");
                }
            }

            PackageUtils::tmpCleanup(true);
        } catch (Exception | Error $e) {
            WP_CLI::warning($e->getMessage());
        } finally {
            DupLog::trace('WP-CLI: Backup Build Command end');
            DupLog::close();
        }

        WP_CLI::success("Build Cleanup Completed");
    }
}
