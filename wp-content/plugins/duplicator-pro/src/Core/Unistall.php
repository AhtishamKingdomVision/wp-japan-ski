<?php

/**
 * Interface that collects the functions of initial duplicator Bootstrap
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core;

use Duplicator\MuPlugin\MuGenerator;
use Duplicator\Utils\ExpireOptions;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Utils\Logging\DupLog;

/**
 * Uninstall class
 */
class Unistall
{
    /**
     * Registrer unistall hoosk
     *
     * @return void
     */
    public static function registreHooks(): void
    {
        if (is_admin()) {
            register_deactivation_hook(DUPLICATOR____FILE, [self::class, 'deactivate']);
        }
    }

    /**
     * Deactivation Hook:
     * Hooked into `register_deactivation_hook`.  Routines used to deactivate the plugin
     * For uninstall see uninstall.php  WordPress by default will call the uninstall.php file
     *
     * @return void
     */
    public static function deactivate(): void
    {
        MigrationMng::renameInstallersPhpFiles();

        //Logic has been added to uninstall.php
        //Force recalculation of next run time on activation
        //see the functionDuplicator\Package\Runner::calculateEarliestScheduleRunTime()
        DupLog::trace("Resetting next run time for active schedules");
        $activeSchedules = ScheduleEntity::getActive();
        ExpireOptions::deleteAll();
        foreach ($activeSchedules as $activeSchedule) {
            $activeSchedule->next_run_time = -1;
            $activeSchedule->save();
        }

        MuGenerator::remove();

        do_action('duplicator_after_deactivation');
    }
}
