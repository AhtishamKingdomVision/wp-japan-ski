<?php

namespace Duplicator\Package\Archive;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\Local\LocalStorage;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\PackageUtils;
use Exception;

class PackageArchiveShellZip
{
    /**
     * Creates the zip file and adds the SQL file to the archive
     *
     * @param AbstractPackage $package The Backup object
     *
     * @return boolean
     */
    public static function create(AbstractPackage $package): bool
    {
        $archive       = $package->Archive;
        $buildProgress = $package->build_progress;
        try {
            if ($archive->isArchiveStarted()) {
                $error_text    = __('Zip process getting killed due to limited server resources.', 'duplicator-pro');
                $fix_text      = __('Click to switch Archive Engine to DupArchive.', 'duplicator-pro');
                $system_global = SystemGlobalEntity::getInstance();
                $system_global->addQuickFix(
                    $error_text,
                    $fix_text,
                    [
                        'global' => ['archive_build_mode' => 3],
                    ]
                );
                DupLog::traceError("$error_text  **RECOMMENDATION: $fix_text");
                if ($buildProgress->retries > 1) {
                    $buildProgress->failed = true;
                    return true;
                } else {
                    $buildProgress->retries++;
                    $package->update();
                }
            }

            PackageUtils::safeTmpCleanup(true);
            $compressDir  = SnapIO::safePathUntrailingslashit($archive->PackDir);
            $zipPath      = SnapIO::safePath("{$package->StorePath}/{$archive->getFileName()}");
            $filterDirs   = empty($archive->FilterDirs) ? 'not set' : rtrim(str_replace(';', "\n\t", $archive->FilterDirs));
            $filterFiles  = empty($archive->FilterFiles) ? 'not set' : rtrim(str_replace(';', "\n\t", $archive->FilterFiles));
            $filterExts   = empty($archive->FilterExts) ? 'not set' : $archive->FilterExts;
            $filterOn     = ($archive->FilterOn) ? 'ON' : 'OFF';
            $scanFilepath = DUPLICATOR_SSDIR_PATH_TMP . "/{$package->getNameHash()}_scan.json";
            // LOAD SCAN REPORT
            try {
                $scanReport = $package->getScanReportFromJson($scanFilepath);
            } catch (Exception $ex) {
                DupLog::trace("Generate scan report failed message: " . $ex->getMessage());
                DupLog::error($ex->getMessage(), '');
                $buildProgress->failed = true;
                return true;
            }

            DupLog::info("\n********************************************************************************");
            DupLog::info("ARCHIVE  Type=ZIP Mode=Shell");
            DupLog::info("********************************************************************************");
            DupLog::info("ARCHIVE DIR:  " . $compressDir);
            DupLog::info("ARCHIVE FILE: " . basename($zipPath));
            DupLog::info("FILTERS: *{$filterOn}*");
            DupLog::info("DIRS:  {$filterDirs}");
            DupLog::info("EXTS:  {$filterExts}");
            DupLog::info("FILES:  {$filterFiles}");
            DupLog::info("----------------------------------------");
            DupLog::info("COMPRESSING");
            DupLog::info("SIZE:\t" . $scanReport->ARC->Size);
            DupLog::info(
                "STATS:\tDirs " . $scanReport->ARC->DirCount . " | Files " .
                    $scanReport->ARC->FileCount . " | Total " . $scanReport->ARC->FullCount
            );
            $archive->setArcvhieStarted();
            $contains_root  = false;
            $exclude_string = '';
            $filterDirs     = $archive->FilterDirsAll;
            $filterExts     = $archive->FilterExtsAll;
            $filterFiles    = $archive->FilterFilesAll;
            // DIRS LIST
            foreach ($filterDirs as $filterDir) {
                if (trim($filterDir) != '') {
                    $relativeFilterDir = SnapIO::getRelativePath($filterDir, $compressDir, false, true);

                    DupLog::trace("Adding relative filter dir $relativeFilterDir for $filterDir relative to $compressDir");
                    if (trim($relativeFilterDir) == '') {
                        $contains_root = true;
                        break;
                    } else {
                        $exclude_string .= ShellZipUtils::customShellArgEscapeSequence($relativeFilterDir) . "**\* ";
                        $exclude_string .= ShellZipUtils::customShellArgEscapeSequence($relativeFilterDir) . " ";
                    }
                }
            }

            //EXT LIST
            foreach ($filterExts as $filterExt) {
                $exclude_string .= "\*.$filterExt ";
            }

            //FILE LIST
            foreach ($filterFiles as $filterFile) {
                if (trim($filterFile) != '') {
                    $relativeFilterFile = SnapIO::getRelativePath($filterFile, $compressDir, false, true);
                    DupLog::trace("Full file=$filterFile relative=$relativeFilterFile compressDir=$compressDir");
                    $exclude_string .= "\"$relativeFilterFile\" ";
                }
            }

            //DB ONLY
            if ($package->isDBOnly()) {
                $contains_root = true;
            }


            if ($contains_root == false) {
                // Only attempt to zip things up if root isn't in there since stderr indicates when it cant do anything
                $storages = AbstractStorageEntity::getAll();
                foreach ($storages as $storage) {
                    if ($storage->getSType() !== LocalStorage::getSType()) {
                        continue;
                    }
                    /** @var LocalStorage $storage */
                    if ($storage->isFilterProtection()) {
                        continue;
                    }
                    $storagePath     = SnapIO::getRelativePath($storage->getLocationString(), $compressDir, false, true);
                    $exclude_string .= "$storagePath**\* ";
                }

                $relativeBackupDir = SnapIO::getRelativePath(DUPLICATOR_SSDIR_PATH, $compressDir, false, true);
                $exclude_string   .= "$relativeBackupDir**\* ";
                $params            = Shell::getCompressionParam($buildProgress->current_build_compression);
                if (strlen($package->Archive->getArchivePassword()) > 0) {
                    $params .= ' --password ' . escapeshellarg($package->Archive->getArchivePassword());
                }
                $params .= ' -rq';

                $command  = 'cd ' . escapeshellarg($compressDir);
                $command .= ' && ' . escapeshellcmd(ShellZipUtils::getShellExecZipPath()) . ' ' . $params . ' ';
                $command .= escapeshellarg($zipPath) . ' ./';
                $command .= " -x $exclude_string 2>&1";
                DupLog::infoTrace("SHELL COMMAND: $command");
                $shellOutput = Shell::runCommandBuffered($command);
                DupLog::trace("After shellzip command");
                if ($shellOutput->getCode() != 0 && !$shellOutput->isEmpty()) {
                    $stderr        = $shellOutput->getOutputAsString();
                    $error_text    = "Error executing shell exec zip: $stderr.";
                    $system_global = SystemGlobalEntity::getInstance();
                    if (SnapString::contains($stderr, 'quota')) {
                        $fix_text = __("Account out of space so purge large files or talk to your host about increasing quota.", 'duplicator-pro');
                        $system_global->addTextFix($error_text, $fix_text);
                    } elseif (SnapString::contains($stderr, 'such file or')) {
                        $fix_text = sprintf(
                            "%s <a href='" . DUPLICATOR_DUPLICATOR_DOCS_URL . "how-to-resolve-zip-format-related-build-issues' target='_blank'>%s</a>",
                            __('See FAQ:', 'duplicator-pro'),
                            __('How to resolve "zip warning: No such file or directory"?', 'duplicator-pro')
                        );
                        $system_global->addTextFix($error_text, $fix_text);
                    } else {
                        $fix_text = __("Click on button to switch to the DupArchive engine.", 'duplicator-pro');
                        $system_global->addQuickFix(
                            $error_text,
                            $fix_text,
                            [
                                'global' => ['archive_build_mode' => 3],
                            ]
                        );
                    }
                    DupLog::error("$error_text  **RECOMMENDATION: $fix_text", '');
                    $buildProgress->failed = true;
                    return true;
                } else {
                    DupLog::trace("Stderr is null");
                }

                $file_count_string = '';
                if (!file_exists($zipPath)) {
                    $file_count_string = sprintf(__('Zip file %s does not exist.', 'duplicator-pro'), $zipPath);
                } elseif (Shell::getExeFilepath('zipinfo') != null) {
                    DupLog::trace("zipinfo exists");
                    $file_count_string = "zipinfo -t '$zipPath'";
                } elseif (Shell::getExeFilepath('unzip') != null) {
                    DupLog::trace("zipinfo doesn't exist so reverting to unzip");
                    $file_count_string = "unzip -l '$zipPath' | wc -l";
                }

                if ($file_count_string != '') {
                    $shellOutput = Shell::runCommandBuffered($file_count_string . ' | awk \'{print $1 }\'');
                    $file_count  = ($shellOutput->getCode() >= 0)
                        ? trim($shellOutput->getOutputAsString())
                        : null;

                    if (is_numeric($file_count)) {
                        // Accounting for the sql and installer back files
                        $archive->file_count = (int) $file_count + 2;
                    } else {
                        $error_text = sprintf(
                            __("Error retrieving file count in Shell Zip %s.", 'duplicator-pro'),
                            $file_count_string
                        );
                        DupLog::trace("Executed file count string of $file_count_string");
                        DupLog::trace($error_text);
                        $fix_text      = __("Click on button to switch to the DupArchive engine.", 'duplicator-pro');
                        $system_global = SystemGlobalEntity::getInstance();
                        $system_global->addQuickFix(
                            $error_text,
                            $fix_text,
                            [
                                'global' => ['archive_build_mode' => 3],
                            ]
                        );
                        DupLog::error("$error_text  **RECOMMENDATION:$fix_text", '');
                        DupLog::trace("$error_text  **RECOMMENDATION:$fix_text");
                        $buildProgress->failed = true;
                        $archive->file_count   = -2;
                        return true;
                    }
                } else {
                    DupLog::trace("zipinfo doesn't exist");
                    // The -1 and -2 should be constants since they signify different things
                    $archive->file_count = -1;
                }
            } else {
                $archive->file_count = 2;
                // Installer bak and database.sql
            }

            DupLog::trace("archive file count from shellzip is $archive->file_count");
            $buildProgress->archive_built = true;
            $buildProgress->retries       = 0;
            $package->update();
            $timerAllEnd = microtime(true);
            $timerAllSum = SnapString::formattedElapsedTime($timerAllEnd, $buildProgress->archive_start_time);
            $zipFileSize = @filesize($zipPath);
            DupLog::info("COMPRESSED SIZE: " . SnapString::byteSize($zipFileSize));
            DupLog::info("ARCHIVE RUNTIME: {$timerAllSum}");
            DupLog::info("MEMORY STACK: " . SnapServer::getPHPMemory());
        } catch (Exception $e) {
            DupLog::errorAndDie("Runtime error in shell exec zip compression.", "Exception: {$e}");
        }

        return true;
    }
}
