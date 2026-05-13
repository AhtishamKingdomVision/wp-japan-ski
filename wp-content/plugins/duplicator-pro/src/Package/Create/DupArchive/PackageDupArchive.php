<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package\Create\DupArchive;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Core\Constants;
use Duplicator\Libs\DupArchive\DupArchiveEngine;
use Duplicator\Libs\DupArchive\States\DupArchiveExpandState;
use Duplicator\Libs\Snap\Snap32BitSizeLimitException;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\WpUtils\WpArchiveUtils;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\Create\BuildProgress;
use Duplicator\Package\PackageUtils;
use Exception;

/**
 * Package DupArchive creator
 */
class PackageDupArchive
{
    // Using a worker time override since evidence shorter time works much
    const WORKER_TIME_IN_SEC = 10;

    /**
     *  Creates the zip file and adds the SQL file to the archive
     *
     * @param AbstractPackage $package Package descriptor
     *
     * @return boolean
     */
    public static function create(AbstractPackage $package)
    {
        $archive       = $package->Archive;
        $buildProgress = $package->build_progress;

        try {
            if ($buildProgress->retries > Constants::MAX_BUILD_RETRIES) {
                $error_msg = __('Backup build appears stuck so marking Backup as failed. Is the Max Worker Time set too high?.', 'duplicator-pro');
                DupLog::error(__('Build Failure', 'duplicator-pro'), $error_msg);
                $buildProgress->failed = true;
                return true;
            } else {
                // If all goes well retries will be reset to 0 at the end of this function.
                $buildProgress->retries++;
                $package->update();
            }

            $global = GlobalEntity::getInstance();
            $done   = false;

            DupArchiveEngine::init(new Logger(), WpArchiveUtils::getTargetRootPath());
            PackageUtils::safeTmpCleanup(true);
            $compressDir = SnapIO::safePathUntrailingslashit($archive->PackDir);
            $archivePath = SnapIO::safePath("{$package->StorePath}/{$archive->getFileName()}");
            $filterDirs  = empty($archive->FilterDirs)  ? 'not set' : rtrim(str_replace(';', "\n\t", $archive->FilterDirs));
            $filterFiles = empty($archive->FilterFiles) ? 'not set' : rtrim(str_replace(';', "\n\t", $archive->FilterFiles));
            $filterExts  = empty($archive->FilterExts)  ? 'not set' : $archive->FilterExts;
            $filterOn    = ($archive->FilterOn) ? 'ON' : 'OFF';

            $scanFilepath            = DUPLICATOR_SSDIR_PATH_TMP . "/{$package->getNameHash()}_scan.json";
            $skipArchiveFinalization = false;
            try {
                $scanReport = $package->getScanReportFromJson($scanFilepath, true);
            } catch (Exception $ex) {
                DupLog::trace("Generate scan report failed message: " . $ex->getMessage());
                DupLog::error($ex->getMessage(), '');
                $buildProgress->failed = true;
                return true;
            }

            if (!$archive->isArchiveStarted()) {
                DupLog::info("\n********************************************************************************");
                DupLog::info("ARCHIVE Type=DUP Mode=DupArchive");
                DupLog::info("********************************************************************************");
                DupLog::info("ARCHIVE DIR:  " . $compressDir);
                DupLog::info("ARCHIVE FILE: " . basename($archivePath));
                DupLog::info("FILTERS: *{$filterOn}*");
                DupLog::info("DIRS:  {$filterDirs}");
                DupLog::info("EXTS:  {$filterExts}");
                DupLog::info("FILES:  {$filterFiles}");
                DupLog::info("----------------------------------------");
                DupLog::info("COMPRESSING");
                DupLog::info("SIZE:\t" . $scanReport->ARC->Size);
                DupLog::info(
                    "STATS:\tDirs " . $scanReport->ARC->DirCount .
                        " | Files " . $scanReport->ARC->FileCount .
                        " | Total " . $scanReport->ARC->FullCount
                );
                if (($scanReport->ARC->DirCount == '') || ($scanReport->ARC->FileCount == '') || ($scanReport->ARC->FullCount == '')) {
                    DupLog::error('Invalid Scan Report Detected', 'Invalid Scan Report Detected');
                    $buildProgress->failed = true;
                    return true;
                }

                $archive->setArcvhieStarted();
            }

            try {
                if ($buildProgress->dupCreate == null) {
                    $archiveHeader = DupArchiveEngine::createArchive(
                        $archivePath,
                        $buildProgress->current_build_compression,
                        $package->Archive->getArchivePassword()
                    );

                    $createState = PackageDupArchiveCreateState::createNew(
                        $archiveHeader,
                        $package,
                        $archivePath,
                        $compressDir,
                        self::WORKER_TIME_IN_SEC
                    );
                } else {
                    // DupLog::traceObject('Resumed build_progress', $package->build_progress);
                    $createState = $package->build_progress->dupCreate;
                }

                if ($buildProgress->retries > 1) {
                    // Indicates it had problems before so move into robustness mode
                    $createState->isRobust = true;
                    //$createState->timeSliceInSecs = self::WORKER_TIME_IN_SEC / 2;
                    $createState->save();
                }

                if ($createState->working) {
                    DupArchiveEngine::addItemsToArchive($createState, $scanReport->ARC);
                    if ($createState->isCriticalFailurePresent()) {
                        throw new Exception($createState->getFailureSummary());
                    }

                    $totalFileCount = count($scanReport->ARC->Files);
                    DupLog::trace("Total file count " . $totalFileCount);
                    $progressPercent = SnapUtil::getWorkPercent(
                        AbstractPackage::STATUS_ARCSTART,
                        AbstractPackage::STATUS_ARCVALIDATION,
                        $totalFileCount,
                        $createState->currentFileIndex
                    );

                    $buildProgress->retries = 0;
                    $createState->save();
                    $package->setProgressPercent($progressPercent);
                    $package->update();

                    DupLog::trace(sprintf(
                        "DupArchive build progress - Files: %d/%d | Dirs: %d | Skipped Files: %d | Skipped Dirs: %d",
                        $createState->currentFileIndex,
                        $totalFileCount,
                        $createState->currentDirectoryIndex,
                        $createState->skippedFileCount,
                        $createState->skippedDirectoryCount
                    ));

                    if ($createState->working == false) {
                        // Want it to do the final cleanup work in an entirely new thread so return immediately
                        $skipArchiveFinalization = true;
                        DupLog::trace("Done build phase.");
                    }
                }
            } catch (Snap32BitSizeLimitException $exception) {
                $global = SystemGlobalEntity::getInstance();
                $err    = 'Backup build failure due to building a large Backup on 32 bit PHP.';
                $fix    = sprintf(
                    _x(
                        'Backup build failure due to building a large Backup on 32 bit PHP. Please see %1$sTech docs%2$s for instructions on how to resolve.',
                        '1 and 2 are opening and closing anchor tags',
                        'duplicator-pro'
                    ),
                    '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-file-io-related-build-issues') . '" target="_blank">',
                    '</a>'
                );
                $global->addTextFix($err, $fix);
                $buildProgress->failed = true;
                return true;
            } catch (Exception $ex) {
                $message = __('Problem adding items to archive.', 'duplicator-pro') . ' ' . $ex->getMessage() . "\n" . $ex->getTraceAsString();
                DupLog::error(__('Problems adding items to archive.', 'duplicator-pro'), $message);
                DupLog::traceObject($message . " EXCEPTION:", $ex);
                $buildProgress->failed = true;
                return true;
            }

            //-- Final Wrapup of the Archive
            if ((!$skipArchiveFinalization) && ($createState->working == false)) {
                if (!$buildProgress->installer_built) {
                    $package->Installer->build($buildProgress);

                    $expandState = new PackageDupArchiveExpandState(
                        DupArchiveEngine::getArchiveHeader($archivePath, $package->Archive->getArchivePassword()),
                        $package
                    );

                    $expandState->archivePath            = $archivePath;
                    $expandState->working                = true;
                    $expandState->timeSliceInSecs        = self::WORKER_TIME_IN_SEC;
                    $expandState->basePath               = DUPLICATOR_SSDIR_PATH_TMP . '/validate';
                    $expandState->validateOnly           = true;
                    $expandState->validatiOnType         = DupArchiveExpandState::VALIDATION_STANDARD;
                    $expandState->working                = true;
                    $expandState->expectedDirectoryCount = max(0, (
                        count($scanReport->ARC->Dirs) -
                        $createState->skippedDirectoryCount +
                        $package->Installer->numDirsAdded
                    ));
                    // add index file
                    $expandState->expectedFileCount = max(0, (
                        1 +
                        count($scanReport->ARC->Files) -
                        $createState->skippedFileCount +
                        $package->Installer->numFilesAdded
                    ));
                    $expandState->save();
                } else {
                    // $build_progress->warnings = $createState->getWarnings(); Auto saves warnings within build progress along the way
                    try {
                        $expandState = $buildProgress->dupExpand;
                        if (is_null($expandState)) {
                            throw new Exception('Expand state can\'t be null');
                        }
                        if ($buildProgress->retries > 1) {
                            // Indicates it had problems before so move into robustness mode
                            $expandState->isRobust = true;
                            //$expandState->timeSliceInSecs = self::WORKER_TIME_IN_SEC / 2;
                            $expandState->save();
                        }

                        DupLog::traceObject('Resumed validation expand state', $expandState);
                        DupArchiveEngine::expandArchive($expandState);
                        $totalFileCount  = count($scanReport->ARC->Files);
                        $archiveSize     = (int) filesize($expandState->archivePath);
                        $progressPercent = SnapUtil::getWorkPercent(
                            AbstractPackage::STATUS_ARCVALIDATION,
                            AbstractPackage::STATUS_ARCDONE,
                            $archiveSize,
                            $expandState->archiveOffset
                        );

                        $package->setProgressPercent($progressPercent);
                        $package->update();
                    } catch (Exception $ex) {
                        DupLog::traceError('Exception:' . $ex->getMessage() . ':' . $ex->getTraceAsString());
                        $buildProgress->failed = true;
                        return true;
                    }

                    if ($expandState->isCriticalFailurePresent()) {
                        // Fail immediately if critical failure present - even if havent completed processing the entire archive.
                        DupLog::error(__('Build Failure', 'duplicator-pro'), $expandState->getFailureSummary());
                        $buildProgress->failed = true;
                        return true;
                    } elseif (!$expandState->working) {
                        $buildProgress->archive_built = true;
                        $buildProgress->retries       = 0;
                        $package->update();
                        $timerAllEnd     = microtime(true);
                        $timerAllSum     = SnapString::formattedElapsedTime($timerAllEnd, $buildProgress->archive_start_time);
                        $archiveFileSize = (int) filesize($archivePath);
                        DupLog::info("COMPRESSED SIZE: " . SnapString::byteSize($archiveFileSize));
                        DupLog::info("ARCHIVE RUNTIME: {$timerAllSum}");
                        DupLog::info("MEMORY STACK: " . SnapServer::getPHPMemory());
                        DupLog::info("CREATE WARNINGS: " . $createState->getFailureSummary(false, true));
                        DupLog::info("VALIDATION WARNINGS: " . $expandState->getFailureSummary(false, true));
                        $archive->file_count = max(0, (
                            $expandState->fileWriteCount +
                            $expandState->directoryWriteCount -
                            $package->Installer->numDirsAdded -
                            $package->Installer->numFilesAdded
                        ));
                        $package->update();
                        $done = true;
                        if ($progressPercent == AbstractPackage::STATUS_ARCDONE) {
                            do_action('duplicator_package_after_set_status', $package, AbstractPackage::STATUS_ARCDONE);
                        }
                    } else {
                        $expandState->save();
                    }
                }
            }
        } catch (Exception $ex) {
            // Have to have a catchall since the main system that calls this function is not prepared to handle exceptions
            DupLog::traceError('Top level create Exception:' . $ex->getMessage() . ':' . $ex->getTraceAsString());
            $buildProgress->failed = true;
            return true;
        }

        $buildProgress->retries = 0;
        return $done;
    }
}
