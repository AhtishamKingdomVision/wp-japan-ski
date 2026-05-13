<?php

namespace Duplicator\Controllers;

use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Package\Recovery\RecoveryPackage;
use Error;
use Exception;

class RecoveryController
{
    const VIEW_WIDGET_NO_PACKAGE_SET = 'nop';
    const VIEW_WIDGET_NOT_VALID      = 'notvalid';
    const VIEW_WIDGET_VALID          = 'valid';

    /** @var bool */
    protected static $isError = false;
    /** @var string */
    protected static $errorMessage = '';

    /**
     * @return bool check if Backup is disallow from wp-config.php
     */
    public static function isDisallow(): bool
    {
        return (bool) DUPLICATOR_DISALLOW_RECOVERY;
    }

    /**
     *
     * @return string
     */
    public static function getErrorMessage()
    {
        return self::$errorMessage;
    }

    /**
     * Get recovery page link
     *
     * @return string
     */
    public static function getRecoverPageLink(): string
    {
        return ControllersManager::getMenuLink(ControllersManager::TOOLS_SUBMENU_SLUG, ToolsPageController::L2_SLUG_RECOVERY);
    }

    /**
     * Reset recovery point
     *
     * @return bool
     */
    public static function actionResetRecoveryPoint(): bool
    {
        try {
            RecoveryPackage::removeRecoveryFolder();
            RecoveryPackage::setRecoveablePackage(false);
        } catch (Exception | Error $e) {
            self::$isError      = true;
            self::$errorMessage = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Render recovery widget
     *
     * @param array<string, mixed> $options widget options
     * @param bool                 $echo    echo or return
     *
     * @return string
     */
    public static function renderRecoveryWidged($options = [], $echo = true)
    {
        $options = array_merge(
            [
                'details'    => true,
                'selector'   => false,
                'subtitle'   => '',
                'copyLink'   => false,
                'copyButton' => true,
                'launch'     => true,
                'download'   => false,
                'info'       => true,
            ],
            (array) $options
        );

        $recoverPackage    = RecoveryPackage::getRecoverPackage();
        $importFailMessage = '';

        if (!$recoverPackage instanceof RecoveryPackage) {
            $viewMode = self::VIEW_WIDGET_NO_PACKAGE_SET;
        } elseif (!$recoverPackage->isImportable($importFailMessage)) {
            $viewMode = self::VIEW_WIDGET_NOT_VALID;
        } else {
            $viewMode = self::VIEW_WIDGET_VALID;
        }

        return TplMng::getInstance()->render('admin_pages/tools/recovery/widget/recovery-widget', [
            'recoverPackage'      => $recoverPackage,
            'recoverPackageId'    => RecoveryPackage::getRecoverPackageId(),
            'recoverablePackages' => RecoveryPackage::getRecoverablesPackages(),
            'selector'            => $options['selector'],
            'subtitle'            => $options['subtitle'],
            'displayDetails'      => $options['details'],
            'displayCopyLink'     => $options['copyLink'],
            'displayCopyButton'   => $options['copyButton'],
            'displayLaunch'       => $options['launch'],
            'displayDownload'     => $options['download'],
            'displayInfo'         => $options['info'],
            'viewMode'            => $viewMode,
            'importFailMessage'   => $importFailMessage,
        ], $echo);
    }
}
