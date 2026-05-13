<?php

/**
 * Storage page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\Storages\UnknownStorage;
use Exception;

class StoragePageController extends AbstractMenuPageController
{
    const INNER_PAGE_LIST = 'storage';
    const INNER_PAGE_EDIT = 'edit';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::STORAGE_SUBMENU_SLUG;
        $this->pageTitle    = __('Storage', 'duplicator-pro');
        $this->menuLabel    = __('Storage', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_STORAGE;
        $this->menuPos      = 40;

        add_filter('duplicator_page_actions_' . $this->pageSlug, [$this, 'pageActions']);
        add_action('duplicator_after_run_actions_' . $this->pageSlug, [$this, 'pageAfterActions']);
        add_filter('duplicator_page_template_data_' . $this->pageSlug, [$this, 'updatePackagePageTitle']);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_action('duplicator_before_render_page_' . $this->pageSlug, [$this, 'beforeRenderPage'], 10, 2);
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
                $tplData['pageTitle']             =  __('Storage', 'duplicator-pro');
                $tplData['templateSecondaryPart'] = 'admin_pages/storages/storage_create_button';
                break;
        }
        return $tplData;
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
        TplMng::getInstance()->setGlobalValue('blur', !License::can(License::CAPABILITY_STORAGE));
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
            'save',
            [
                $this,
                'actionEditSave',
            ],
            [$this->pageSlug],
            'edit'
        );
        $actions[] = new PageAction(
            'copy-storage',
            [
                $this,
                'actionEditCopyStorage',
            ],
            [$this->pageSlug],
            'edit'
        );
        return $actions;
    }

    /**
     * Return storage edit url
     *
     * @param AbstractStorageEntity $storage storage entity, if is null get new storage URL
     *
     * @return string
     */
    public static function getEditUrl(?AbstractStorageEntity $storage = null): string
    {
        $data = [ControllersManager::QUERY_STRING_INNER_PAGE => 'edit'];
        if ($storage !== null) {
            $data['storage_id'] = $storage->getId();
        }
        return ControllersManager::getMenuLink(ControllersManager::STORAGE_SUBMENU_SLUG, null, null, $data);
    }

    /**
     * Return storage defualt edit URL
     *
     * @return string
     */
    public static function getEditDefaultUrl(): string
    {
        return ControllersManager::getMenuLink(
            ControllersManager::STORAGE_SUBMENU_SLUG,
            null,
            null,
            [
                ControllersManager::QUERY_STRING_INNER_PAGE => 'edit',
                'storage_id'                                => StoragesUtil::getDefaultStorageId(),
            ]
        );
    }

    /**
     * Page after actions hook
     *
     * @param bool $isActionCalled true if one actions is called,false if no actions
     *
     * @return void
     */
    public function pageAfterActions($isActionCalled): void
    {
        $tplMng = TplMng::getInstance();
        if ($this->getCurrentInnerPage() == 'edit' && $tplMng->hasGlobalValue('storage_id') == false) {
            $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
            $storage   = ($storageId == -1 ? StoragesUtil::getDefaultNewStorage() : AbstractStorageEntity::getById($storageId));
            if ($storage === false) {
                $storageId = -1;
                $storage   = StoragesUtil::getDefaultNewStorage();
            }

            $tplMng->setGlobalValue('storage_id', $storageId);
            $tplMng->setGlobalValue('storage', $storage);
            $tplMng->setGlobalValue('error_message', null);
            $tplMng->setGlobalValue('success_message', null);
        }
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
        try {
            switch ($this->getCurrentInnerPage()) {
                case self::INNER_PAGE_EDIT:
                    TplMng::getInstance()->render('admin_pages/storages/storage_edit');
                    break;
                case self::INNER_PAGE_LIST:
                default:
                    // I left the global try catch for security but the exceptions should be managed inside the list.
                    TplMng::getInstance()->render('admin_pages/storages/storage_list');
                    break;
            }
        } catch (Exception $e) {
            DupLog::trace("Error while rendering storage: " . $e->getMessage());
            TplMng::getInstance()->render(
                'admin_pages/storages/parts/storage_error',
                ['exception' => $e]
            );
        }
    }

    /**
     * Save storage
     *
     * @return array{storage_id:int,storage:AbstractStorageEntity,error_message:?string,success_message:?string}
     */
    public function actionEditSave(): array
    {
        $error_message   = null;
        $success_message = null;

        $storageId   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        $storageType = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_type', UnknownStorage::getSType());
        $storage     = ($storageId == -1 ? AbstractStorageEntity::getNewStorageByType($storageType) : AbstractStorageEntity::getById($storageId));
        if ($storage === false) {
            $error_message = __('Unable to load storage item', 'duplicator-pro');
        }
        $message = '';

        if ($storage->updateFromHttpRequest($message) === false) {
            $error_message = $message;
            DupLog::trace('Storage update failed ID:' . $storage->getId() . ' Type:' . $storage->getStypeName() . ' Message:' . $message);
        } elseif ($storage->save() === false) {
            $error_message   = __('Unable to save storage item', 'duplicator-pro');
            $success_message = '';
            DupLog::trace('Storage save failed ID:' . $storage->getId() . ' Type:' . $storage->getStypeName());
        } else {
            DupLog::trace('Storage updated successfully ID:' . $storage->getId() . ' Type:' . $storage->getStypeName());
            $success_message = $message;
        }

        return [
            "storage_id"      => $storageId,
            "storage"         => $storage,
            "error_message"   => $error_message,
            "success_message" => $success_message,
        ];
    }

    /**
     * Save storage
     *
     * @return array{storage_id:int,storage:AbstractStorageEntity,error_message:?string,success_message:?string}
     */
    public function actionEditCopyStorage(): array
    {
        $error_message   = null;
        $success_message = null;
        $sourceId        = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'dupli-source-storage-id', -1);
        $targetId        = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);

        if (($storage = AbstractStorageEntity::getCopyStorage($sourceId, $targetId)) === false) {
            $error_message = __('Unable to copy storage item', 'duplicator-pro');
            $storage       = AbstractStorageEntity::getById($targetId);
        } elseif ($storage->save() === false) {
            $error_message   = __('Unable to copy storage item', 'duplicator-pro');
            $success_message = '';
            DupLog::trace('Storage save failed ID:' . $storage->getId() . ' Type:' . $storage->getStypeName());
        } else {
            $success_message = __('Storage Copied Successfully.', 'duplicator-pro');
        }

        return [
            "storage_id"      => $targetId,
            "storage"         => $storage,
            "error_message"   => $error_message,
            "success_message" => $success_message,
        ];
    }
}
