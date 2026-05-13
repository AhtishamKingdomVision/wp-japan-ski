<?php

namespace Duplicator\Package;

use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Models\BrandEntity;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\PackageUtils;
use Duplicator\Utils\Logging\DupLog;
use Error;
use Exception;

/**
 * Class used to store and process all Backup logic
 *
 * @package Dupicator\classes
 */
class DupPackage extends AbstractPackage
{
    /**
     * Get backup type
     *
     * @return string
     */
    public static function getBackupType(): string
    {
        return PackageUtils::DEFAULT_BACKUP_TYPE;
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
        if ($this->schedule_id == -1) {
            return;
        }

        parent::postScheduledBuildProcessing($stage, $success, $tests);

        try {
            $this->sendBuildEmail($stage, $success);
        } catch (Exception $ex) {
            DupLog::trace($ex->getMessage());
        }
    }

    /**
     * Check is Brand is properly prepered
     *
     * @return array<string,mixed>
     */
    public static function isActiveBrandPrepared(): array
    {
        $manual_template = TemplateEntity::getManualTemplate();
        $brand           = BrandEntity::getByIdOrDefault((int) $manual_template->installer_opts_brand);
        if (is_array($brand->attachments)) {
            $attachments = count($brand->attachments);
            $exists      = [];
            if ($attachments > 0) {
                $installer = DUPLICATOR____PATH . '/installer/dup-installer/assets/images/brand';
                if (file_exists($installer) && is_dir($installer)) {
                    foreach ($brand->attachments as $attachment) {
                        if (file_exists("{$installer}{$attachment}")) {
                            $exists[] = "{$installer}{$attachment}";
                        }
                    }
                }
            }
            //return ($attachments == count($exists));

            return [
                'LogoAttachmentExists' => ($attachments > 0),
                'LogoCount'            => $attachments,
                'LogoFinded'           => count($exists),
                'LogoImageExists'      => ($attachments == count($exists)),
                'LogoImages'           => $exists,
                'Name'                 => $brand->name,
                'Notes'                => $brand->notes,
            ];
        }


        return [
            'LogoAttachmentExists' => false,
            'LogoCount'            => 0,
            'LogoFinded'           => 0,
            'LogoImageExists'      => true,
            'LogoImages'           => [],
            'Name'                 => __('Default', 'duplicator-pro'),
            'Notes'                => __('The default content used when a brand is not defined.', 'duplicator-pro'),
        ];
    }

    /**
     * Processes the Backup after the build
     *
     * @param int  $stage   0 for failure at build, 1 for failure during storage phase
     * @param bool $success true if build was successful
     *
     * @return void
     */
    protected function sendBuildEmail(int $stage, bool $success): void
    {
        try {
            if ($this->buildEmailSent) {
                return;
            }

            $global = GlobalEntity::getInstance();
            switch ($global->send_email_on_build_mode) {
                case GlobalEntity::EMAIL_BUILD_MODE_NEVER:
                    return;
                case GlobalEntity::EMAIL_BUILD_MODE_ALL:
                    break;
                case GlobalEntity::EMAIL_BUILD_MODE_FAILURE:
                    if ($success) {
                        return;
                    }
                    break;
                default:
                    return;
            }

            $to = !empty($global->notification_email_address) ? $global->notification_email_address : get_option('admin_email');
            if (empty($to) !== false) {
                throw new Exception("Would normally send a build notification but admin email is empty.");
            }

            if (($schedule = $this->getSchedule()) === null) {
                throw new Exception("Couldn't get schedule by ID {$this->schedule_id} to start post scheduled build processing.");
            }

            DupLog::trace("Attempting to send build notification to $to");
            $data = [
                'success'      => $success,
                'messageTitle' => __('BACKUP SUCCEEDED', 'duplicator-pro'),
                'packageID'    => $this->getId(),
                'packageName'  => $this->getName(),
                'scheduleName' => $schedule->name,
                'storageNames' => array_map(fn(AbstractStorageEntity $s): string => $s->getName(), $this->getStorages()),
                'packagesLink' => ControllersManager::getMenuLink(ControllersManager::PACKAGES_SUBMENU_SLUG, null, null, [], false),
                'logExists'    => file_exists($this->getSafeLogFilepath()),
            ];
            if ($success) {
                $data    = array_merge($data, [
                    'fileCount'   => $this->Archive->FileCount,
                    'packageSize' => SnapString::byteSize($this->Archive->Size),
                    'tableCount'  => $this->Database->info->tablesFinalCount,
                    'sqlSize'     => SnapString::byteSize($this->Database->Size),
                ]);
                $subject = sprintf(__('Backup of %1$s (%2$s) Succeeded', 'duplicator-pro'), home_url(), $schedule->name);
            } else {
                $data['messageTitle']  = __('BACKUP FAILED', 'duplicator-pro') . ' ';
                $data['messageTitle'] .= $stage === 0
                    ? __('DURING BUILD PHASE', 'duplicator-pro')
                    : __('DURING STORAGE PHASE. CHECK SITE FOR DETAILS.', 'duplicator-pro');
                $subject               = sprintf(__('Backup of %1$s (%2$s) Failed', 'duplicator-pro'), home_url(), $schedule->name);
            }

            $message     = \Duplicator\Core\Views\TplMng::getInstance()->render("mail/scheduled-build", $data, false);
            $attachments = $data['logExists'] ? $this->getSafeLogFilepath() : '';

            if (!wp_mail($to, $subject, $message, ['Content-Type: text/html; charset=UTF-8'], $attachments)) {
                throw new Exception("Problem sending build notification to {$to} regarding Backup {$this->getId()}");
            }

            $this->buildEmailSent = true;
            $this->save();
            DupLog::trace('wp_mail reporting send success');
        } catch (Exception | Error $ex) {
            DupLog::traceException($ex, "Problem sending build notification email");
        }
    }

    /**
     * Return Backup life
     *
     * @param string $type can be hours,human,timestamp
     *
     * @return int|string Backup life in hours, timestamp or human readable format
     */
    public function getPackageLife($type = 'timestamp')
    {
        $created = strtotime($this->created);
        $current = strtotime(gmdate("Y-m-d H:i:s"));
        $delta   = $current - $created;

        switch ($type) {
            case 'hours':
                return max(0, floor($delta / 60 / 60));
            case 'human':
                return human_time_diff($created, $current);
            case 'timestamp':
            default:
                return $delta;
        }
    }
}
