<?php

/**
 * Trait for package build operations
 *
 * @package   Duplicator
 * @copyright (c) 2017, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Package;

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Utils\Logging\DupLog;
use Exception;

/**
 * Trait TraitPackageBuild
 *
 * Handles package build operations including the main build process,
 * build start, build complete, cleanup, integrity checks, and failure handling.
 *
 * @phpstan-require-extends AbstractPackage
 */
trait TraitPackageBuild
{
    /**
     * Starts the Backup build process
     *
     * @param bool $closeOnEnd if true the function will close the log and die on error
     *
     * @return void
     */
    public function runBuild($closeOnEnd = true): void
    {
        try {
            DupLog::trace('Main build step');

            // START LOGGING
            DupLog::open($this->getNameHash());
            $global = GlobalEntity::getInstance();
            $this->build_progress->startTimer();
            if ($this->build_progress->initialized == false) {
                $this->runBuildStart();
            }

            // At one point having this as an else as not part of the main logic prevented failure emails from getting sent.
            // Note2: Think that by putting has_completed() at top of check will prevent archive from continuing to build after a failure has hit.
            if ($this->build_progress->hasCompleted()) {
                $this->runBuildComplete();
            } elseif (!$this->build_progress->database_script_built) {
                //START BUILD
                //PHPs serialze method will return the object, but the ID above is not passed
                //for one reason or another so passing the object back in seems to do the trick
                try {
                    if ((!$global->package_mysqldump) && ($global->package_phpdump_mode == WpDbUtils::PHPDUMP_MODE_MULTI)) {
                        $this->Database->buildInChunks();
                    } else {
                        $this->Database->build();
                        $this->build_progress->database_script_built = true;
                        $this->update();
                    }
                } catch (Exception $e) {
                    do_action('duplicator_build_database_fail', $this);
                    DupLog::infoTrace("Runtime error in database dump Message: " . $e->getMessage());
                    throw $e;
                }

                DupLog::trace("Done building database");
                if ($this->build_progress->database_script_built) {
                    DupLog::trace("Set db built for Backup $this->ID");
                }
            } elseif (!$this->build_progress->archive_built) {
                $this->Archive->buildFile($this);
                $this->update();
            } elseif (!$this->build_progress->installer_built) {
                // Note: Duparchive builds installer within the main build flow not here
                $this->Installer->build($this->build_progress);
                $this->update();
                if ($this->build_progress->failed) {
                    throw new Exception('ERROR: Problem adding installer to archive.');
                }
            }

            if ($this->build_progress->failed) {
                throw new Exception('Build progress fail');
            }
        } catch (Exception $e) {
            DupLog::infoTraceException($e, 'Build failed');
            $message  = "Backup creation failed.\n"
                . " EXCEPTION message: " . $e->getMessage() . "\n";
            $message .= $e->getFile() . ' LINE: ' . $e->getLine() . "\n";
            $message .= $e->getTraceAsString();
            $this->buildFail($message, $closeOnEnd);
        }

        if ($closeOnEnd) {
            DupLog::close();
        }
    }

    /**
     * Run build start
     *
     * @return void
     */
    protected function runBuildStart(): void
    {
        global $wp_version;
        $global = GlobalEntity::getInstance();

        DupLog::trace("**** START OF BUILD: " . $this->getNameHash());

        do_action('duplicator_build_before_start', $this);
        $this->timer_start     = microtime(true);
        $this->ziparchive_mode = $global->ziparchive_mode;
        if (!License::can(License::CAPABILITY_MULTISITE_PLUS)) {
            $this->Multisite->FilterSites = [];
        }
        $php_max_time       = @ini_get("max_execution_time");
        $php_max_memory     = @ini_get('memory_limit');
        $php_max_time       = ($php_max_time == 0) ? "(0) no time limit imposed" : "[{$php_max_time}] not allowed";
        $php_max_memory     = ($php_max_memory === false) ? "Unable to set php memory_limit" : WP_MAX_MEMORY_LIMIT . " ({$php_max_memory} default)";
        $architecture       = SnapUtil::getArchitectureString();
        $clientkickoffstate = $global->clientside_kickoff ? 'on' : 'off';
        $archive_engine     = $global->getArchiveEngine();
        $serverSoftware     = SnapUtil::sanitizeTextInput(INPUT_SERVER, 'SERVER_SOFTWARE', 'unknown');
        $info               = "********************************************************************************\n";
        $info              .= "********************************************************************************\n";
        $info              .= "DUPLICATOR PRO PACKAGE-LOG: " . @date("Y-m-d H:i:s") . "\n";
        $info              .= "NOTICE: Do NOT post to public sites or forums \n";
        $info              .= "PACKAGE CREATION START\n";
        $info              .= "********************************************************************************\n";
        $info              .= "********************************************************************************\n";
        $info              .= "VERSION:\t" . DUPLICATOR_VERSION . "\n";
        $info              .= "WORDPRESS:\t{$wp_version}\n";
        $info              .= "PHP INFO:\t" . phpversion() . ' | ' . 'SAPI: ' . php_sapi_name() . "\n";
        $info              .= "SERVER:\t\t{$serverSoftware} \n";
        $info              .= "ARCHITECTURE:\t{$architecture} \n";
        $info              .= "CLIENT KICKOFF: {$clientkickoffstate} \n";
        $info              .= "PHP TIME LIMIT: {$php_max_time} \n";
        $info              .= "PHP MAX MEMORY: {$php_max_memory} \n";
        $info              .= "RUN TYPE:\t" . PackageUtils::getExecTypeString($this->getExecutionType(), $this->template_id) . "\n";
        $info              .= "MEMORY STACK:\t" . SnapServer::getPHPMemory() . "\n";
        $info              .= "ARCHIVE ENGINE: {$archive_engine}\n";
        $info              .= "PACKAGE COMPONENTS:\n\t" . BuildComponents::displayComponentsList($this->components, ",\n\t");
        DupLog::infoTrace($info);
        // CREATE DB RECORD
        $this->build_progress->setBuildMode();

        if ($this->Archive->isArchiveEncrypt() && !SettingsUtils::isArchiveEncryptionAvailable()) {
            throw new Exception("Archive encryption isn't available.");
        }

        $this->build_progress->initialized = true;
        $this->setStatus(AbstractPackage::STATUS_START);
        do_action('duplicator_build_start', $this);

        if (
            $this->getExecutionType() === AbstractPackage::EXEC_TYPE_SCHEDULED &&
            !License::can(License::CAPABILITY_SCHEDULE)
        ) {
            // Prevent scheduled backups from running if the license doesn't support it
            throw new Exception("Can't process package schedule " . $this->ID . " because Duplicator isn't licensed");
        }

        // Validate that at least one valid storage exists before starting backup
        if (!$this->canStartBackup()) {
            $errorMessage = __('Cannot start backup: There are invalid storages. Please check your storage configurations.', 'duplicator-pro');
            DupLog::error(__('Backup validation failed', 'duplicator-pro'), $errorMessage);
            throw new Exception($errorMessage);
        }
    }

    /**
     * Run build complete
     *
     * @return void
     */
    protected function runBuildComplete(): void
    {
        DupLog::info("\n********************************************************************************");
        DupLog::info("STORAGE:");
        DupLog::info("********************************************************************************");
        foreach ($this->upload_infos as $upload_info) {
            $storage = $upload_info->getStorage();
            if ($storage->isValid() === false) {
                continue;
            }
            // Protection against deleted storage
            $storage_type_string = strtoupper($storage->getStypeName());
            $storage_path        = $storage->getLocationString();
            DupLog::info($storage_type_string . ": " . $storage->getName() . ', ' . $storage_path);
        }

        if (!$this->build_progress->failed) {
            // Only makees sense to perform build integrity check on completed archives
            $this->buildIntegrityCheck();
        }

        $timerEnd      = microtime(true);
        $timerSum      = SnapString::formattedElapsedTime($timerEnd, $this->timer_start);
        $this->Runtime = $timerSum;
        // FINAL REPORT
        $info  = "\n********************************************************************************\n";
        $info .= "RECORD ID:[{$this->ID}]\n";
        $info .= "TOTAL PROCESS RUNTIME: {$timerSum}\n";
        $info .= "PEAK PHP MEMORY USED: " . SnapServer::getPHPMemory(true) . "\n";
        $info .= "DONE PROCESSING => {$this->name} " . @date("Y-m-d H:i:s") . "\n";
        DupLog::info($info);
        DupLog::trace("Done Backup building");

        if ($this->build_progress->failed) {
            throw new Exception("Backup creation failed.");
        } else {
            //File Cleanup
            $this->buildCleanup();
            do_action('duplicator_build_completed', $this);
        }
    }

    /**
     * Build cleanup
     *
     * @return void
     */
    protected function buildCleanup(): void
    {
        $files = SnapIO::regexGlob(DUPLICATOR_SSDIR_PATH_TMP);
        if (count($files) > 0) {
            $filesToStore = [
                $this->Installer->getInstallerLocalName(),
                $this->Archive->getFileName(),
            ];
            $newPath      = DUPLICATOR_SSDIR_PATH;

            foreach ($files as $file) {
                $fileName = basename($file);

                if (!strstr($fileName, $this->getNameHash())) {
                    continue;
                }

                if (in_array($fileName, $filesToStore)) {
                    if (function_exists('rename')) {
                        rename($file, "{$newPath}/{$fileName}");
                    } elseif (function_exists('copy')) {
                        copy($file, "{$newPath}/{$fileName}");
                    } else {
                        throw new Exception('copy and rename function don\'t found');
                    }
                }

                if (file_exists($file)) {
                    unlink($file);
                }
            }
        }
        $this->setStatus(AbstractPackage::STATUS_COPIEDPACKAGE);
    }


    /**
     * Integrity check for the build process
     *
     * @return void
     */
    protected function buildIntegrityCheck()
    {
        //INTEGRITY CHECKS
        //We should not rely on data set in the serialized object, we need to manually check each value
        //indepentantly to have a true integrity check.
        DupLog::info("\n********************************************************************************");
        DupLog::info("INTEGRITY CHECKS:");
        DupLog::info("********************************************************************************");
        //------------------------
        //SQL CHECK:  File should be at minimum 5K.  A base WP install with only Create tables is about 9K
        $sql_temp_path = $this->Database->getTempSafeFilePath();
        $sql_temp_size = @filesize($sql_temp_path);
        $sql_easy_size = SnapString::byteSize($sql_temp_size);
        $sql_done_txt  = SnapIO::tailFile($sql_temp_path, 3);

        // Note: Had to add extra size check of 800 since observed bad sql when filter was on
        if (
            in_array(BuildComponents::COMP_DB, $this->components) &&
            (!strstr($sql_done_txt, (string) DUPLICATOR_DB_EOF_MARKER) ||
                (!$this->Database->FilterOn && $sql_temp_size < DUPLICATOR_MIN_SIZE_DBFILE_WITHOUT_FILTERS) ||
                ($this->Database->FilterOn && $this->Database->info->tablesFinalCount > 0 && $sql_temp_size < DUPLICATOR_MIN_SIZE_DBFILE_WITH_FILTERS))
        ) {
            $this->build_progress->failed = true;
            $error_text                   = "ERROR: SQL file not complete.
                The file looks too small ($sql_temp_size bytes) or the end of file marker was not found.";
            $system_global                = SystemGlobalEntity::getInstance();
            if ($this->Database->DBMode == 'MYSQLDUMP') {
                $fix_text = __('Click button to switch database engine to PHP', 'duplicator-pro');
                $system_global->addQuickFix(
                    $error_text,
                    $fix_text,
                    [
                        'global' => [
                            'package_mysqldump'          => 0,
                            'package_mysqldump_qrylimit' => 32768,
                        ],
                    ]
                );
            } else {
                $fix_text = __('Click button to switch database engine to MySQLDump', 'duplicator-pro');
                $system_global->addQuickFix($error_text, $fix_text, [
                    'global' => [
                        'package_mysqldump'      => 1,
                        'package_mysqldump_path' => '',
                    ],
                ]);
            }
            DupLog::error("$error_text  **RECOMMENDATION: $fix_text", '');
            throw new Exception($error_text);
        }

        DupLog::info("SQL FILE: {$sql_easy_size}");
        //------------------------
        //INSTALLER CHECK:
        $exe_temp_path = SnapIO::safePath(DUPLICATOR_SSDIR_PATH_TMP . '/' . $this->Installer->getInstallerLocalName());
        $exe_temp_size = @filesize($exe_temp_path);
        $exe_easy_size = SnapString::byteSize($exe_temp_size);
        $exe_done_txt  = SnapIO::tailFile($exe_temp_path, 10);
        if (!strstr($exe_done_txt, DUPLICATOR_INSTALLER_EOF_MARKER) && !$this->build_progress->failed) {
            throw new Exception("ERROR: Installer file not complete.  The end of file marker was not found.  Please try to re-create the Backup.");
        }
        DupLog::info("INSTALLER FILE: {$exe_easy_size}");
        //------------------------
        //ARCHIVE CHECK:
        // Only performs check if we were able to obtain the count
        DupLog::trace("Archive file count is " . $this->Archive->file_count);
        if ($this->Archive->file_count != -1) {
            $zip_easy_size = SnapString::byteSize($this->Archive->Size);
            if (!($this->Archive->Size)) {
                throw new Exception("ERROR: The archive file contains no size. Archive Size: {$zip_easy_size}");
            }

            $scan_filepath = DUPLICATOR_SSDIR_PATH_TMP . "/{$this->getNameHash()}_scan.json";
            $json          = '';
            DupLog::trace("***********Does $scan_filepath exist?");
            if (($json = SnapIO::safeFileGetContents($scan_filepath)) === false) {
                $error_message = sprintf(
                    __(
                        "Can't find Scanfile %s. Please ensure there no non-English characters in the Backup or schedule name.",
                        'duplicator-pro'
                    ),
                    $scan_filepath
                );
                throw new Exception($error_message);
            }

            $scanReport         = json_decode($json);
            $expected_filecount = (int) ($scanReport->ARC->UDirCount + $scanReport->ARC->UFileCount);
            DupLog::info("ARCHIVE FILE: {$zip_easy_size} ");
            DupLog::info(sprintf(__('EXPECTED FILE/DIRECTORY COUNT: %1$s', 'duplicator-pro'), number_format($expected_filecount)));
            DupLog::info(sprintf(__('ACTUAL FILE/DIRECTORY COUNT: %1$s', 'duplicator-pro'), number_format($this->Archive->file_count)));
            $this->ExeSize = $exe_easy_size;
            $this->ZipSize = $zip_easy_size;
            /* ------- ZIP Filecount Check -------- */
            // Any zip of over 500 files should be within 2% - this is probably too loose but it will catch gross errors
            DupLog::trace("Expected filecount = $expected_filecount and archive filecount=" . $this->Archive->file_count);
            if ($expected_filecount > 500) {
                $straight_ratio = ($this->Archive->file_count > 0 ? (float) $expected_filecount / (float) $this->Archive->file_count : 0);
                // RSR NEW
                $warning_count = $scanReport->ARC->UnreadableFileCount + $scanReport->ARC->UnreadableDirCount;
                DupLog::trace("Unread counts) unreadfile:{$scanReport->ARC->UnreadableFileCount} unreaddir:{$scanReport->ARC->UnreadableDirCount}");
                $warning_ratio = ((float) ($expected_filecount + $warning_count)) / (float) $this->Archive->file_count;
                DupLog::trace(
                    "Straight ratio is $straight_ratio and warning ratio is $warning_ratio.
                    # Expected=$expected_filecount # Warning=$warning_count and #Archive File {$this->Archive->file_count}"
                );
                // Allow the real file count to exceed the expected by 10% but only allow 1% the other way
                if (($straight_ratio < 0.90) || ($straight_ratio > 1.01)) {
                    // Has to exceed both the straight as well as the warning ratios
                    if (($warning_ratio < 0.90) || ($warning_ratio > 1.01)) {
                        $zip_file_count = $this->Archive->file_count;
                        $error_message  = sprintf(
                            'ERROR: File count in archive vs expected suggests a bad archive (%1$d vs %2$d).',
                            $zip_file_count,
                            $expected_filecount
                        );
                        if ($this->build_progress->current_build_mode == PackageArchive::BUILD_MODE_SHELL_EXEC) {
                            // $fix_text = "Go to: Settings > Packages Tab > Archive Engine to ZipArchive.";
                            $fix_text      = __("Click on button to set archive engine to DupArchive.", 'duplicator-pro');
                            $system_global = SystemGlobalEntity::getInstance();
                            $system_global->addQuickFix(
                                $error_message,
                                $fix_text,
                                [
                                    'global' => ['archive_build_mode' => 3],
                                ]
                            );
                            $error_message .= ' **' . sprintf(__("RECOMMENDATION: %s", 'duplicator-pro'), $fix_text);
                        }

                        DupLog::trace($error_message);
                        throw new Exception($error_message);
                    }
                }
            }
        }
    }




    /**
     * Backup build fail, this method die the process and set the Backup status to error
     *
     * @param string $message Error message
     * @param bool   $die     If true, the process will die
     *
     * @return void
     */
    public function buildFail(string $message, bool $die = true): void
    {
        $this->build_progress->failed = true;
        $this->setStatus(AbstractPackage::STATUS_ERROR);
        $this->postScheduledBuildProcessing(0, false);
        do_action('duplicator_build_fail', $this);
        if ($die) {
            DupLog::errorAndDie($message);
        } else {
            DupLog::error($message);
        }
    }
}
