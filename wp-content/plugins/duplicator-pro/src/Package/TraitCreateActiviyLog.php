<?php

namespace Duplicator\Package;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Models\ActivityLog\LogEventBackupCreate;
use Duplicator\Models\ActivityLog\LogEventWebsitesScan;
use Exception;
use Throwable;

/**
 * This trait is used to create an activity log for package creation
 */
trait TraitCreateActiviyLog
{
    protected int $mainScanLogId       = 0;
    protected int $mainActivityLogId   = 0;
    protected float $scanTimeStart     = 0;
    protected float $scanTimeEnd       = 0;
    protected float $buildTimeStart    = 0;
    protected float $buildTimeEnd      = 0;
    protected float $dbTimeStart       = 0;
    protected float $dbTimeEnd         = 0;
    protected float $filesTimeStart    = 0;
    protected float $filesTimeEnd      = 0;
    protected float $transferTimeStart = 0;
    protected float $transferTimeEnd   = 0;

    /**
     * Get terminal statuses that indicate backup completion
     *
     * @return int[] Terminal statuses
     */
    private static function getTerminalStatuses(): array
    {
        return [
            AbstractPackage::STATUS_COMPLETE,
            AbstractPackage::STATUS_ERROR,
            AbstractPackage::STATUS_BUILD_CANCELLED,
            AbstractPackage::STATUS_REQUIREMENTS_FAILED,
            AbstractPackage::STATUS_STORAGE_FAILED,
            AbstractPackage::STATUS_STORAGE_CANCELLED,
        ];
    }

    /**
     * Get all execution timing data
     *
     * @return array<string,float> Array of timing values
     */
    public function getExecutionTimingData(): array
    {
        return [
            'scanTimeStart'     => $this->scanTimeStart,
            'scanTimeEnd'       => $this->scanTimeEnd,
            'buildTimeStart'    => $this->buildTimeStart,
            'buildTimeEnd'      => $this->buildTimeEnd,
            'dbTimeStart'       => $this->dbTimeStart,
            'dbTimeEnd'         => $this->dbTimeEnd,
            'filesTimeStart'    => $this->filesTimeStart,
            'filesTimeEnd'      => $this->filesTimeEnd,
            'transferTimeStart' => $this->transferTimeStart,
            'transferTimeEnd'   => $this->transferTimeEnd,
        ];
    }

    /**
     * Update log with current timing data and save
     *
     * @param LogEventBackupCreate $log Log to update
     *
     * @return void
     */
    private function updateLogTimingAndSave(LogEventBackupCreate $log): void
    {
        $timingData = $this->getExecutionTimingData();
        $log->updateTimingData($timingData);
        $log->save();
    }

    /**
     * Add log event
     *
     * @param int $previousStatus Previous status ENUM AbstractPackage::STATUS_*
     *
     * @return bool True if the log event was added, false otherwise
     */
    protected function addLogEvent(int $previousStatus): bool
    {
        if (!$this instanceof AbstractPackage) {
            throw new Exception('This method can only be called on an instance of AbstractPackage');
        }

        try {
            $onScan = in_array($previousStatus, [
                AbstractPackage::STATUS_PRE_PROCESS,
                AbstractPackage::STATUS_SCANNING,
                AbstractPackage::STATUS_AFTER_SCAN,
            ]);

            switch ($this->getStatus()) {
                case AbstractPackage::STATUS_ERROR:
                    if ($onScan) {
                        if ($this->addScanLogEvent() == false) {
                            throw new Exception('Error adding scan log event');
                        }
                    } else {
                        if ($this->addBuildLogEvent() == false) {
                            throw new Exception('Error adding build log event');
                        }
                    }
                    break;
                case AbstractPackage::STATUS_PRE_PROCESS:
                case AbstractPackage::STATUS_SCANNING:
                    $this->mainScanLogId = 0;
                    // Continue with the next status
                case AbstractPackage::STATUS_SCAN_VALIDATION:
                case AbstractPackage::STATUS_AFTER_SCAN:
                    if ($this->addScanLogEvent() == false) {
                        throw new Exception('Error adding scan log event');
                    }
                    break;
                case AbstractPackage::STATUS_REQUIREMENTS_FAILED:
                case AbstractPackage::STATUS_STORAGE_FAILED:
                case AbstractPackage::STATUS_STORAGE_CANCELLED:
                case AbstractPackage::STATUS_PENDING_CANCEL:
                case AbstractPackage::STATUS_BUILD_CANCELLED:
                case AbstractPackage::STATUS_START:
                case AbstractPackage::STATUS_DBSTART:
                case AbstractPackage::STATUS_DBDONE:
                case AbstractPackage::STATUS_ARCSTART:
                case AbstractPackage::STATUS_ARCVALIDATION:
                case AbstractPackage::STATUS_ARCDONE:
                case AbstractPackage::STATUS_COPIEDPACKAGE:
                case AbstractPackage::STATUS_STORAGE_PROCESSING:
                case AbstractPackage::STATUS_COMPLETE:
                    if ($this->addBuildLogEvent() == false) {
                        throw new Exception('Error adding build log event');
                    }
                    break;
                default:
                    throw new Exception('Invalid status: ' . $this->getStatus());
            }
            return true;
        } catch (Throwable $e) {
            DupLog::traceException($e, 'Error adding log event');
            return false;
        }
    }

    /**
     * Add scan log event
     *
     * @return bool True if the log event was added, false otherwise
     */
    protected function addScanLogEvent(): bool
    {
        if (!$this instanceof AbstractPackage) {
            throw new Exception('This method can only be called on an instance of AbstractPackage');
        }

        $statusesToLog = [
            AbstractPackage::STATUS_SCANNING,
            AbstractPackage::STATUS_AFTER_SCAN,
            AbstractPackage::STATUS_ERROR,
        ];

        if (!in_array($this->getStatus(), $statusesToLog)) {
            return true;
        }

        $updateMainScanLogId = ($this->mainScanLogId === 0);
        switch ($this->getStatus()) {
            case AbstractPackage::STATUS_SCANNING:
                $this->scanTimeStart = microtime(true);
                $status              = LogEventWebsitesScan::SUB_TYPE_START;
                break;
            case AbstractPackage::STATUS_AFTER_SCAN:
                $this->scanTimeEnd = microtime(true);
                $status            = LogEventWebsitesScan::SUB_TYPE_END;
                break;
            case AbstractPackage::STATUS_ERROR:
            default:
                $this->scanTimeEnd = microtime(true);
                $status            = LogEventWebsitesScan::SUB_TYPE_ERROR;
                break;
        }

        $activityLog = new LogEventWebsitesScan($this, $status, $this->mainScanLogId);
        if ($activityLog->save() == false) {
            return false;
        }
        if ($updateMainScanLogId) {
            $this->mainScanLogId = $activityLog->getId();
        } else {
            // Update the parent scan log with current timing data and severity
            $mainLog = LogEventWebsitesScan::getById($this->mainScanLogId);
            if ($mainLog instanceof LogEventWebsitesScan) {
                // Update severity if needed
                if ($activityLog->getSeverity() > $mainLog->getSeverity()) {
                    $mainLog->setSeverity($activityLog->getSeverity());
                }

                // Update parent log with latest timing data
                $timingData = $this->getExecutionTimingData();
                $mainLog->updateTimingData($timingData);
                $mainLog->save();
            }
        }
        return true;
    }

    /**
     * Method to add a log event
     *
     * @return bool True if the log event was added, false otherwise
     */
    protected function addBuildLogEvent(): bool
    {
        if (!$this instanceof AbstractPackage) {
            throw new Exception('This method can only be called on an instance of AbstractPackage');
        }

        switch ($this->getStatus()) {
            case AbstractPackage::STATUS_REQUIREMENTS_FAILED:
            case AbstractPackage::STATUS_STORAGE_FAILED:
            case AbstractPackage::STATUS_STORAGE_CANCELLED:
            case AbstractPackage::STATUS_BUILD_CANCELLED:
            case AbstractPackage::STATUS_ERROR:
                $this->buildTimeEnd = microtime(true);
                // Close any open phase timers
                $phaseTimers = [
                    [
                        'dbTimeStart',
                        'dbTimeEnd',
                    ],
                    [
                        'filesTimeStart',
                        'filesTimeEnd',
                    ],
                    [
                        'transferTimeStart',
                        'transferTimeEnd',
                    ],
                ];
                foreach ($phaseTimers as [$startField, $endField]) {
                    if ($this->$startField > 0) {
                        $this->$endField = $this->buildTimeEnd;
                    }
                }
                break;
            case AbstractPackage::STATUS_START:
                $this->buildTimeStart = microtime(true);
                break;
            case AbstractPackage::STATUS_DBSTART:
                $this->dbTimeStart = microtime(true);
                break;
            case AbstractPackage::STATUS_ARCSTART:
                $this->dbTimeEnd = $this->filesTimeStart = microtime(true);
                break;
            case AbstractPackage::STATUS_STORAGE_PROCESSING:
                $this->filesTimeEnd = $this->transferTimeStart = microtime(true);
                break;
            case AbstractPackage::STATUS_COMPLETE:
                $this->buildTimeEnd = $this->transferTimeEnd = microtime(true);
                break;
            default:
                // Don't log other status
                return true;
        }

        $updateMainActivityLogId = ($this->mainActivityLogId === 0);

        // Before creating a new child log, update the previous child log with current timing data
        if ($this->mainActivityLogId > 0) {
            // Use getChildLogsByParentId() for internal operations (no capability filtering)
            $recentChildLogs = LogEventBackupCreate::getChildLogsByParentId(
                $this->mainActivityLogId,
                'DESC',
                1
            );

            if (!empty($recentChildLogs)) {
                $previousChildLog = $recentChildLogs[0];
                // Update with latest timing data from package
                $this->updateLogTimingAndSave($previousChildLog);
            }
        }

        $activityLog = new LogEventBackupCreate($this, $this->mainActivityLogId);

        if ($activityLog->save() == false) {
            return false;
        }

        if ($updateMainActivityLogId) {
            // Update the main activity log id only if it is not already set
            $this->mainActivityLogId = $activityLog->getId();
        } else {
            // Update the parent log with current timing data and severity
            $mainLog = LogEventBackupCreate::getById($this->mainActivityLogId);
            if ($mainLog instanceof LogEventBackupCreate) {
                // Update severity if needed
                if ($activityLog->getSeverity() > $mainLog->getSeverity()) {
                    $mainLog->setSeverity($activityLog->getSeverity());
                }

                // Update parent log with latest timing data
                $this->updateLogTimingAndSave($mainLog);
            }

            // Update parent log with final data when backup reaches terminal state
            if (in_array($this->getStatus(), self::getTerminalStatuses()) && $mainLog instanceof LogEventBackupCreate) {
                $mainLog->updateFinalData();
            }
        }
        return true;
    }
}
