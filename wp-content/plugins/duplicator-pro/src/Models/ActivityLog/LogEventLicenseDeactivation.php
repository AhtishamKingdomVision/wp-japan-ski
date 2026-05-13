<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\CapMng;
use Duplicator\Addons\ProBase\Models\LicenseData;

/**
 * Log event for license deactivation (both success and failure)
 */
class LogEventLicenseDeactivation extends AbstractLogEvent
{
    const SUB_TYPE_SUCCESS = 'success';
    const SUB_TYPE_ERROR   = 'error';
    const SUB_TYPE_TIMEOUT = 'timeout';

    /**
     * Class constructor
     *
     * @param string $subType          Success or failed
     * @param string $maskedLicenseKey Masked license key
     * @param int    $licenseStatus    License status
     * @param string $errorMessage     Error message for failures
     */
    public function __construct(
        string $subType = self::SUB_TYPE_SUCCESS,
        string $maskedLicenseKey = '',
        int $licenseStatus = 0,
        string $errorMessage = ''
    ) {
        $this->subType = $subType;

        $currentUser = wp_get_current_user();

        // Store base user information and license data
        $this->data = [
            'user_id'        => get_current_user_id(),
            'user_display'   => $currentUser->display_name ?: $currentUser->user_login ?: 'system',
            'role'           => !empty($currentUser->roles) ? $currentUser->roles[0] : 'unknown',
            'ip_address'     => self::getClientIP(),
            'timestamp'      => time(),
            'license_key'    => $maskedLicenseKey,
            'license_status' => LicenseData::getStatusLabel($licenseStatus),
            'error_message'  => $errorMessage,
        ];

        // Set severity and title based on success/failure
        $this->setSeverityAndTitle();
    }

    /**
     * Create and save a license deactivation log event
     *
     * @param string $subType          Success or failed
     * @param string $maskedLicenseKey Masked license key
     * @param int    $licenseStatus    License status
     * @param string $errorMessage     Error message for failures
     *
     * @return void
     */
    public static function create(
        string $subType = self::SUB_TYPE_SUCCESS,
        string $maskedLicenseKey = '',
        int $licenseStatus = 0,
        string $errorMessage = ''
    ): void {
        $logEvent = new self($subType, $maskedLicenseKey, $licenseStatus, $errorMessage);
        $logEvent->save();
    }

    /**
     * Return the event type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'license_deactivation';
    }

    /**
     * Get type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('License Deactivation', 'duplicator-pro');
    }

    /**
     * Get required capability to view this log
     *
     * @return string
     */
    public static function getCapability(): string
    {
        return CapMng::CAP_LICENSE;
    }

    /**
     * Get short description
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        $licenseKeyMasked = $this->data['license_key'] ?? 'Unknown';

        if ($this->subType === self::SUB_TYPE_SUCCESS) {
            return sprintf(__('License %1$s deactivated by user', 'duplicator-pro'), $licenseKeyMasked);
        } elseif ($this->subType === self::SUB_TYPE_TIMEOUT) {
            return sprintf(__('License %1$s deactivation failed: Connection timeout', 'duplicator-pro'), $licenseKeyMasked);
        } else {
            return sprintf(__('License %1$s deactivation failed', 'duplicator-pro'), $licenseKeyMasked);
        }
    }

    /**
     * Display detailed information in HTML format
     *
     * @return void
     */
    public function detailHtml(): void
    {
        ?>
        <div class="dup-log-license-details">
            <?php $this->renderCommonDetails(); ?>
            <?php if ($this->subType !== self::SUB_TYPE_SUCCESS && !empty($this->data['error_message'])) : ?>
                <?php $this->renderErrorDetails(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render common license details
     *
     * @return void
     */
    private function renderCommonDetails(): void
    {
        ?>
        <div class="dup-log-type-wrapper">
            <strong><?php esc_html_e('Event Type:', 'duplicator-pro'); ?></strong>
            <span class="dup-log-type">
                <?php echo $this->subType === self::SUB_TYPE_SUCCESS
                    ? esc_html__('License Deactivated', 'duplicator-pro')
                    : esc_html__('License Deactivation Failed', 'duplicator-pro'); ?>
            </span>
        </div>

        <div class="dup-log-type-wrapper">
            <strong><?php esc_html_e('User:', 'duplicator-pro'); ?></strong>
            <span class="dup-log-type">
                <?php echo esc_html($this->data['user_display'] ?? 'Unknown'); ?>
                (<?php echo esc_html($this->data['role'] ?? 'unknown'); ?>)
            </span>
        </div>

        <div class="dup-log-type-wrapper">
            <strong><?php esc_html_e('License Key:', 'duplicator-pro'); ?></strong>
            <span class="dup-log-type">
                <?php echo esc_html($this->data['license_key'] ?? 'Unknown'); ?>
            </span>
        </div>

        <?php if (!empty($this->data['license_status'])) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('License Status:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['license_status']); ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($this->data['ip_address'])) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('IP Address:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['ip_address']); ?>
                </span>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render error details for failed deactivation
     *
     * @return void
     */
    private function renderErrorDetails(): void
    {
        ?>
        <div class="dup-log-type-wrapper">
            <strong><?php esc_html_e('Error Message:', 'duplicator-pro'); ?></strong>
            <span class="dup-log-type">
                <?php echo wp_kses_post($this->data['error_message'] ?? __('Unknown error', 'duplicator-pro')); ?>
            </span>
        </div>
        <?php
    }

    /**
     * Set severity and title based on sub-type
     *
     * @return void
     */
    private function setSeverityAndTitle(): void
    {
        $licenseKeyMasked = $this->data['license_key'] ?? 'Unknown';

        switch ($this->subType) {
            case self::SUB_TYPE_SUCCESS:
                $this->severity = self::SEVERITY_WARNING;
                $this->title    = sprintf(__('License Deactivated: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            case self::SUB_TYPE_TIMEOUT:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('License Deactivation Failed (Timeout): %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            default:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('License Deactivation Failed: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
        }
    }
}
