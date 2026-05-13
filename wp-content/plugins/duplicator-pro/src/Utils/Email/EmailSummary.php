<?php

namespace Duplicator\Utils\Email;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\AbstractPackage;
use Duplicator\Models\GlobalEntity;
use Duplicator\Package\Storage\UploadInfo;
use Duplicator\Models\ScheduleEntity;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitGenericModelSingleton;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Controllers\EmailSummaryPreviewPageController;
use Duplicator\Models\Storages\StoragesUtil;

/**
 * Email Summary
 */
class EmailSummary extends AbstractEntity
{
    use TraitGenericModelSingleton;

    const SEND_FREQ_NEVER   = 'never';
    const SEND_FREQ_DAILY   = 'daily';
    const SEND_FREQ_WEEKLY  = 'weekly';
    const SEND_FREQ_MONTHLY = 'monthly';

    const PREVIEW_SLUG = 'duplicator-email-summary-preview';

    /** @var int[] Manual Backup ids */
    private $manualPackageIds = [];

    /** @var array<array<int, int[]>> Scheduled Backup ids */
    private $scheduledPackageIds = [];

    /** @var int[] Failed Backup ids */
    private $failedPackageIds = [];

    /** @var int[] Array of failed uploads in format [storageId => count] */
    private $failedUploads = [];

    /** @var int[] Array of cancelled uploads in format [storageId => count] */
    private $cancelledUploads = [];

    /** @var int[] Array of successful uploads in format [storageId => count] */
    private $successfulUploads = [];

    /** @var int[] info about created schedules*/
    private $scheduleIds = [];

    /** @var int[] info about created storages*/
    private $storageIds = [];

    /**
     * Constructor
     */
    protected function __construct()
    {
        //do nothing
    }

    /**
     * Returns the summary data
     *
     * @return array{packages: array<mixed>, storages: array<mixed>, schedules: array<mixed>, uploads: array<mixed>}
     */
    public function getData(): array
    {
        return [
            'packages'  => $this->getPackagesInfo(),
            'storages'  => $this->getStoragesInfo(),
            'schedules' => $this->getSchedulesInfo(),
            'uploads'   => $this->getUploadInfo(),
        ];
    }

    /**
     * Returns the preview link
     *
     * @return string
     */
    public static function getPreviewLink()
    {
        return EmailSummaryPreviewPageController::getInstance()->getPageUrl();
    }


    /**
     * Add storage info
     *
     * @param int $storageId Storage id
     *
     * @return void
     */
    public function addStorage($storageId): void
    {
        try {
            if ($storageId === StoragesUtil::getDefaultStorageId()) {
                return;
            }
            $this->storageIds[] = $storageId;
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding storage to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding storage to email summary: " . $e->getMessage());
        }
    }

    /**
     * Remove storage info
     *
     * @param int $storageId Storage id to remove
     *
     * @return void
     */
    public function removeStorage($storageId): void
    {
        try {
            $key = array_search($storageId, $this->storageIds);
            if ($key !== false) {
                array_splice($this->storageIds, $key, 1);
            }
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error removing storage from email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception removing storage from email summary: " . $e->getMessage());
        }
    }

    /**
     * Add schedule info
     *
     * @param ScheduleEntity $schedule Storage entity
     *
     * @return void
     */
    public function addSchedule(ScheduleEntity $schedule): void
    {
        try {
            $this->scheduleIds[] = $schedule->getId();
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding schedule to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding schedule to email summary: " . $e->getMessage());
        }
    }

    /**
     * Remove schedule id
     *
     * @param int $scheduleId Schedule id to remove
     *
     * @return void
     */
    public function removeSchedule($scheduleId): void
    {
        try {
            $key = array_search($scheduleId, $this->scheduleIds);
            if ($key !== false) {
                array_splice($this->scheduleIds, $key, 1);
            }
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error removing schedule from email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception removing schedule from email summary: " . $e->getMessage());
        }
    }

    /**
     * Add Backup id
     *
     * @param AbstractPackage $package The Backup
     *
     * @return void
     */
    public function addPackage(AbstractPackage $package): void
    {
        try {
            if ($package->getScheduleId() > 0) {
                $this->scheduledPackageIds[$package->getScheduleId()][] = $package->getId();
            } else {
                $this->manualPackageIds[] = $package->getId();
            }
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding Backup to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding Backup to email summary: " . $e->getMessage());
        }
    }

    /**
     * Add Backup id
     *
     * @param AbstractPackage $package The Backup
     *
     * @return void
     */
    public function addFailed(AbstractPackage $package): void
    {
        try {
            $this->failedPackageIds[] = $package->getId();
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding failed Backup to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding failed Backup to email summary: " . $e->getMessage());
        }
    }

    /**
     * Add cancelled uploads info
     *
     * @param UploadInfo $uploadInfo The upload info
     *
     * @return void
     */
    public function addCancelledUpload(UploadInfo $uploadInfo): void
    {
        try {
            if (isset($this->cancelledUploads[$uploadInfo->getStorageId()])) {
                $this->cancelledUploads[$uploadInfo->getStorageId()]++;
            } else {
                $this->cancelledUploads[$uploadInfo->getStorageId()] = 1;
            }
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding cancelled upload to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding cancelled upload to email summary: " . $e->getMessage());
        }
    }

    /**
     * Add failed uploads info
     *
     * @param UploadInfo $uploadInfo The upload info
     *
     * @return void
     */
    public function addFailedUpload(UploadInfo $uploadInfo): void
    {
        try {
            if (isset($this->failedUploads[$uploadInfo->getStorageId()])) {
                $this->failedUploads[$uploadInfo->getStorageId()]++;
            } else {
                $this->failedUploads[$uploadInfo->getStorageId()] = 1;
            }
            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding failed upload to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding failed upload to email summary: " . $e->getMessage());
        }
    }

    /**
     * Add successful uploads info
     *
     * @param UploadInfo $uploadInfo The upload info
     *
     * @return void
     */
    public function addSuccessfulUpload(UploadInfo $uploadInfo): void
    {
        try {
            if (!isset($this->successfulUploads[$uploadInfo->getStorageId()])) {
                $this->successfulUploads[$uploadInfo->getStorageId()] = 1;
            } else {
                $this->successfulUploads[$uploadInfo->getStorageId()]++;
            }

            $this->save();
        } catch (\Error $e) {
            DupLog::trace("Error adding successful upload to email summary: " . $e->getMessage());
        } catch (\Exception $e) {
            DupLog::trace("Exception adding successful upload to email summary: " . $e->getMessage());
        }
    }

    /**
     * Returns info about created Backups
     *
     * @return array<int|string, array<string, string|int>>
     */
    private function getPackagesInfo(): array
    {
        $packagesInfo = [];
        foreach ($this->scheduledPackageIds as $scheduleId => $packageIds) {
            if (($scheduleInfo = $this->getSingleScheduleInfo($scheduleId)) === false) {
                $scheduleInfo = [
                    'name'     => __('[Schedule Deleted]', 'duplicator-pro'),
                    'storages' => __('N/A', 'duplicator-pro'),
                ];
            }

            $packagesInfo[] = array_merge($scheduleInfo, ['count' => count($packageIds)]);
        }

        if (count($this->manualPackageIds) > 0) {
            $packagesInfo['manual'] = [
                'name'     => __('Manual', 'duplicator-pro'),
                'storages' => __('N/A', 'duplicator-pro'),
                'count'    => count($this->manualPackageIds),
            ];
        }

        if (count($this->failedPackageIds) > 0) {
            $packagesInfo['failed'] = [
                'name'     => __('Failed', 'duplicator-pro'),
                'storages' => __('N/A', 'duplicator-pro'),
                'count'    => count($this->failedPackageIds),
            ];
        }

        return $packagesInfo;
    }

    /**
     * Returns the info of successful uploads
     *
     * @return array<string|int, array<string, string|int>>
     */
    private function getUploadInfo(): array
    {
        $uploadInfo = [];
        foreach ($this->successfulUploads as $storageId => $count) {
            if (($storageInfo = $this->getSingleStorageInfo($storageId)) === false) {
                continue;
            }

            $uploadInfo[] = [
                'name'  => $storageInfo['name'],
                'count' => (int) $count,
            ];
        }

        if (count($this->failedUploads) > 0) {
            $count                      = array_sum($this->failedUploads);
            $uploadInfo['failedUpload'] = [
                'name'  => __('Failed', 'duplicator-pro'),
                'count' => (int) $count,
            ];
        }

        if (count($this->cancelledUploads) > 0) {
            $count                         = array_sum($this->cancelledUploads);
            $uploadInfo['cancelledUpload'] = [
                'name'  => __('Cancelled', 'duplicator-pro'),
                'count' => (int) $count,
            ];
        }


        return $uploadInfo;
    }

    /**
     * Returns info about created schedules
     *
     * @return array<array{'name': string, 'storages': string}>
     */
    private function getSchedulesInfo(): array
    {
        $schedulesInfo = [];
        foreach ($this->scheduleIds as $scheduleId) {
            if (($scheduleInfo = $this->getSingleScheduleInfo($scheduleId)) === false) {
                DupLog::trace("A Schedule with the ID {$scheduleId} was not found.");
                continue;
            }

            $schedulesInfo[] = $scheduleInfo;
        }

        return $schedulesInfo;
    }

    /**
     * Get schedule info or false if it doesn't exist
     *
     * @param int $scheduleId The schedule id
     *
     * @return array{'name': string, 'storages': string}|false
     */
    private function getSingleScheduleInfo($scheduleId)
    {
        if (($schedule = ScheduleEntity::getById($scheduleId)) === false) {
            return false;
        }

        $result             = [];
        $result['name']     = $schedule->name;
        $result['storages'] = '';
        foreach ($schedule->storage_ids as $i => $storageId) {
            if (($storageInfo = $this->getSingleStorageInfo($storageId)) === false) {
                continue;
            }

            $seperator           = ($i == count($schedule->storage_ids) - 1) ? '' : ', ';
            $result['storages'] .= $storageInfo['name'] . $seperator;
        }

        return $result;
    }

    /**
     * Returns info about created storages
     *
     * @return array<array{'name': string, 'type': string}>
     */
    private function getStoragesInfo(): array
    {
        $storagesInfo = [];
        foreach ($this->storageIds as $storageId) {
            if (($storageInfo = $this->getSingleStorageInfo($storageId)) === false) {
                DupLog::trace("A Storage with the ID {$storageId} was not found.");
                continue;
            }

            $storagesInfo[] = $storageInfo;
        }

        return $storagesInfo;
    }

    /**
     * Get storage info
     *
     * @param int $storageId The storage id
     *
     * @return array{'name': string, 'type': string}|false
     */
    private function getSingleStorageInfo($storageId)
    {
        if (($storage = AbstractStorageEntity::getById($storageId)) === false) {
            return false;
        }

        return [
            'name' => $storage->getName(),
            'type' => $storage->getStypeName(),
        ];
    }

    /**
     * Get default recipient emails
     *
     * @return array<string>
     */
    public static function getDefaultRecipients(): array
    {
        $recipients = [];

        $adminEmail = get_option('admin_email');
        if (!empty($adminEmail)) {
            $recipients[] = $adminEmail;
        }

        return $recipients;
    }

    /**
     * Get default recipient emails
     *
     * @return array<string>
     */
    public static function getRecipientSuggestions(): array
    {
        $recipients = [];
        foreach (self::getDefaultRecipients() as $recipient) {
            if (in_array($recipient, GlobalEntity::getInstance()->getEmailSummaryRecipients())) {
                continue;
            }

            $recipients[] = $recipient;
        }

        return $recipients;
    }

    /**
     * Get all frequency options
     *
     * @return array<string, string>
     */
    public static function getAllFrequencyOptions(): array
    {
        return [
            self::SEND_FREQ_NEVER   => esc_html__('Never', 'duplicator-pro'),
            self::SEND_FREQ_DAILY   => esc_html__('Daily', 'duplicator-pro'),
            self::SEND_FREQ_WEEKLY  => esc_html__('Weekly', 'duplicator-pro'),
            self::SEND_FREQ_MONTHLY => esc_html__('Monthly', 'duplicator-pro'),
        ];
    }

    /**
     * Get the frequency text displayed in the email
     *
     * @return string
     */
    public static function getFrequencyText()
    {
        switch (GlobalEntity::getInstance()->getEmailSummaryFrequency()) {
            case self::SEND_FREQ_DAILY:
                return esc_html__('day', 'duplicator-pro');
            case self::SEND_FREQ_MONTHLY:
                return esc_html__('month', 'duplicator-pro');
            case self::SEND_FREQ_WEEKLY:
            default:
                return esc_html__('week', 'duplicator-pro');
        }
    }

    /**
     * Save email summary data
     *
     * @return bool True on success, or false on error.
     */
    public function save(): bool
    {
        try {
            return parent::save();
        } catch (\Error $e) {
            DupLog::trace("Error saving email summary info: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            DupLog::trace("Exception saving email summary info: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'EmailSummary';
    }
}
