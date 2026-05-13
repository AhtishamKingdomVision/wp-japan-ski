<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Storage\UploadInfo;
use Duplicator\Views\AdminNotices;

class TransferFailureHandler
{
    const OPTION_KEY_FAILED_TRANSFERS = 'dupli_opt_failed_transfers';

    /**
     * Init
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('duplicator_transfer_failed', [self::class, 'addFailedTransfer'], 10, 1);
        add_filter('duplicator_admin_notices', function (array $notices): array {
            $notices[] = [
                self::class,
                'transferFailureNotice',
            ];
            return $notices;
        });
    }

    /**
     * Add transfer failure notice
     *
     * @return void
     */
    public static function transferFailureNotice(): void
    {
        if (!ControllersManager::getInstance()->isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_STORAGE, false) && !CapMng::can(CapMng::CAP_CREATE, false)) {
            return;
        }

        if (($failedTransfers = get_option(self::OPTION_KEY_FAILED_TRANSFERS, [])) === []) {
            return;
        }

        $isAllDownload = true;
        $isAllUpload   = true;
        $storageInfos  = [];
        foreach ($failedTransfers as $transfer) {
            $storage = AbstractStorageEntity::getById($transfer['id']);
            if ($storage === false) {
                continue;
            }

            $storageInfos[] = [
                'name'       => $storage->getName(),
                'type'       => $storage->getStypeName(),
                'isDownload' => $transfer['isDownload'],
            ];

            $isAllDownload = $isAllDownload && $transfer['isDownload'];
            $isAllUpload   = $isAllUpload && !$transfer['isDownload'];
        }

        if (count($storageInfos) === 0) {
            return;
        }

        $message = TplMng::getInstance()->render(
            'admin_pages/storages/storage_transfer_failure_notice',
            [
                'storageInfos'   => $storageInfos,
                'isAllDownload'  => $isAllDownload,
                'isAllUpload'    => $isAllUpload,
                'failureMessage' => self::getTranferFailedMessage($storageInfos, $isAllDownload, $isAllUpload),
            ],
            false
        );

        AdminNotices::displayGeneralAdminNotice(
            $message,
            AdminNotices::GEN_ERROR_NOTICE,
            true,
            ['dupli-quick-fix-notice'],
            [
                'data-to-dismiss' => self::OPTION_KEY_FAILED_TRANSFERS,
            ]
        );
    }

    /**
     * Get transfer failed message
     *
     * @param array{name:string,type:string,isDownload:bool}[] $storageInfos  Storage infos
     * @param bool                                             $isAllDownload If true all downloads failed
     * @param bool                                             $isAllUpload   If true all uploads failed
     *
     * @return string
     */
    protected static function getTranferFailedMessage(array $storageInfos, bool $isAllDownload, bool $isAllUpload): string
    {
        if (count($storageInfos) === 1) {
            $singleDownloadMessage = _x(
                'There was a problem downloading the backup from the storage <b>%1$s</b> <i>(%2$s)</i>.',
                '1: storage name, 2: storage type name',
                'duplicator-pro'
            );
            $singleUploadMessage   = _x(
                'There was a problem uploading the backup to the storage <b>%1$s</b> <i>(%2$s)</i>.',
                '1: storage name, 2: storage type name',
                'duplicator-pro'
            );

            $message = $isAllDownload ? $singleDownloadMessage : $singleUploadMessage;
            $message = sprintf($message, $storageInfos[0]['name'], $storageInfos[0]['type']);
        } else {
            $multiDownloadMessage = __(
                'There was a problem downloading the backup from the following storages:',
                'duplicator-pro'
            );
            $multiUploadMessage   = __(
                'There was a problem uploading the backup to the following storages:',
                'duplicator-pro'
            );
            $genericMessage       = __(
                'There was a backup transfer problem related to the following storages:',
                'duplicator-pro'
            );

            if ($isAllDownload || $isAllUpload) {
                $message = $isAllDownload ? $multiDownloadMessage : $multiUploadMessage;
            } else {
                $message = $genericMessage;
            }
        }

        return $message;
    }

    /**
     * Transfer failed handler
     *
     * @param UploadInfo $uploadInfo The upload info
     *
     * @return void
     */
    public static function addFailedTransfer(UploadInfo $uploadInfo): void
    {
        $failedTransfers = get_option(self::OPTION_KEY_FAILED_TRANSFERS, []);
        if (
            SnapUtil::inArrayExtended(
                $failedTransfers,
                fn ($f) => $f['id'] === $uploadInfo->getStorageId() && $f['isDownload'] === $uploadInfo->isDownloadFromRemote()
            )
        ) {
            return;
        }

        $failedTransfers[] = [
            'id'         => $uploadInfo->getStorageId(),
            'isDownload' => $uploadInfo->isDownloadFromRemote(),
        ];

        update_option(self::OPTION_KEY_FAILED_TRANSFERS, $failedTransfers);
    }
}
