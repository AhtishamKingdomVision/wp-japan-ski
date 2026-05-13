<?php

namespace Duplicator\Utils\Settings;

use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Models\TemplateEntity;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\Local\DefaultLocalStorage;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Utils\Crypt\CryptCustom;
use Exception;
use stdClass;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

class MigrateSettings
{
    /**
     * Create settings export file
     *
     * @param string $message message to display to user
     *
     * @return false|string false if error, otherwise the export file path
     */
    public static function export(&$message = '')
    {
        $exportData                   = new stdClass();
        $exportData->version          = DUPLICATOR_VERSION;
        $exportData->settings         = GlobalEntity::getInstance()->settingsExport();
        $exportData->dynamic_settings = DynamicGlobalEntity::getInstance()->settingsExport();

        if (($templates = TemplateEntity::getAllWithoutManualMode()) === false) {
            $templates = [];
        }
        $exportData->templates = [];
        foreach ($templates as $template) {
            $exportData->templates[] = $template->settingsExport();
        }

        if (($storages = AbstractStorageEntity::getAll()) === false) {
            $storages = [];
        }
        $exportData->storages = [];
        foreach ($storages as $storage) {
            $exportData->storages[] = $storage->settingsExport();
        }

        if (($schedules = ScheduleEntity::getAll()) === false) {
            $schedules = [];
        }
        $exportData->schedules = [];
        foreach ($schedules as $schedule) {
            $exportData->schedules[] = $schedule->settingsExport();
        }

        $jsonData = JsonSerialize::serialize(
            $exportData,
            JsonSerialize::JSON_SKIP_CLASS_NAME | JSON_PRETTY_PRINT
        );

        if ($jsonData === false) {
            //Isolate the problem area:
            $test           = JsonSerialize::serialize($exportData->templates);
            $test_templates = ($test === false ? '*Fail' : 'Pass');
            $test           = JsonSerialize::serialize($exportData->schedules);
            $test_schedules = ($test === false ? '*Fail' : 'Pass');
            $test           = JsonSerialize::serialize($exportData->storages);
            $test_storages  = ($test === false ? '*Fail' : 'Pass');
            $test           = JsonSerialize::serialize($exportData->settings);
            $test_settings  = ($test === false ? '*Fail' : 'Pass');
            $test           = JsonSerialize::serialize($exportData->schedules);
            $test_settings  = ($test === false ? '*Fail' : 'Pass');

            $exc_msg = 'Isn\'t possible serialize json data';
            $div     = "******************************************";
            $message = <<<ERR
******************************************
DUPLICATOR PRO - EXPORT SETTINGS ERROR
******************************************
Error encoding json data for export status

Templates	= {$test_templates}
Schedules	= {$test_schedules}
Storage		= {$test_storages}
Settings	= {$test_settings}
Security	= {$test_settings}

RECOMMENDATION:
Check the data in the failed areas above to make sure the data is correct.  If the data looks correct consider re-saving the data in
that respective area.  If the problem persists consider removing the items one by one to isolate the setting that is causing the issue.

ERROR DETAILS:\n$exc_msg
ERR;
            DupLog::traceObject('There was an error encoding json data for export', $exportData);
            return false;
        }

        $encryptedData  = CryptCustom::encrypt($jsonData, 'test');
        $exportFilepath = DUPLICATOR_SSDIR_PATH_TMP . '/dupli-export-' . date("Ymdhs") . '.dup';

        if (file_put_contents($exportFilepath, $encryptedData) === false) {
            DupLog::trace("Error writing export to {$exportFilepath}");
            return false;
        }

        $message = __("Export data file has been created!<br/>", 'duplicator-pro');
        return $exportFilepath;
    }

    /**
     * Creates and export file of current settings and then
     * imports all the new settings from an existing import file
     *
     * @param string   $filename The name of the import file to import
     * @param string[] $opts     The options to import templates, schedules, storage, etc.
     * @param string   $message  message to display to user
     *
     *  @return bool true if success, otherwise false
     */
    public static function import($filename, array $opts, &$message = ''): bool
    {
        DupLog::trace('Start Import data options: ' . implode(',', $opts));
        StoragesUtil::getDefaultStorage()->initStorageDirectory();

        // Generate backup of current settings
        $backupSettings = self::export();

        $filepath = $filename;
        if (!file_exists($filepath)) {
            throw new Exception("File {$filepath} does not exist");
        }

        $encrypted_data = file_get_contents($filepath);
        if ($encrypted_data === false) {
            throw new Exception("Error reading {$filepath}");
        }

        $json_data   = CryptCustom::decrypt($encrypted_data, 'test');
        $import_data = JsonSerialize::unserialize($json_data);
        if (!is_array($import_data)) {
            throw new Exception('Problem decoding JSON data');
        }

        DupLog::traceObject('Import data', $import_data);

        if (in_array('schedules', $opts)) {
            $opts[] = 'templates';
            $opts[] = 'storages';
            $opts   = array_unique($opts);
        }

        $version = ($import_data['version'] ?? '0.0.0');

        if (in_array('settings', $opts)) {
            self::importSettings($import_data, $version);
        }

        $template_map = in_array('templates', $opts) ? self::importTemplates($import_data, $version) : [];

        $storage_map = in_array('storages', $opts) ? self::importStorages($import_data, $version) : [];

        if (in_array('schedules', $opts)) {
            $schedule_map = self::importSchedules($import_data, $version, $storage_map, $template_map);
        }

        $message  = __("All data has been successfully imported and updated! <br/>", 'duplicator-pro');
        $message .= sprintf(__('Backup data file has been created here %s', 'duplicator-pro'), $backupSettings) . '<br/>';

        return true;
    }

    /**
     * Import settings
     *
     * @param array<string,mixed> $import_data data to import
     * @param string              $version     version of data
     *
     * @return bool true if success, otherwise false
     */
    private static function importSettings(array $import_data, $version)
    {
        if (!isset($import_data['settings'])) {
            return true;
        }
        DupLog::trace('Import data settings');

        $global = GlobalEntity::getInstance();
        $global->settingsImport($import_data['settings'], $version);

        if (isset($import_data['dynamic_settings'])) {
            $dGlobal = DynamicGlobalEntity::getInstance();
            $dGlobal->settingsImport($import_data['dynamic_settings'], $version);
            $dGlobal->save();
        }

        return $global->save();
    }

    /**
     * Import templates
     *
     * @param array<string,mixed> $import_data data to import
     * @param string              $version     version of data
     *
     * @return int[] return map from old ids and new
     */
    private static function importTemplates(array $import_data, string $version): array
    {
        $map = [];

        if (!isset($import_data['templates']) || !is_array($import_data['templates'])) {
            return $map;
        }

        foreach ($import_data['templates'] as $data) {
            $template = new TemplateEntity();
            $template->settingsImport($data, $version);

            if ($template->is_default) {
                // Don't save default template
                continue;
            }

            if ($template->save() === false) {
                DupLog::traceObject('Error saving template so skip', $template);
                continue;
            }
            $map[$data['id']] = $template->getId();
        }
        return $map;
    }

    /**
     * Import storages
     *
     * @param array<string,mixed> $import_data data to import
     * @param string              $version     version of data
     *
     * @return int[] return map from old ids and new
     */
    private static function importStorages(array $import_data, string $version): array
    {
        $map = [
            DefaultLocalStorage::OLD_VIRTUAL_STORAGE_ID => StoragesUtil::getDefaultStorageId(),
        ];

        if (!isset($import_data['storages']) || !is_array($import_data['storages'])) {
            return $map;
        }

        foreach ($import_data['storages'] as $data) {
            $class = AbstractStorageEntity::getSTypePHPClass($data);
            /** @var AbstractStorageEntity */
            $storage = new $class();
            $storage->settingsImport($data, $version);

            if ($storage->isDefault()) {
                // Don't create new default storage
                $storage = StoragesUtil::getDefaultStorage();
                $storage->settingsImport($data, $version);
            }

            if ($storage->save() === false) {
                DupLog::traceObject('Error saving storage so skip', $storage);
                continue;
            }
            $map[$data['id']] = $storage->getId();
        }
        return $map;
    }

    /**
     * Import schedules
     *
     * @param array<string,mixed> $import_data  data to import
     * @param string              $version      version of data
     * @param int[]               $storage_map  key is source id, value is new id
     * @param int[]               $template_map key is source id, value is new id
     *
     * @return int[] return map from old ids and new
     */
    private static function importSchedules(array $import_data, $version, array $storage_map, array $template_map): array
    {
        $map = [];

        if (!isset($import_data['schedules']) || !is_array($import_data['schedules'])) {
            return $map;
        }

        $extraData = [
            'storage_map'  => $storage_map,
            'template_map' => $template_map,
        ];

        foreach ($import_data['schedules'] as $data) {
            $schedule = new ScheduleEntity();
            $schedule->settingsImport($data, $version, $extraData);

            if ($schedule->save() === false) {
                DupLog::traceObject('Error saving schedule so skip', $schedule);
                continue;
            }
            $map[$data['id']] = $schedule->getId();
        }
        return $map;
    }
}
