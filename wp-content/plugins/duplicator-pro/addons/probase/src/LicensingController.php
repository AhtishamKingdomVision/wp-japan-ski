<?php

/**
 * Version Pro Base functionalities
 *
 * Name: Duplicator PRO base
 * Version: 1
 * Author: Snap Creek
 * Author URI: http://snapcreek.com
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Addons\ProBase;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\ActivityLog\LogEventLicenseActivation;
use Duplicator\Models\ActivityLog\LogEventLicenseDeactivation;
use Duplicator\Models\ActivityLog\LogEventLicenseKeyCleared;
use Duplicator\Models\ActivityLog\LogEventLicenseVisibilityChanged;
use Duplicator\Models\ActivityLog\LogEventLicenseStatusChanged;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Views\AdminNotices;
use Exception;

class LicensingController
{
    //License actions
    const ACTION_ACTIVATE_LICENSE   = 'activate_license';
    const ACTION_DEACTIVATE_LICENSE = 'deactivate_license';
    const ACTION_CHANGE_VISIBILITY  = 'change_visibility';
    const ACTION_CLEAR_KEY          = 'clear_key';
    const ACTION_FORCE_REFRESH      = 'force_refresh';

    // Legacy license key option
    const LEGACY_LICENSE_KEY_OPTION_AUTO_ACTIVE = 'duplicator_pro_license_auto_active';

    // New license key option
    const LICENSE_KEY_OPTION_AUTO_ACTIVE = 'dupli_opt_auth_token_auto_active';

    /**
     * License controller init
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'licenseAutoActive']);
        add_action('admin_init', [self::class, 'forceUpgradeCheckAction']);
        add_action('duplicator_settings_general_before', [self::class, 'renderLicenseContent'], 10);
        add_action('duplicator_settings_general_before', [self::class, 'renderLicenseVisibility'], 100);
        add_filter('duplicator_page_actions_' . ControllersManager::SETTINGS_SUBMENU_SLUG, [self::class, 'pageActions']);
        add_filter('duplicator_template_file', [self::class, 'getTemplateFile'], 10, 2);
    }

    /**
     * Method call on admin_init hook
     *
     * @return void
     */
    public static function licenseAutoActive(): void
    {
        // Get license key option
        $license_key_option = self::getLicenseKeyOption();

        // Auto activation don't require capabilities check because is a background process
        if (($lKey = get_option($license_key_option, false)) === false) {
            return;
        }
        if (($action = SettingsPageController::getInstance()->getActionByKey(self::ACTION_ACTIVATE_LICENSE)) == false) {
            return;
        }
        delete_option($license_key_option);
        $redirect = $action->getUrl(['_license_key' => $lKey]);

        DupLog::trace("CONTROLLER LICENSE AUTO ACTIVE: Redirecting to " . $action->getUrl());

        if (wp_safe_redirect($redirect)) {
            exit;
        } else {
            throw new Exception(__('Error redirecting to license activation page', 'duplicator-pro'));
        }
    }

    /**
     * Get license key option
     *
     * @return string
     */
    public static function getLicenseKeyOption(): string
    {
        $license_key_option = self::LICENSE_KEY_OPTION_AUTO_ACTIVE;
        if (get_option(self::LEGACY_LICENSE_KEY_OPTION_AUTO_ACTIVE, false) !== false) {
            $license_key_option = self::LEGACY_LICENSE_KEY_OPTION_AUTO_ACTIVE;
        }
        return $license_key_option;
    }

    /**
     * Return force upgrade check URL
     *
     * @return string
     */
    public static function getForceUpgradeCheckURL(): string
    {
        return SnapWP::adminUrl('update-core.php', ['force-check' => 1]);
    }


    /**
     * Force upgrade check action
     *
     * @return void
     */
    public static function forceUpgradeCheckAction(): void
    {
        global $pagenow;

        if ($pagenow !== 'update-core.php') {
            return;
        }

        if (!SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'force-check')) {
            return;
        }

        License::forceUpgradeCheck();
    }

    /**
     * Define actions related to the license
     *
     * @param PageAction[] $actions Page actions array from filter
     *
     * @return PageAction[] Updated page actions array
     */
    public static function pageActions($actions)
    {
        $actions[] = new PageAction(
            self::ACTION_ACTIVATE_LICENSE,
            [
                self::class,
                'activateLicense',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_DEACTIVATE_LICENSE,
            [
                self::class,
                'deactivateLicense',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_CLEAR_KEY,
            [
                self::class,
                'clearLicenseKeyAction',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_CHANGE_VISIBILITY,
            [
                self::class,
                'changeLicenseVisibility',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );
        $actions[] = new PageAction(
            self::ACTION_FORCE_REFRESH,
            [
                self::class,
                'forceRefresh',
            ],
            [ControllersManager::SETTINGS_SUBMENU_SLUG]
        );

        return $actions;
    }

    /**
     * Action that changes the license visibility
     *
     * @return array<string, mixed>
     */
    public static function changeLicenseVisibility(): array
    {
        DupLog::trace("CONTROLLER CHANGE LICENSE VISIBILITY ACTION: Changing license visibility");

        $result  = [
            'license_success' => false,
            'license_message' => '',
        ];
        $dGlobal = DynamicGlobalEntity::getInstance();

        $oldVisibility = $dGlobal->getValInt('license_key_visible', LIcense::VISIBILITY_ALL);
        $newVisibility = filter_input(INPUT_POST, 'license_key_visible', FILTER_VALIDATE_INT);
        $newPassword   = SnapUtil::sanitizeInput(INPUT_POST, '_key_password', '');

        if ($oldVisibility === $newVisibility) {
            return $result;
        }

        switch ($newVisibility) {
            case License::VISIBILITY_ALL:
                if ($dGlobal->getValString('license_key_visible_pwd') !== $newPassword) {
                    $result['license_message'] = __("Wrong password entered. Please enter the correct password.", 'duplicator-pro');
                    return $result;
                }
                $newPassword = ''; // reset password
                break;
            case License::VISIBILITY_NONE:
            case License::VISIBILITY_INFO:
                if ($oldVisibility == License::VISIBILITY_ALL) {
                    $password_confirmation = SnapUtil::sanitizeInput(INPUT_POST, '_key_password_confirmation', '');

                    if (strlen($newPassword) === 0) {
                        $result['license_message'] = __('Password cannot be empty.', 'duplicator-pro');
                        return $result;
                    }

                    if ($newPassword !== $password_confirmation) {
                        $result['license_message'] = __("Passwords don't match.", 'duplicator-pro');
                        return $result;
                    }
                } else {
                    if ($dGlobal->getValString('license_key_visible_pwd') !== $newPassword) {
                        $result['license_message'] = __("Wrong password entered. Please enter the correct password.", 'duplicator-pro');
                        return $result;
                    }
                }
                break;
            default:
                throw new Exception(__('Invalid license visibility value.', 'duplicator-pro'));
        }

        $dGlobal->setValInt('license_key_visible', $newVisibility);
        $dGlobal->setValString('license_key_visible_pwd', $newPassword);

        if ($dGlobal->save()) {
            LogEventLicenseVisibilityChanged::create($oldVisibility, $newVisibility);

            return [
                'license_success' => true,
                'license_message' => __("License visibility changed", 'duplicator-pro'),
            ];
        } else {
            LogEventLicenseVisibilityChanged::create($oldVisibility, $newVisibility, LogEventLicenseVisibilityChanged::SUB_TYPE_ERROR);

            return [
                'license_success' => false,
                'license_message' => __("Couldn't change license visibility.", 'duplicator-pro'),
            ];
        }
    }

    /**
     * Action that clears the license key
     *
     * @return array<string, mixed>
     */
    public static function clearLicenseKeyAction()
    {
        DupLog::trace("CONTROLLER CLEAR LICENSE KEY ACTION: Clearing license key");

        LicenseData::resetLastRequestFailure();
        $result = self::clearLicenseKey();
        LicenseData::resetLastRequestFailure();
        return $result;
    }


    /**
     * Action that clears the license key
     *
     * @param bool $logEvent Whether to log the event
     *
     * @return array<string, mixed>
     */
    protected static function clearLicenseKey($logEvent = true): array
    {
        $dGlobal = DynamicGlobalEntity::getInstance();

        LicenseData::getInstance()->setKey('');
        License::clearVersionCache(true);

        $dGlobal->setValInt('license_key_visible', License::VISIBILITY_ALL);
        $dGlobal->setValString('license_key_visible_pwd', '');

        $result = [];

        if ($dGlobal->save()) {
            $result['license_success'] = true;
            $result['license_message'] = __("License key cleared", 'duplicator-pro');
        } else {
            $result['license_success'] = false;
            $result['license_message'] = __("Couldn't save changes", 'duplicator-pro');
        }

        if ($logEvent) {
            $eventSubType = $result['license_success'] ? LogEventLicenseKeyCleared::SUB_TYPE_SUCCESS : LogEventLicenseKeyCleared::SUB_TYPE_ERROR;
            LogEventLicenseKeyCleared::create($eventSubType);
        }

        return $result;
    }

    /**
     * Action that deactivates the license
     *
     * @return array<string, mixed>
     */
    public static function deactivateLicense(): array
    {
        $result = [
            'license_success' => true,
            'license_message' => '',
        ];

        $lData = LicenseData::getInstance();

        try {
            DupLog::trace("CONTROLLER DEACTIVATE LICENSE ACTION: Deactivating license");

            if ($lData->getStatus() !== LicenseData::STATUS_VALID) {
                return [
                    'license_success' => true,
                    'license_message' => __('License already deactivated.', 'duplicator-pro'),
                ];
            }

            $licenseKey = $lData->getKey();

            switch ($lData->deactivate()) {
                case LicenseData::ACTIVATION_RESPONSE_OK:
                    $result['license_message'] = sprintf(
                        _x(
                            'License %1$s %2$sDeactivated%3$s',
                            '%1$s is the license key, %2$s and %3$s are opening and closing HTML tags',
                            'duplicator-pro'
                        ),
                        LicenseData::maskLicenseKey($licenseKey),
                        '<b class="alert-color">',
                        '</b>'
                    );

                    LogEventLicenseDeactivation::create(
                        LogEventLicenseDeactivation::SUB_TYPE_SUCCESS,
                        LicenseData::maskLicenseKey($licenseKey),
                        $lData->getStatus(),
                    );
                    break;
                case LicenseData::ACTIVATION_RESPONSE_INVALID:
                    throw new Exception(__('Invalid license key.', 'duplicator-pro'));
                case LicenseData::ACTIVATION_REQUEST_ERROR:
                    $result['license_request_error'] = $lData->getLastRequestError();
                    throw new Exception(self::getRequestErrorMessage());
                default:
                    throw new Exception(__('Error deactivating license.', 'duplicator-pro'));
            }
        } catch (Exception $e) {
            LogEventLicenseDeactivation::create(
                LogEventLicenseDeactivation::SUB_TYPE_ERROR,
                LicenseData::maskLicenseKey($lData->getKey()),
                $lData->getStatus(),
                $e->getMessage()
            );

            $result['license_success'] = false;
            $result['license_message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Return template file path
     *
     * @param string $path    path to the template file
     * @param string $slugTpl slug of the template
     *
     * @return string
     */
    public static function getTemplateFile($path, $slugTpl)
    {
        if (strpos($slugTpl, 'licensing/') === 0) {
            return ProBase::getAddonPath() . '/template/' . $slugTpl . '.php';
        }
        return $path;
    }

    /**
     * Action that activates the license
     *
     * @return array<string, mixed>
     */
    public static function activateLicense(): array
    {
        $result = [
            'license_success' => true,
            'license_message' => '',
        ];

        $lData = LicenseData::getInstance();

        try {
            if (($licenseKey = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, '_license_key')) === false) {
                throw new Exception(__('Please enter a valid key. Key should be 32 characters long.', 'duplicator-pro'));
            }

            if (!preg_match('/^[a-f0-9]{32}$/i', $licenseKey)) {
                throw new Exception(__('Please enter a valid key. Key should be 32 characters long.', 'duplicator-pro'));
            }

            DupLog::trace("CONTROLLER ACTIVATE LICENSE ACTION: Setting license key to " . LicenseData::maskLicenseKey($licenseKey));

            // make sure reset old license key if exists (without logging)
            self::clearLicenseKey(false);
            $lData->setKey($licenseKey);

            switch ($lData->activate()) {
                case LicenseData::ACTIVATION_RESPONSE_OK:
                    $result['license_message'] = sprintf(
                        _x(
                            'License %1$s %2$sActivated%3$s',
                            '%1$s is the license key, %2$s and %3$s are opening and closing HTML tags',
                            'duplicator-pro'
                        ),
                        LicenseData::maskLicenseKey($licenseKey),
                        '<b class="green-color">',
                        '</b>'
                    );

                    // Important to manage frontend license post activation actions
                    $result['license_action_activation_success'] = true;

                    LogEventLicenseActivation::create(
                        LogEventLicenseActivation::SUB_TYPE_SUCCESS,
                        LicenseData::maskLicenseKey($licenseKey),
                        $lData->getExpirationDate(),
                        $lData->getExpirationDays(),
                        $lData->getSiteCount(),
                        $lData->getLicenseLimit(),
                        $lData->getStatus(),
                    );


                    break;
                case LicenseData::ACTIVATION_RESPONSE_INVALID:
                    throw new Exception(__('Invalid license key.', 'duplicator-pro'));
                case LicenseData::ACTIVATION_REQUEST_ERROR:
                    $result['license_request_error'] = $lData->getLastRequestError();
                    DupLog::traceObject('License request error', $result['license_request_error']);
                    throw new Exception(self::getRequestErrorMessage());
                default:
                    throw new Exception(__('Error activating license.', 'duplicator-pro'));
            }
        } catch (Exception $e) {
            $result['license_success'] = false;
            $result['license_message'] = $e->getMessage();

            LogEventLicenseActivation::create(
                LogEventLicenseActivation::SUB_TYPE_ERROR,
                LicenseData::maskLicenseKey($lData->getKey()),
                $lData->getExpirationDate(),
                $lData->getExpirationDays(),
                $lData->getSiteCount(),
                $lData->getLicenseLimit(),
                $lData->getStatus(),
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Force a refresh of the license data action
     *
     * @return array<string,mixed>
     */
    public static function forceRefresh(): array
    {
        DupLog::trace("CONTROLLER FORCE REFRESH ACTION: Force refreshing license data");

        $result = [
            'license_success' => true,
            'license_message' => __("License data reloaded.", 'duplicator-pro'),
        ];

        $lData = LicenseData::getInstance();

        try {
            $oldStatus  = $lData->getStatus();
            $licenseKey = $lData->getKey();

            $lData->resetLastRequestFailure();
            $lData->getLicenseData(true);

            $newStatus = $lData->getStatus();

            // Log the force refresh action and any status changes
            if ($oldStatus !== $newStatus) {
                // Status changed during refresh - determine the appropriate subtype
                $subType = LogEventLicenseStatusChanged::SUB_TYPE_INVALID; // default
                switch ($newStatus) {
                    case LicenseData::STATUS_EXPIRED:
                        $subType = LogEventLicenseStatusChanged::SUB_TYPE_EXPIRED;
                        break;
                    case LicenseData::STATUS_DISABLED:
                        $subType = LogEventLicenseStatusChanged::SUB_TYPE_DISABLED;
                        break;
                    case LicenseData::STATUS_INACTIVE:
                    case LicenseData::STATUS_SITE_INACTIVE:
                        $subType = LogEventLicenseStatusChanged::SUB_TYPE_INACTIVE;
                        break;
                    case LicenseData::STATUS_INVALID:
                        $subType = LogEventLicenseStatusChanged::SUB_TYPE_INVALID;
                        break;
                    case LicenseData::STATUS_VALID:
                        $subType = LogEventLicenseStatusChanged::SUB_TYPE_RESTORED;
                        break;
                }

                LogEventLicenseStatusChanged::create(
                    $subType,
                    LicenseData::maskLicenseKey($licenseKey),
                    $lData->getStatus(),
                    $lData->getExpirationDate(),
                    $lData->getExpirationDays(),
                    $lData->getSiteCount(),
                    $lData->getLicenseLimit()
                );
            }
        } catch (Exception $e) {
            $result['license_success'] = false;
            $result['license_message'] = $e->getMessage();

            LogEventLicenseStatusChanged::create(
                LogEventLicenseStatusChanged::SUB_TYPE_ERROR,
                LicenseData::maskLicenseKey($lData->getKey()),
                $lData->getStatus(),
                $lData->getExpirationDate(),
                $lData->getExpirationDays(),
                $lData->getSiteCount(),
                $lData->getLicenseLimit(),
                $e->getMessage()
            );
        }

        return $result;
    }

    /**
     * Render page content
     *
     * @return void
     */
    public static function renderLicenseContent(): void
    {
        if (!CapMng::getInstance()->can(CapMng::CAP_LICENSE, false)) {
            return;
        }
        self::renderLicenseMessage();
        TplMng::getInstance()->render('licensing/main');
    }

    /**
     * Render page content
     *
     * @return void
     */
    public static function renderLicenseVisibility(): void
    {
        if (!CapMng::getInstance()->can(CapMng::CAP_LICENSE, false)) {
            return;
        }
        TplMng::getInstance()->render('licensing/visibility');
    }

    /**
     * Return true avter license activation action
     *
     * @return bool
     */
    public static function isActivationLicenseRender(): bool
    {
        return TplMng::getInstance()->getDataValueBool('license_action_activation_success', false);
    }

    /**
     * Render activation/deactivation license message
     *
     * @return void
     */
    protected static function renderLicenseMessage()
    {
        if (!CapMng::getInstance()->can(CapMng::CAP_LICENSE, false)) {
            return;
        }

        $tplData = TplMng::getInstance()->getGlobalData();
        if (empty($tplData['license_message'])) {
            return;
        }

        $success = (isset($tplData['license_success']) && $tplData['license_success'] === true);
        AdminNotices::displayGeneralAdminNotice(
            TplMng::getInstance()->render('licensing/notices/activation_message', [], false),
            ($success ? AdminNotices::GEN_SUCCESS_NOTICE : AdminNotices::GEN_ERROR_NOTICE),
            false,
            [],
            [],
            true
        );
    }

    /**
     * Returns the communication error message
     *
     * @return string
     */
    private static function getRequestErrorMessage(): string
    {
        $result  = '<b>' . __('License data request failed.', 'duplicator-pro') . '</b>';
        $result .= '<br>';
        return $result . sprintf(
            _x(
                'Please see %1$sthis FAQ entry%2$s for possible causes and resolutions.',
                '%1$s and %2$s represents the opening and closing HTML tags for an anchor or link',
                'duplicator-pro'
            ),
            '<a href="' . DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-license-activation-issues/" target="_blank">',
            '</a>'
        );
    }
}
