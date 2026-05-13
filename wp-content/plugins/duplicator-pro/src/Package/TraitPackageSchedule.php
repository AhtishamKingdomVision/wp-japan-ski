<?php

/**
 * Trait for package schedule operations
 *
 * @package   Duplicator
 * @copyright (c) 2017, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Package;

use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Exception;

/**
 * Trait TraitPackageSchedule
 *
 * Handles package schedule operations including post-build processing
 * for scheduled backups, schedule retrieval, and failure handling.
 *
 * @phpstan-require-extends AbstractPackage
 */
trait TraitPackageSchedule
{
    /**
     * Get schedule if is set
     *
     * @return ?ScheduleEntity
     */
    public function getSchedule(): ?ScheduleEntity
    {
        if ($this->schedule_id === -1) {
            return null;
        }

        if (($schedule = ScheduleEntity::getById($this->schedule_id)) === false) {
            DupLog::traceBacktrace("No ScheduleEntity found: id {$this->schedule_id}");
            return null;
        }

        return $schedule;
    }

    /**
     * Post scheduled build failure
     *
     * @param array<string, mixed> $tests Tests results
     *
     * @return void
     */
    public function postScheduledBuildFailure(array $tests = []): void
    {
        $this->postScheduledBuildProcessing(0, false, $tests);
    }

    /**
     * Post scheduled storage failure
     *
     * @return void
     */
    public function postScheduledStorageFailure(): void
    {
        $this->postScheduledBuildProcessing(1, false);
    }

    /**
     * Processes the Backup after the build
     *
     * @param int                  $stage   0 for failure at build, 1 for failure during storage phase
     * @param bool                 $success true if build was successful
     * @param array<string, mixed> $tests   Tests results
     *
     * @return void
     */
    protected function postScheduledBuildProcessing(int $stage, bool $success, array $tests = []): void
    {
        try {
            if ($this->schedule_id == -1) {
                return;
            }
            if (($schedule = $this->getSchedule()) === null) {
                throw new Exception("Couldn't get schedule by ID {$this->schedule_id} to start post scheduled build processing.");
            }

            $system_global                  = SystemGlobalEntity::getInstance();
            $system_global->schedule_failed = !$success;
            $system_global->save();
            $schedule->times_run++;
            $schedule->last_run_time   = time();
            $schedule->last_run_status = ($success ? ScheduleEntity::RUN_STATUS_SUCCESS : ScheduleEntity::RUN_STATUS_FAILURE);
            $schedule->save();

            if (!empty($tests) && $tests['RES']['INSTALL'] == 'Fail') {
                $system_global->addQuickFix(
                    __('Backup was cancelled because installer files from a previous migration were found.', 'duplicator-pro'),
                    __(
                        'Click the button to remove all installer files.',
                        'duplicator-pro'
                    ),
                    [
                        'special' => ['remove_installer_files' => 1],
                    ]
                );
            }
        } catch (Exception $ex) {
            DupLog::trace($ex->getMessage());
        }
    }
}
