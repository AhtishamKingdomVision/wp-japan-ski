<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Ajax\ServicesBrand;
use Duplicator\Ajax\ServicesDashboard;
use Duplicator\Ajax\ServicesImport;
use Duplicator\Ajax\ServicesNotifications;
use Duplicator\Ajax\ServicesPackage;
use Duplicator\Ajax\ServicesRecovery;
use Duplicator\Ajax\ServicesSchedule;
use Duplicator\Ajax\ServicesSettings;
use Duplicator\Ajax\ServicesStorage;
use Duplicator\Ajax\ServicesTools;
use Duplicator\Ajax\ServicesActivityLog;

class AjaxServicesUtils
{
    /**
     * Init ajax hooks
     *
     * @return void
     */
    public static function loadServices(): void
    {
        (new ServicesImport())->init();
        (new ServicesRecovery())->init();
        (new ServicesSchedule())->init();
        (new ServicesStorage())->init();
        (new ServicesDashboard())->init();
        (new ServicesSettings())->init();
        (new ServicesNotifications())->init();
        (new ServicesPackage())->init();
        (new ServicesTools())->init();
        (new ServicesBrand())->init();
        (new ServicesActivityLog())->init();
    }
}
