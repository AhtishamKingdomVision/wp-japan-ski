<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Core\CapMng;
use Duplicator\Package\Runner;
use Duplicator\Utils\Logging\ErrorHandler;
use Error;
use Exception;
use stdClass;

class ServicesSchedule extends AbstractAjaxService
{
    const SCHEDULE_BULK_DELETE     = 1;
    const SCHEDULE_BULK_ACTIVATE   = 2;
    const SCHEDULE_BULK_DEACTIVATE = 3;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall('wp_ajax_duplicator_schedule_bulk_action', 'bulkAction');
        $this->addAjaxCall('wp_ajax_duplicator_get_schedule_infos', 'getScheduleInfo');
        $this->addAjaxCall('wp_ajax_duplicator_run_schedule_now', 'runScheduleNow');
    }

    /**
     * Schedule bulk actions
     *
     * @return void
     */
    public function bulkAction(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_schedule_bulk_action', 'nonce');

        $isValid     = true;
        $json        = [
            'success' => true,
            'message' => '',
        ];
        $inputData   = filter_input_array(INPUT_POST, [
            'schedule_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
            'perform'      => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);
        $scheduleIDs = $inputData['schedule_ids'];
        $action      = $inputData['perform'];

        if (empty($scheduleIDs) || in_array(false, $scheduleIDs) || $action === false) {
            $isValid = false;
        }

        try {
            CapMng::can(CapMng::CAP_SCHEDULE);

            if (!$isValid) {
                throw new Exception(__("Invalid Request.", 'duplicator-pro'));
            }

            foreach ($scheduleIDs as $id) {
                switch ($action) {
                    case self::SCHEDULE_BULK_DELETE:
                        ScheduleEntity::deleteById($id);
                        break;
                    case self::SCHEDULE_BULK_ACTIVATE:
                        $schedule = ScheduleEntity::getById($id);
                        if (!$schedule->isValid()) {
                            $json['success']  = false;
                            $json['message'] .= sprintf(
                                __(
                                    'Could not activate schedule "%1$s" because it has no valid storage locations. Please check the storage configurations.',
                                    'duplicator-pro'
                                ),
                                $schedule->name
                            ) . "<br>";
                        } else {
                            $schedule->setActive(true);
                            $schedule->save();
                        }
                        break;
                    case self::SCHEDULE_BULK_DEACTIVATE:
                        $schedule = ScheduleEntity::getById($id);
                        $schedule->setActive(false);
                        $schedule->save();
                        break;
                    default:
                        throw new Exception("Invalid schedule bulk action.");
                }
            }
        } catch (Exception $ex) {
            $json['success'] = false;
            $json['message'] = $ex->getMessage();
        }

        die(json_encode($json));
    }

    /**
     * Get schedule info action
     *
     * { schedule_id, is_running=true|false, last_ran_string}
     *
     * @return void
     */
    public function getScheduleInfo(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_get_schedule_infos', 'nonce');
        CapMng::can(CapMng::CAP_SCHEDULE);
        $schedules      = ScheduleEntity::getAll();
        $schedule_infos = [];

        if (count($schedules) > 0) {
            $package = DupPackage::getNextActive();

            foreach ($schedules as $schedule) {
                $schedule_info = new stdClass();

                $schedule_info->schedule_id     = $schedule->getId();
                $schedule_info->last_ran_string = $schedule->getLastRanString();

                $schedule_info->is_running = $package != null && $package->getScheduleId() == $schedule->getId();

                array_push($schedule_infos, $schedule_info);
            }
        }

        wp_send_json($schedule_infos);
    }

    /**
     * Run schedule action
     *
     * @return void
     */
    public function runScheduleNow(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_run_schedule_now', 'nonce');

        $json = [
            'success' => false,
            'message' => '',
        ];

        try {
            CapMng::can(CapMng::CAP_SCHEDULE);
            $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);

            if ($schedule_id === false) {
                throw new Exception(__("Invalid schedule id", 'duplicator-pro'));
            }

            $schedule = ScheduleEntity::getById($schedule_id);

            if ($schedule == false) {
                DupLog::trace("Attempted to queue up a job for non existent schedule $schedule_id");
                throw new Exception(__("Invalid schedule id", 'duplicator-pro'));
            }

            DupLog::trace("Inserting new Backup for schedule $schedule->name due to manual request");
            // Just inserting it is enough since init() will automatically pick it up and schedule a cron in the near future.
            $schedule->insertNewPackage(true);
            Runner::kickOffWorker();

            $json = [
                'success' => true,
                'message' => '',
            ];
        } catch (Exception | Error $e) {
            $json['success'] = false;
            $json['message'] = $e->getMessage();
        }

        die(json_encode($json));
    }
}
