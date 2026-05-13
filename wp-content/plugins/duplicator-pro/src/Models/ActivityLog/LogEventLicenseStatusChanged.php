<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Core\CapMng;

/**
 * Log event for license status changes (expired, disabled, inactive, invalid, restored)
 */
class LogEventLicenseStatusChanged extends AbstractLogEvent
{
    const SUB_TYPE_EXPIRED  = 'expired';
    const SUB_TYPE_DISABLED = 'disabled';
    const SUB_TYPE_INACTIVE = 'inactive';
    const SUB_TYPE_INVALID  = 'invalid';
    const SUB_TYPE_RESTORED = 'restored';
    const SUB_TYPE_ERROR    = 'error';

    /** @var int Maximum reasonable expiration days to display (10 years) */
    const MAX_REASONABLE_EXPIRATION_DAYS = 3650;

    /**
     * Class constructor
     *
     * @param string $subType          Status change type
     * @param string $maskedLicenseKey Masked license key
     * @param int    $licenseStatus    License status
     * @param string $expirationDate   Expiration date
     * @param int    $expirationDays   Expiration days
     * @param int    $siteCount        Site count
     * @param int    $licenseLimit     License limit
     * @param string $errorMessage     Error message
     *
     * @return void
     */
    public function __construct(
        string $subType,
        string $maskedLicenseKey,
        int $licenseStatus = 0,
        string $expirationDate = '',
        int $expirationDays = 0,
        int $siteCount = 0,
        int $licenseLimit = 0,
        string $errorMessage = ''
    ) {
        $this->subType = $subType;

        $currentUser = wp_get_current_user();

        // Store base user information and license data
        $this->data = [
            'user_id'         => get_current_user_id(),
            'user_display'    => $currentUser->display_name ?: $currentUser->user_login ?: 'system',
            'role'            => !empty($currentUser->roles) ? $currentUser->roles[0] : 'unknown',
            'ip_address'      => self::getClientIP(),
            'timestamp'       => time(),
            'license_key'     => $maskedLicenseKey,
            'expiration_date' => $expirationDate,
            'expiration_days' => $expirationDays,
            'site_count'      => $siteCount,
            'license_limit'   => $licenseLimit,
            'license_status'  => LicenseData::getStatusLabel($licenseStatus),
            'error_message'   => $errorMessage,
        ];

        // Set severity and title based on status type
        $this->setSeverityAndTitle();
    }

    /**
     * Create a new license status changed event
     *
     * @param string $subType          Status change type
     * @param string $maskedLicenseKey Masked license key
     * @param int    $licenseStatus    License status
     * @param string $expirationDate   Expiration date
     * @param int    $expirationDays   Expiration days
     * @param int    $siteCount        Site count
     * @param int    $licenseLimit     License limit
     * @param string $errorMessage     Error message
     *
     * @return void
     */
    public static function create(
        string $subType,
        string $maskedLicenseKey,
        int $licenseStatus = 0,
        string $expirationDate = '',
        int $expirationDays = 0,
        int $siteCount = 0,
        int $licenseLimit = 0,
        string $errorMessage = ''
    ): void {
        $logEvent = new self(
            $subType,
            $maskedLicenseKey,
            $licenseStatus,
            $expirationDate,
            $expirationDays,
            $siteCount,
            $licenseLimit,
            $errorMessage
        );
        $logEvent->save();
    }

    /**
     * Return the event type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'license_status_changed';
    }

    /**
     * Get type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('License Status Changed', 'duplicator-pro');
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
        $licenseStatus    = $this->data['license_status'] ?? 'Unknown';

        switch ($this->subType) {
            case self::SUB_TYPE_EXPIRED:
                $description = sprintf(__('License %1$s expired', 'duplicator-pro'), $licenseKeyMasked);

                if (!empty($this->data['expiration_date'])) {
                    $description .= sprintf(__(' on %s', 'duplicator-pro'), $this->data['expiration_date']);
                }
                return $description;

            case self::SUB_TYPE_DISABLED:
                return sprintf(__('License %1$s disabled by Duplicator', 'duplicator-pro'), $licenseKeyMasked);

            case self::SUB_TYPE_INACTIVE:
                return sprintf(__('License %1$s became inactive', 'duplicator-pro'), $licenseKeyMasked);

            case self::SUB_TYPE_INVALID:
                return sprintf(__('License %1$s became invalid', 'duplicator-pro'), $licenseKeyMasked);

            case self::SUB_TYPE_RESTORED:
                $description = sprintf(__('License %1$s status restored to %2$s', 'duplicator-pro'), $licenseKeyMasked, $licenseStatus);

                // Add remaining days info if not lifetime (PHP_INT_MAX)
                $expirationDays = $this->data['expiration_days'] ?? 0;
                if ($expirationDays > 0 && $expirationDays < PHP_INT_MAX) {
                    $description .= sprintf(__(' (%d days remaining)', 'duplicator-pro'), $expirationDays);
                }

                return $description;

            case self::SUB_TYPE_ERROR:
                return sprintf(__('Error updating license status: %s', 'duplicator-pro'), $licenseKeyMasked);

            default:
                return __('License status changed', 'duplicator-pro');
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
            <?php if ($this->subType === self::SUB_TYPE_ERROR && !empty($this->data['error_message'])) : ?>
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
                <?php echo esc_html($this->getSubTypeLabel()); ?>
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
                <?php echo esc_html($this->data['license_key']); ?>
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

        <?php if (!empty($this->data['expiration_date'])) : ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Expiration:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['expiration_date']); ?>
                    <?php
                    // Only show days for reasonable values (less than 10 years)
                    if (
                        !empty($this->data['expiration_days']) &&
                        $this->data['expiration_days'] > 0 &&
                        $this->data['expiration_days'] < self::MAX_REASONABLE_EXPIRATION_DAYS
                    ) :
                        ?>
                        (<?php printf(esc_html__('%d days', 'duplicator-pro'), (int) $this->data['expiration_days']); ?>)
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php
        if (!empty($this->data['site_count']) && $this->data['site_count'] >= 0) :
            ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Site Usage:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php printf(
                        esc_html__('%1$d of %2$d sites', 'duplicator-pro'),
                        (int) $this->data['site_count'],
                        (int) $this->data['license_limit']
                    ); ?>
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
     * Render error details for failed activation
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
            case self::SUB_TYPE_EXPIRED:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('License Expired: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            case self::SUB_TYPE_DISABLED:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('License Disabled: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            case self::SUB_TYPE_INACTIVE:
            case self::SUB_TYPE_INVALID:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('License Status Changed: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            case self::SUB_TYPE_RESTORED:
                $this->severity = self::SEVERITY_INFO;
                $this->title    = sprintf(__('License Status Restored: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            case self::SUB_TYPE_ERROR:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('Error updating license status: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            default:
                $this->severity = self::SEVERITY_INFO;
                $this->title    = __('License Status Changed', 'duplicator-pro');
                break;
        }
    }

    /**
     * Get human-readable label for sub-type
     *
     * @return string
     */
    private function getSubTypeLabel(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_EXPIRED:
                return __('License Expired', 'duplicator-pro');
            case self::SUB_TYPE_DISABLED:
                return __('License Disabled', 'duplicator-pro');
            case self::SUB_TYPE_INACTIVE:
                return __('License Inactive', 'duplicator-pro');
            case self::SUB_TYPE_INVALID:
                return __('License Invalid', 'duplicator-pro');
            case self::SUB_TYPE_RESTORED:
                return __('License Status Restored', 'duplicator-pro');
            case self::SUB_TYPE_ERROR:
                return __('License Error', 'duplicator-pro');
            default:
                return __('License Status Changed', 'duplicator-pro');
        }
    }
}
