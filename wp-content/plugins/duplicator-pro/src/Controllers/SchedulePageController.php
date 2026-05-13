<?php

/**
 * Schedule page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\License\LicenseNotices;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Upgrade\UpgradePlugin;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\ScheduleEntity;

class SchedulePageController extends AbstractMenuPageController
{
    const INNER_PAGE_LIST = 'schedules';
    const INNER_PAGE_EDIT = 'edit';

    /*
     * action types
     */
    const ACTION_EDIT_SAVE = 'save';
    const ACTION_EDIT_COPY = 'copy-schedule';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::SCHEDULES_SUBMENU_SLUG;
        $this->pageTitle    = __('Schedule Backups', 'duplicator-pro');
        $this->menuLabel    = __('Schedule Backups', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_SCHEDULE;
        $this->menuPos      = 30;

        add_filter('duplicator_page_actions_' . $this->pageSlug, [$this, 'pageActions']);
        add_filter('duplicator_page_template_data_' . $this->pageSlug, [$this, 'updatePackagePageTitle']);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_action('duplicator_before_render_page_' . $this->pageSlug, [$this, 'beforeRenderPage'], 10, 2);
    }

    /**
     * Return actions for current page
     *
     * @param PageAction[] $actions actions lists
     *
     * @return PageAction[]
     */
    public function pageActions($actions)
    {
        $actions[] = new PageAction(
            self::ACTION_EDIT_SAVE,
            [
                $this,
                'saveSchedule',
            ],
            [$this->pageSlug],
            self::INNER_PAGE_EDIT
        );
        $actions[] = new PageAction(
            self::ACTION_EDIT_COPY,
            [
                $this,
                'copySchedule',
            ],
            [$this->pageSlug],
            self::INNER_PAGE_EDIT
        );
        return $actions;
    }

    /**
     * Set Backup object before render pages
     *
     * @param string[] $currentLevelSlugs current menu slugs
     * @param string   $innerPage         current inner page, empty if not set
     *
     * @return void
     */
    public function beforeRenderPage($currentLevelSlugs, $innerPage): void
    {
        TplMng::getInstance()->setGlobalValue('blur', !License::can(License::CAPABILITY_SCHEDULE));
    }

    /**
     * Set Backup page title
     *
     * @param array<string, mixed> $tplData template global data
     *
     * @return array<string, mixed>
     */
    public function updatePackagePageTitle($tplData)
    {
        switch ($this->getCurrentInnerPage()) {
            case self::INNER_PAGE_EDIT:
                break;
            case self::INNER_PAGE_LIST:
            default:
                $tplData['pageTitle']             =  __('Schedule Backup', 'duplicator-pro');
                $tplData['templateSecondaryPart'] = 'admin_pages/schedules/schedule_create_button';
                break;
        }
        return $tplData;
    }

    /**
     * Save schedule
     *
     * @return array<string, mixed>
     */
    public function saveSchedule(): array
    {
        $result = [
            'saveSuccess'      => false,
            'actionScheduleId' => SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'schedule_id', -1),
        ];

        $schedule_id = $result['actionScheduleId'];
        $schedule    = $schedule_id == -1 ? new ScheduleEntity() : ScheduleEntity::getById($schedule_id);

        if ($schedule == false) {
            $result['errorMessage'] = __('Schedule not found', 'duplicator-pro');
            return $result;
        }

        $schedule->setFromInput(SnapUtil::INPUT_REQUEST);
        if ($schedule->save() == false) {
            $result['errorMessage'] = __('Is not possible to update the schedule settings', 'duplicator-pro');
        } else {
            $result['saveSuccess']    = true;
            $result['successMessage'] = __('Schedule saved', 'duplicator-pro');
        }

        $result['actionScheduleId'] = $schedule->getId();

        return $result;
    }

    /**
     * Save schedule
     *
     * @return array<string, mixed>
     */
    public function copySchedule(): array
    {
        $result = [
            'saveSuccess'      => false,
            'actionScheduleId' => SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'schedule_id', -1),
        ];

        $schedule_id = $result['actionScheduleId'];
        $source_id   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'dupli-source-schedule-id', -1);

        if ($source_id == -1 || !ScheduleEntity::exists($source_id)) {
            $result['errorMessage'] = __('Schedule to copy not found', 'duplicator-pro');
            return $result;
        }

        $schedule = $schedule_id == -1 ? new ScheduleEntity() : ScheduleEntity::getById($schedule_id);

        if ($schedule == false) {
            $result['errorMessage'] = __('Schedule not found', 'duplicator-pro');
            return $result;
        }

        $schedule->copyFromSourceId($source_id);
        if ($schedule->save() == false) {
            $result['errorMessage'] = __('Is not possible to update the schedule settings', 'duplicator-pro');
        } else {
            $result['saveSuccess']    = true;
            $result['successMessage'] = __('Schedule copied.', 'duplicator-pro');
        }

        $result['actionScheduleId'] = $schedule->getId();

        return $result;
    }

    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current menu slugs
     * @param string   $innerPage         current inner page, empty if not set
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs, $innerPage): void
    {
        switch ($this->getCurrentInnerPage()) {
            case self::INNER_PAGE_EDIT:
                $scheduleId = TplMng::getInstance()->getGlobalValue(
                    'actionScheduleId',
                    SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'schedule_id', -1)
                );
                if ($scheduleId == -1) {
                    $schedule = new ScheduleEntity();
                    $schedule->setActive(true); // Active by default
                } else {
                    $schedule = ScheduleEntity::getById($scheduleId);
                }
                TplMng::getInstance()->render(
                    'admin_pages/schedules/schedule_edit',
                    ['schedule' => $schedule]
                );
                break;
            case self::INNER_PAGE_LIST:
            default:
                TplMng::getInstance()->render('admin_pages/schedules/schedule_list');
                break;
        }
    }

    /**
     * Get schedule edit URL
     *
     * @return string
     */
    public function getEditBaseUrl(): string
    {
        return ControllersManager::getMenuLink(
            ControllersManager::SCHEDULES_SUBMENU_SLUG,
            null,
            null,
            [ControllersManager::QUERY_STRING_INNER_PAGE => 'edit']
        );
    }

    /**
     * Get schedule edit URL
     *
     * @param int $scheduleId schedule id, if -1 then new schedule will be created
     *
     * @return string
     */
    public function getEditUrl($scheduleId = -1): string
    {
        return ControllersManager::getMenuLink(
            ControllersManager::SCHEDULES_SUBMENU_SLUG,
            null,
            null,
            [
                ControllersManager::QUERY_STRING_INNER_PAGE => 'edit',
                'schedule_id'                               => $scheduleId,
            ]
        );
    }

    /**
     * Get schedule copy action URL
     *
     * @param int $scheduleId schedule id, if -1 then new schedule will be created
     * @param int $sourceId   source schedule id, if -1 then source if ins't add on URL
     *
     * @return string
     */
    public function getCopyActionUrl($scheduleId = -1, $sourceId = -1): string
    {

        $action    = $this->getActionByKey(self::ACTION_EDIT_COPY);
        $extraArgs = [
            ControllersManager::QUERY_STRING_INNER_PAGE => 'edit',
            'action'                                    => $action->getKey(),
            '_wpnonce'                                  => $action->getNonce(),
            'schedule_id'                               => $scheduleId,
        ];
        if ($sourceId > -1) {
            $extraArgs['dupli-source-schedule-id'] = $sourceId;
        }
        return ControllersManager::getMenuLink(
            ControllersManager::SCHEDULES_SUBMENU_SLUG,
            null,
            null,
            $extraArgs
        );
    }
}
