<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Package\DupPackage;
use Duplicator\Controllers\SchedulePageController;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StorageAuthInterface;
use Duplicator\Models\Storages\UnknownStorage;
use Duplicator\Package\AbstractPackage;
use Duplicator\Utils\Logging\ErrorHandler;
use Exception;
use Duplicator\Models\ScheduleEntity;

class ServicesStorage extends AbstractAjaxService
{
    const STORAGE_BULK_DELETE   = 1;
    const STORAGE_GET_SCHEDULES = 5;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall("wp_ajax_duplicator_storage_bulk_actions", "bulkActions");
        $this->addAjaxCall('wp_ajax_duplicator_get_storage_details', 'packageStoragesDetails');
        $this->addAjaxCall("wp_ajax_duplicator_storage_test", "testStorage");
        $this->addAjaxCall("wp_ajax_duplicator_auth_storage", "authorizeStorage");
        $this->addAjaxCall("wp_ajax_duplicator_revoke_storage", "revokeStorage");
    }

    /**
     * Storage bulk actions handler
     *
     * @return void
     * @throws \Exception
     */
    public function bulkActions(): void
    {
        ErrorHandler::init();
        check_ajax_referer('duplicator_storage_bulk_actions', 'nonce');

        $json       = [
            'success'   => false,
            'message'   => '',
            'schedules' => [],
        ];
        $isValid    = true;
        $inputData  = filter_input_array(INPUT_POST, [
            'storage_ids' => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => ['default' => false],
            ],
            'perform'     => [
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => ['default' => false],
            ],
        ]);
        $storageIDs = $inputData['storage_ids'];
        $action     = $inputData['perform'];

        if (empty($storageIDs) || in_array(false, $storageIDs) || $action === false) {
            $isValid = false;
        }

        try {
            CapMng::can(CapMng::CAP_STORAGE);

            if (!$isValid) {
                throw new \Exception(__("Invalid Request.", 'duplicator-pro'));
            }

            foreach ($storageIDs as $id) {
                switch ($action) {
                    case self::STORAGE_BULK_DELETE:
                        AbstractStorageEntity::deleteById($id);
                        break;
                    case self::STORAGE_GET_SCHEDULES:
                        foreach (ScheduleEntity::getSchedulesByStorageId($id) as $schedule) {
                            $json["schedules"][] = [
                                "id"            => $schedule->getId(),
                                "name"          => $schedule->name,
                                "hasOneStorage" => count($schedule->storage_ids) <= 1,
                                "editURL"       => SchedulePageController::getInstance()->getEditUrl($schedule->getId()),
                            ];
                        }
                        break;
                    default:
                        throw new \Exception("Invalid action.");
                }
            }
            //SORT_REGULAR allows to do array_unique on multidimensional arrays
            $json["schedules"] = array_unique($json["schedules"], SORT_REGULAR);
            $json["success"]   = true;
        } catch (\Exception $ex) {
            $json['message'] = $ex->getMessage();
        }

        die(json_encode($json));
    }

    /**
     * Test storage connection
     *
     * @return void
     */
    public function packageStoragesDetails(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'packageStoragesDetailsCallback',
            ],
            'duplicator_get_storage_details',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_CREATE
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_get_storage_details
     *
     * @return array<string,mixed>
     */
    public static function packageStoragesDetailsCallback(): array
    {
        $result = [
            'success'           => false,
            'message'           => '',
            'logURL'            => '',
            'storage_providers' => [],
        ];

        try {
            if (($package_id = SnapUtil::sanitizeIntInput(INPUT_POST, 'package_id', -1)) < 0) {
                throw new Exception(__("Invalid Request.", 'duplicator-pro'));
            }

            $package = DupPackage::getById($package_id);
            if ($package == false) {
                throw new Exception(sprintf(__('Unknown Backup %1$d', 'duplicator-pro'), $package_id));
            }

            $providers = [];
            foreach ($package->upload_infos as $upload_info) {
                if ($upload_info->isDownloadFromRemote()) {
                    continue;
                }

                if (($storage = AbstractStorageEntity::getById($upload_info->getStorageId())) === false) {
                    continue;
                }

                $storageInfo              = [];
                $storageInfo["failed"]    = $upload_info->isFailed();
                $storageInfo["cancelled"] = $upload_info->isCancelled();
                $storageInfo["infoHTML"]  = $storage->renderRemoteLocationInfo(
                    $upload_info->isFailed(),
                    $upload_info->isCancelled(),
                    $upload_info->packageExists(),
                    false
                );
                // Newest storage upload infos will supercede earlier attempts to the same storage
                $providers[$upload_info->getStorageId()] = $storageInfo;
            }

            $result['success']           = true;
            $result['message']           = __('Retrieved storage information', 'duplicator-pro');
            $result['logURL']            = $package->getLocalPackageFileURL(AbstractPackage::FILE_TYPE_LOG);
            $result['storage_providers'] = $providers;
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['message'] = $ex->getMessage();
            DupLog::traceError($ex->getMessage());
        }

        return $result;
    }

    /**
     * Test storage connection
     *
     * @return void
     */
    public function testStorage(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'testStorageCallback',
            ],
            'duplicator_storage_test',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Test storage callback
     *
     * @return array<string,mixed>
     */
    public static function testStorageCallback(): array
    {
        $result = [
            'success'     => false,
            'message'     => '',
            'status_msgs' => '',
        ];

        $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0 || ($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message']     = __('Invalid storage', 'duplicator-pro');
            $result['status_msgs'] = __('Invalid storage', 'duplicator-pro');
        } else {
            $result['success']     = $storage->test($result['message']);
            $result['status_msgs'] = $storage->getTestLog();
        }

        return $result;
    }

    /**
     * Authorize storage
     *
     * @return void
     */
    public function authorizeStorage(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'authorizeStorageCallback',
            ],
            'duplicator_auth_storage',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Authorize storage callback
     *
     * @return mixed[]
     */
    public static function authorizeStorageCallback(): array
    {
        $result = [
            'success'      => false,
            'storage_id'   => -1,
            'message'      => '',
            'redirect_url' => '',
        ];

        $currentPage = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'current_page', '');
        $storageId   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0) {
            // New storage
            $intMin      = (PHP_INT_MAX * -1 - 1); // On php 5.6 PHP_INT_MIN don't exists
            $storageType = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_type', $intMin);
            $storage     = AbstractStorageEntity::getNewStorageByType($storageType);
            if ($storage instanceof UnknownStorage) {
                $result['message'] = __('Invalid storage type', 'duplicator-pro');
                return $result;
            }
        } elseif (($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message'] = __('Invalid storage', 'duplicator-pro');
            return $result;
        } else {
            $result['storage_id'] = $storage->getId();
        }

        DupLog::trace("Auth storage: " . $storage->getName() . "[ID:" . $storage->getId() . "] type: " . $storage->getStypeName());
        if (!$storage instanceof StorageAuthInterface) {
            $result['message'] = __('Storage does not support authorization', 'duplicator-pro');
            return $result;
        }

        if ($storage->authorizeFromRequest($result['message'])) {
            if (($result['success'] = $storage->save()) == false) {
                $result['message'] = __('Failed to update storage', 'duplicator-pro');
            }
        }

        // Make sure storage id is set for new storage
        $result['storage_id'] = $storage->getId();

        // Build redirect URL based on current page context
        if ($result['success']) {
            $result['redirect_url'] = self::buildRedirectUrl(
                $currentPage,
                $result['storage_id'],
                $result['message'],
                'dup-auth-message'
            );
        }

        DupLog::trace('Auth result: ' . SnapLog::v2str($result['success']) . ' msg: ' . $result['message']);
        return $result;
    }

    /**
     * Revoke storage
     *
     * @return void
     */
    public function revokeStorage(): void
    {
        AjaxWrapper::json(
            [
                self::class,
                'revokeStorageCallback',
            ],
            'duplicator_revoke_storage',
            SnapUtil::sanitizeTextInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Revoke storage callback
     *
     * @return mixed[]
     */
    public static function revokeStorageCallback(): array
    {
        $result = [
            'success'      => false,
            'message'      => '',
            'redirect_url' => '',
        ];

        $currentPage = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'current_page', '');
        $storageId   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0 || ($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message'] = __('Invalid storage', 'duplicator-pro');
            return $result;
        }

        DupLog::trace("Revoke storage: " . $storage->getName() . "[ID:" . $storage->getId() . "] type: " . $storage->getStypeName());
        if (!$storage instanceof StorageAuthInterface) {
            $result['message'] = __('Storage does not support authorization', 'duplicator-pro');
            DupLog::trace($result['message']);
            return $result;
        }

        if ($storage->revokeAuthorization($result['message'])) {
            if (($result['success'] = $storage->save()) == false) {
                $result['message'] = __('Failed to update storage', 'duplicator-pro');
            }
        }

        // Build redirect URL based on current page context
        if ($result['success']) {
            $result['redirect_url'] = self::buildRedirectUrl(
                $currentPage,
                $storageId,
                $result['message'],
                'dup-revoke-message'
            );
        }

        DupLog::trace('Revoke result: ' . SnapLog::v2str($result['success']) . ' msg: ' . $result['message']);
        return $result;
    }

    /**
     * Build redirect URL based on current page context
     *
     * @param string $currentPage  Current page slug
     * @param int    $storageId    Storage ID
     * @param string $message      Success/error message
     * @param string $messageParam URL parameter name for message
     *
     * @return string Redirect URL
     */
    private static function buildRedirectUrl(
        string $currentPage,
        int $storageId,
        string $message,
        string $messageParam
    ): string {
        // Check if we're on settings page
        if ($currentPage === ControllersManager::SETTINGS_SUBMENU_SLUG) {
            // Settings page: construct current settings URL with message and storage_id
            $params = [];
            if (!empty($message)) {
                $params[$messageParam] = $message;
            }
            $params['dup-storage-id'] = $storageId;

            $url = ControllersManager::getMenuLink(
                ControllersManager::SETTINGS_SUBMENU_SLUG,
                SettingsPageController::L2_SLUG_GENERAL
            );
            $url = add_query_arg($params, $url);
        } else {
            // Storage page: redirect to storage edit with storage_id
            $params = [
                ControllersManager::QUERY_STRING_INNER_PAGE => 'edit',
                'storage_id'                                => $storageId,
            ];
            if (!empty($message)) {
                $params[$messageParam] = $message;
            }
            $url = ControllersManager::getMenuLink(
                ControllersManager::STORAGE_SUBMENU_SLUG,
                SettingsPageController::L2_SLUG_STORAGE,
                null,
                $params
            );
        }

        return $url;
    }
}
