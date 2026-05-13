<?php

/**
 * Schedule entity
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models;

use DateTime;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitGenericModelList;
use Duplicator\Core\Models\UpdateFromInputInterface;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\ActivityLog\LogEventSchedule;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\DupPackage;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Exception;
use ReflectionClass;
use Throwable;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use VendorDuplicator\Cron\CronExpression;

/**
 * Schedule entity
 */
class ScheduleEntity extends AbstractEntity implements UpdateFromInputInterface, ModelMigrateSettingsInterface
{
    use TraitGenericModelList;

    const RUN_STATUS_SUCCESS = 0;
    const RUN_STATUS_FAILURE = 1;

    const REPEAT_DAILY   = 0;
    const REPEAT_WEEKLY  = 1;
    const REPEAT_MONTHLY = 2;
    const REPEAT_HOURLY  = 3;

    const DAY_MONDAY    = 0b0000001;
    const DAY_TUESDAY   = 0b0000010;
    const DAY_WEDNESDAY = 0b0000100;
    const DAY_THURSDAY  = 0b0001000;
    const DAY_FRIDAY    = 0b0010000;
    const DAY_SATURDAY  = 0b0100000;
    const DAY_SUNDAY    = 0b1000000;

    /** @var string */
    public $name = '';
    /** @var int<-1, max> */
    public $template_id = -1;
    /** @var int<-1, max> */
    protected $start_ticks = 0;
    /** @var int<0, 3> */
    public $repeat_type = self::REPEAT_WEEKLY;
    /** @var bool */
    protected $active = false;
    /** @var int<-1, max> */
    public $next_run_time = -1;
    /** @var int<1, max> */
    public $run_every = 1;
    /** @var int<0, max> bitmask 0 */
    public $weekly_days = 0;
    /** @var int<1, max> */
    public $day_of_month = 1;
    /** @var string */
    public $cron_string = '';
    /** @var int<-1, max> */
    public $last_run_time = -1;
    /** @var int<0, 1> */
    public $last_run_status = self::RUN_STATUS_FAILURE;
    /** @var int<0, max> */
    public $times_run = 0;
    /** @var int[] */
    public $storage_ids = [];

    /**
     * Class contructor
     */
    public function __construct()
    {
        $this->name        = __('New Schedule', 'duplicator-pro');
        $this->storage_ids = [StoragesUtil::getDefaultStorageId()];
        $this->updateCronSchedule();
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Schedule_Entity';
    }

    /**
     * Set active state
     *
     * @param bool $active Active state
     *
     * @return void
     */
    public function setActive(bool $active): void
    {
        if ($active == true && $this->isValid() == false) {
            DupLog::trace("Schedule {$this->name} is not valid, cannot activate.");
            // If not valid force schedule to inactive
            $active = false;
        }

        if ($this->active === $active) {
            return;
        }

        $this->active = $active;
        if ($active) {
            // If the schedule is being activated, update the cron schedule
            $this->updateCronSchedule();
        }
    }

    /**
     * Check if schedule is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Delete schedule
     *
     * @return bool true on success or false on failure
     */
    public function delete(): bool
    {
        $id = $this->id;
        do_action('duplicator_before_schedule_delete', $this);
        if (!parent::delete()) {
            return false;
        }
        do_action('duplicator_after_schedule_delete', $id);

        return true;
    }

    /**
     * Insert new schedule
     *
     * @return bool true on success or false on failure
     */
    public function insert(): bool
    {
        do_action('duplicator_before_schedule_create', $this);
        if (!parent::insert()) {
            return false;
        }
        do_action('duplicator_after_schedule_create', $this);

        return true;
    }

    /**
     * Update cron schedule
     *
     * @return void
     */
    protected function updateCronSchedule()
    {
        $this->buildCronString();
        $this->next_run_time = $this->generateNextRunTime();
    }

    /**
     * Set data from query input
     *
     * @param int $type One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV, SnapUtil::INPUT_REQUEST
     *
     * @return bool true on success or false on failure
     */
    public function setFromInput(int $type): bool
    {
        $input = SnapUtil::getInputFromType($type);

        $this->setFromArrayKey(
            $input,
            function ($key, $val) {
                if (is_string($val)) {
                    $val = stripslashes($val);
                }
                return (is_scalar($val) ? SnapUtil::sanitizeNSChars($val) : $val);
            }
        );

        if (strlen($this->name) == 0) {
            throw new Exception(__('Schedule name can\'t be empty', 'duplicator-pro'));
        }
        $this->template_id = intval($this->template_id);

        if ($this->getTemplate() === false) {
            throw new Exception(__('Invalid template id', 'duplicator-pro'));
        }

        $this->repeat_type  = intval($this->repeat_type);
        $this->day_of_month = intval($this->day_of_month);

        switch ($this->repeat_type) {
            case self::REPEAT_HOURLY:
                $this->run_every = intval($input['_run_every_hours']);
                DupLog::trace("run every hours: " . $input['_run_every_hours']);
                break;
            case self::REPEAT_DAILY:
                $this->run_every = intval($input['_run_every_days']);
                DupLog::trace("run every days: " . $input['_run_every_days']);
                break;
            case self::REPEAT_MONTHLY:
                $this->run_every = intval($input['_run_every_months']);
                DupLog::trace("run every months: " . $input['_run_every_months']);
                break;
            case self::REPEAT_WEEKLY:
                $this->setWeekdaysFromRequest($input);
                break;
        }

        if (isset($input['_storage_ids'])) {
            $this->storage_ids = array_map('intval', $input['_storage_ids']);
        } else {
            $this->storage_ids = [StoragesUtil::getDefaultStorageId()];
        }

        $this->setStartDateTime($input['_start_time']);
        // Checkboxes don't set post values when off so have to manually set these
        $this->setActive(isset($input['_active']));

        // Update cron schedule after all input changes
        $this->updateCronSchedule();

        return true;
    }

    /**
     * To export data
     *
     * @return array<string,mixed>
     */
    public function settingsExport(): array
    {
        return JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport($data, $dataVersion, array $extraData = []): bool
    {
        $storage_map  = ($extraData['storage_map'] ?? []);
        $template_map = ($extraData['template_map'] ?? []);

        $skipProps = [
            'id',
            'last_run_time',
            'next_run_time',
            'times_run',
        ];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            $prop->setValue($this, $data[$prop->getName()]);
        }

        if (isset($template_map[$this->template_id])) {
            $this->template_id = $template_map[$this->template_id];
        }

        for ($i = 0; $i < count($this->storage_ids); $i++) {
            if (isset($storage_map[$this->storage_ids[$i]])) {
                $this->storage_ids[$i] = $storage_map[$this->storage_ids[$i]];
            }
        }

        $this->updateCronSchedule();
        return true;
    }

    /**
     * If it should run, queue up a Backup then update the run time
     *
     * @return void
     */
    public function process(): void
    {
        DupLog::trace("process");
        $now = time();

        if ($this->next_run_time == -1) {
            return;
        }

        if ($this->active && ($this->next_run_time <= $now)) {
            $exception = null;
            try {
                $next_run_time_string = SnapWP::getLocalTimeFromGMTTicks($this->next_run_time);
                $now_string           = SnapWP::getLocalTimeFromGMTTicks($this->next_run_time);

                DupLog::trace("NEXT RUN IS NOW! $next_run_time_string <= $now_string so trying to queue Backup");

                $this->insertNewPackage();

                $this->next_run_time = $this->generateNextRunTime();
                $this->save();

                $next_run_time_string = SnapWP::getLocalTimeFromGMTTicks($this->next_run_time);
                DupLog::trace("******PACKAGE JUST CREATED. UPDATED NEXT RUN TIME TO $next_run_time_string");
            } catch (\Exception | \Error $e) {
                $exception = $e;
            }

            if (!is_null($exception)) {
                $msg  = "Start schedule error " . $exception->getMessage() . "\n";
                $msg .= SnapLog::getTextException($exception);
                SnapUtil::errorLog($msg);
                DupLog::trace($msg);
                $system_global                  = SystemGlobalEntity::getInstance();
                $system_global->schedule_failed = true;
                $system_global->save();
            }
        } else {
            DupLog::trace("active and runtime=$this->next_run_time >= $now");
        }
    }

    /**
     * Copy schedule from id
     *
     * @param int $scheduleId template id
     *
     * @return void
     */
    public function copyFromSourceId(int $scheduleId): void
    {
        if (($source = self::getById($scheduleId)) === false) {
            throw new \Exception('Can\'t get tempalte id' . $scheduleId);
        }

        $skipProps = [
            'id',
            'last_run_time',
            'next_run_time',
            'times_run',
        ];

        $reflect = new \ReflectionClass($this);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            $prop->setValue($this, $prop->getValue($source));
        }

        $this->name = sprintf(__('%1$s - Copy', 'duplicator-pro'), $source->name);
    }

    /**
     * Get new package
     *
     * @param bool $run_now If true the Backup creation is started immediately, otherwise it is scheduled
     *
     * @return DupPackage
     */
    protected function getNewPackage(bool $run_now = false): DupPackage
    {
        $type = ($run_now ? DupPackage::EXEC_TYPE_RUN_NOW : DupPackage::EXEC_TYPE_SCHEDULED);
        return new DupPackage(
            $type,
            $this->storage_ids,
            $this->getTemplate(),
            $this
        );
    }

    /**
     * Create new Backup from schedule, to run
     *
     * @param bool $run_now If true the Backup creation is started immediately, otherwise it is scheduled
     *
     * @return void
     */
    public function insertNewPackage(bool $run_now = false): void
    {
        $global = GlobalEntity::getInstance();

        DupLog::trace("NEW PACKAGE FROM SCHEDULE ID: " . $this->getId() . " Name: " . $this->name);
        DupLog::trace("Archive build mode before calling insert new Backup, build mode:" . $global->getBuildMode());

        // Validate schedule is still valid before creating package
        if (!$this->isValid()) {
            DupLog::trace("Schedule {$this->name} is not valid but continuing with package creation");
        }

        $logEvent = new LogEventSchedule($this);
        $logEvent->save();

        if (($template = $this->getTemplate()) === false) {
            DupLog::traceError("No settings object exists for schedule {$this->name}!");
            return;
        }

        $package = $this->getNewPackage($run_now);
        DupLog::trace('NEW PACKAGE NAME ' . $package->getName());

        //PACKAGE
        $package->notes = sprintf(esc_html_x('Created by schedule %1$s', '%1$s = name of schedule', 'duplicator-pro'), $this->name);

        $system_global = SystemGlobalEntity::getInstance();
        $system_global->clearFixes();
        $system_global->package_check_ts = 0;
        $system_global->save();

        if ($package->save(false) == false) {
            $msg = "Duplicator is unable to insert a Backup record into the database table from schedule {$this->name}.";
            DupLog::trace($msg);
            throw new \Exception($msg);
        }

        DupLog::trace("archive build mode after calling insert new Backup ID = " . $package->getId() . " build mode = " . $global->archive_build_mode);
    }

    /**
     * Get schedule template object or false if don't exists
     *
     * @return false|TemplateEntity
     */
    public function getTemplate()
    {
        $template = $this->template_id > 0 ? TemplateEntity::getById($this->template_id) : null;

        if (!$template instanceof TemplateEntity) {
            return false;
        }

        return $template;
    }

    /**
     * Display HTML info
     *
     * @param bool $isList if true display info for list
     *
     * @return void
     */
    public function recoveableHtmlInfo($isList = false): void
    {
        if (($template = $this->getTemplate()) === false) {
            return;
        }

        TplMng::getInstance()->render('parts/recovery/widget/recoverable-template-info', [
            'template' => $template,
            'schedule' => $this,
            'isList'   => $isList,
        ]);
    }

    /**
     * Update schedule next run time
     *
     * @return bool true on success or false on failure
     */
    public function updateNextRuntime()
    {
        $newTime = $this->generateNextRunTime();
        if ($newTime == $this->next_run_time) {
            return true;
        }
        $this->next_run_time = $newTime;
        return $this->save();
    }

    /**
     * Return the next run time in UTC
     *
     * @return int<-1, max> Next run time in UTC or -1 if inactive
     */
    protected function generateNextRunTime()
    {
        if (!$this->active) {
            return -1;
        }

        $nextMinute = time() + 60; // We look ahead starting from next minute
        $date       = new DateTime();
        $date->setTimestamp($nextMinute + SnapWP::getGMTOffset()); //Add timezone specific offset

        //Get next run time relative to $date
        $nextRunTime = CronExpression::factory($this->cron_string)->getNextRunDate($date)->getTimestamp();

        // Have to negate the offset and add. For instance for az time -7
        // we want the next run time to be 7 ahead in UTC time
        $nextRunTime -= SnapWP::getGMTOffset();

        // Handling DST problem that happens when there is a change of DST between $nextMinute and $nextRunTime.
        // The problem does not happen if manual offset is selected, because in that case there is no DST.
        $timezoneString = SnapWP::getTimeZoneString();
        if ($timezoneString) {
            // User selected particular timezone (not manual offset), so the problem needs to be handled.
            $DST_NextMinute           = SnapWP::getDST($nextMinute);
            $DST_NextRunTime          = SnapWP::getDST($nextRunTime);
            $DST_NextRunTime_HourBack = SnapWP::getDST($nextRunTime - 3600);
            if ($DST_NextMinute && !$DST_NextRunTime) {
                $nextRunTime += 3600; // Move one hour ahead because of DST change
            } elseif (!$DST_NextMinute && $DST_NextRunTime && $DST_NextRunTime_HourBack) {
                $nextRunTime -= 3600; // Move one hour back because of DST change
            }
        }
        return $nextRunTime;
    }

    /**
     * Set week days from input data
     *
     * @param array<string, mixed> $request input data
     *
     * @return void
     */
    protected function setWeekdaysFromRequest(array $request): void
    {
        $weekday = $request['weekday'];
        if (in_array('mon', $weekday)) {
            $this->weekly_days |= self::DAY_MONDAY;
        } else {
            $this->weekly_days &= ~self::DAY_MONDAY;
        }

        if (in_array('tue', $weekday)) {
            $this->weekly_days |= self::DAY_TUESDAY;
        } else {
            $this->weekly_days &= ~self::DAY_TUESDAY;
        }

        if (in_array('wed', $weekday)) {
            $this->weekly_days |= self::DAY_WEDNESDAY;
        } else {
            $this->weekly_days &= ~self::DAY_WEDNESDAY;
        }

        if (in_array('thu', $weekday)) {
            $this->weekly_days |= self::DAY_THURSDAY;
        } else {
            $this->weekly_days &= ~self::DAY_THURSDAY;
        }

        if (in_array('fri', $weekday)) {
            $this->weekly_days |= self::DAY_FRIDAY;
        } else {
            $this->weekly_days &= ~self::DAY_FRIDAY;
        }

        if (in_array('sat', $weekday)) {
            $this->weekly_days |= self::DAY_SATURDAY;
        } else {
            $this->weekly_days &= ~self::DAY_SATURDAY;
        }

        if (in_array('sun', $weekday)) {
            $this->weekly_days |= self::DAY_SUNDAY;
        } else {
            $this->weekly_days &= ~self::DAY_SUNDAY;
        }
    }

    /**
     * Check if day is set
     *
     * @param string $day_string day string
     *
     * @return bool
     */
    public function isDaySet($day_string): bool
    {
        $day_bit = 0;

        switch ($day_string) {
            case 'mon':
                $day_bit = self::DAY_MONDAY;
                break;
            case 'tue':
                $day_bit = self::DAY_TUESDAY;
                break;
            case 'wed':
                $day_bit = self::DAY_WEDNESDAY;
                break;
            case 'thu':
                $day_bit = self::DAY_THURSDAY;
                break;
            case 'fri':
                $day_bit = self::DAY_FRIDAY;
                break;
            case 'sat':
                $day_bit = self::DAY_SATURDAY;
                break;
            case 'sun':
                $day_bit = self::DAY_SUNDAY;
                break;
        }

        return (($this->weekly_days & $day_bit) != 0);
    }

    /**
     * Returns a list of all schedules associated with a storage
     *
     * @param int $storageID The storage id
     *
     * @return self[]
     */
    public static function getSchedulesByStorageId(int $storageID): array
    {
        return array_filter(self::getAll(), fn($schedule): bool => in_array($storageID, $schedule->storage_ids));
    }

    /**
     * Runs the callback on all schedules
     *
     * @param callable $callback The callback function
     *
     * @return void
     */
    public static function runOnAll(callable $callback): void
    {
        foreach (self::getAll() as $schedule) {
            call_user_func($callback, $schedule);
        }
    }

    /**
     * Get active schedule
     *
     * @return self[]
     */
    public static function getActive(): array
    {
        $result = self::getAll(
            0,
            0,
            null,
            fn(self $schedule) => $schedule->active
        );

        return ($result ?: []);
    }

    /**
     * Get stazrt time piece
     *
     * @param int $piece 0 = hour; 1 = minute;
     *
     * @return int
     */
    public function getStartTimePiece(int $piece): int
    {
        switch ($piece) {
            case 0:
                return (int) date('G', $this->start_ticks);
            case 1:
                return (int) date('i', $this->start_ticks);
            default:
                return -1;
        }
    }

    /**
     * Return next run date
     *
     * @return string
     */
    public function getNextRunTimeString(): string
    {
        if ($this->next_run_time == -1) {
            return __('Unscheduled', 'duplicator-pro');
        } else {
            $date_portion   = SnapWP::getDateInWPTimezone(
                get_option('date_format', 'n/j/y') . ' G:i',
                $this->next_run_time
            );
            $repeat_portion = $this->getRepeatText();
            return "$date_portion - $repeat_portion";
        }
    }

    /**
     * Return last run date
     *
     * @return string
     */
    public function getLastRanString(): string
    {
        if ($this->last_run_time == -1) {
            return __('Never Ran', 'duplicator-pro');
        } else {
            $date_portion   = SnapWP::getDateInWPTimezone(
                get_option('date_format', 'n/j/y') . ' G:i',
                $this->last_run_time
            );
            $status_portion = (($this->last_run_status == self::RUN_STATUS_SUCCESS) ? __('Success', 'duplicator-pro') : __('Failed', 'duplicator-pro'));
            return "$date_portion - $status_portion";
        }
    }

    /**
     * Set start time from string date format
     *
     * @param int|string $startTime start time string HH:MM or int 0-23 for hour
     * @param string     $startDate date format
     *
     * @return int return start time
     */
    public function setStartDateTime($startTime, $startDate = '2015/1/1'): int
    {
        if (is_numeric($startTime)) {
            $startTime = sprintf('%02d:00', $startTime);
        }
        $this->start_ticks = (int) strtotime("$startDate $startTime");
        DupLog::trace("start ticks = $this->start_ticks for $startTime $startDate");
        return $this->start_ticks;
    }

    /**
     * Get schedules entity by template id
     *
     * @param int $template_id template id
     *
     * @return self[]
     */
    public static function getByTemplateId(int $template_id): array
    {
        $schedules          = self::getAll();
        $filtered_schedules = [];

        foreach ($schedules as $schedule) {
            if ($schedule->template_id == $template_id) {
                array_push($filtered_schedules, $schedule);
            }
        }

        DupLog::trace("get by template id $template_id schedules = " . count($filtered_schedules));

        return $filtered_schedules;
    }

    /**
     * Return repeat text
     *
     * @return string
     */
    public function getRepeatText(): string
    {
        switch ($this->repeat_type) {
            case self::REPEAT_DAILY:
                return __('Daily', 'duplicator-pro');
            case self::REPEAT_WEEKLY:
                return __('Weekly', 'duplicator-pro');
            case self::REPEAT_MONTHLY:
                return __('Monthly', 'duplicator-pro');
            case self::REPEAT_HOURLY:
                return __('Hourly', 'duplicator-pro');
            default:
                return __('Unknown', 'duplicator-pro');
        }
    }

    /**
     * Build cron string
     *
     * @return void
     */
    public function buildCronString(): void
    {
        $start_hour = $this->getStartTimePiece(0);
        $start_min  = $this->getStartTimePiece(1);

        $run_every_string = $this->run_every == 1 ? '*' : "*/$this->run_every";

        // Generated cron patterns using http://www.cronmaker.com/
        switch ($this->repeat_type) {
            case self::REPEAT_HOURLY:
                $this->cron_string = "$start_min $run_every_string * * *";
                break;
            case self::REPEAT_DAILY:
                $this->cron_string = "$start_min $start_hour $run_every_string * *";
                break;
            case self::REPEAT_WEEKLY:
                $day_of_week_string = $this->getDayOfWeekString();
                $this->cron_string  = "$start_min $start_hour * * $day_of_week_string";

                DupLog::trace("day of week cron string: $this->cron_string");
                break;
            case self::REPEAT_MONTHLY:
                $this->cron_string = "$start_min $start_hour $this->day_of_month $run_every_string *";
                break;
        }

        DupLog::trace("cron string = $this->cron_string");
    }

    /**
     * Return day of weeks list with commad separated
     *
     * @return string
     */
    private function getDayOfWeekString(): string
    {
        $day_array = [];

        DupLog::trace("weekly days=$this->weekly_days");

        if (($this->weekly_days & self::DAY_MONDAY) != 0) {
            $day_array[] = '1';
        }
        if (($this->weekly_days & self::DAY_TUESDAY) != 0) {
            $day_array[] = '2';
        }
        if (($this->weekly_days & self::DAY_WEDNESDAY) != 0) {
            $day_array[] = '3';
        }
        if (($this->weekly_days & self::DAY_THURSDAY) != 0) {
            $day_array[] = '4';
        }
        if (($this->weekly_days & self::DAY_FRIDAY) != 0) {
            array_push($day_array, '5');
        }
        if (($this->weekly_days & self::DAY_SATURDAY) != 0) {
            $day_array[] = '6';
        }
        if (($this->weekly_days & self::DAY_SUNDAY) != 0) {
            $day_array[] = '0';
        }

        return (count($day_array) > 0) ? implode(',', $day_array) : '*';
    }

    /**
     * Check if schedule is valid for activation
     *
     * This method can be extended in the future to include other validation checks
     * beyond storage validation
     *
     * @return bool True if schedule is valid for activation, false otherwise
     */
    public function isValid(): bool
    {
        try {
            return StoragesUtil::hasValidStorage($this->storage_ids, true);
        } catch (Throwable $e) {
            DupLog::traceError("Error checking storages for schedule {$this->name}: " . $e->getMessage());
            return false;
        }
    }
}
