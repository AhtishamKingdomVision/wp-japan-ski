<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Addons\ProBase\Models\LicenseData;
use Duplicator\Core\CapMng;

/**
 * Log event for license activation (success and failure)
 */
class LogEventLicenseActivation extends AbstractLogEvent
{
    const SUB_TYPE_SUCCESS = 'success';
    const SUB_TYPE_ERROR   = 'error';
    const SUB_TYPE_INVALID = 'invalid';
    const SUB_TYPE_EXPIRED = 'expired';

    /**
     * Class constructor
     *
     * @param string $subType          Activation result type
     * @param string $maskedLicenseKey Masked license key
     * @param string $expirationDate   Expiration date
     * @param int    $expirationDays   Expiration days
     * @param int    $siteCount        Site count
     * @param int    $licenseLimit     License limit
     * @param int    $licenseStatus    License status
     * @param string $errorMessage     Error message
     *
     * @return void
     */
    public function __construct(
        string $subType = self::SUB_TYPE_SUCCESS,
        string $maskedLicenseKey = '',
        string $expirationDate = '',
        int $expirationDays = 0,
        int $siteCount = 0,
        int $licenseLimit = 0,
        int $licenseStatus = 0,
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

        // Set severity and title based on subtype
        $this->setSeverityAndTitle();
    }

    /**
     * Create a new license activation event
     *
     * @param string $subType          Activation result type
     * @param string $maskedLicenseKey Masked license key
     * @param string $expirationDate   Expiration date
     * @param int    $expirationDays   Expiration days
     * @param int    $siteCount        Site count
     * @param int    $licenseLimit     License limit
     * @param int    $licenseStatus    License status
     * @param string $errorMessage     Error message
     *
     * @return void
     */
    public static function create(
        string $subType = self::SUB_TYPE_SUCCESS,
        string $maskedLicenseKey = '',
        string $expirationDate = '',
        int $expirationDays = 0,
        int $siteCount = 0,
        int $licenseLimit = 0,
        int $licenseStatus = 0,
        string $errorMessage = ''
    ): void {
        $logEvent = new self(
            $subType,
            $maskedLicenseKey,
            $expirationDate,
            $expirationDays,
            $siteCount,
            $licenseLimit,
            $licenseStatus,
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
        return 'license_activation';
    }

    /**
     * Get type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('License Activation', 'duplicator-pro');
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

        switch ($this->subType) {
            case self::SUB_TYPE_SUCCESS:
                $description = sprintf(__('License %1$s activated successfully', 'duplicator-pro'), $licenseKeyMasked);

                $details = [];

                // Add expiration info
                $expirationDays = $this->data['expiration_days'] ?? 0;
                if ($expirationDays > 0) {
                    $details[] = sprintf(_n('expires in %d day', 'expires in %d days', $expirationDays, 'duplicator-pro'), $expirationDays);
                }

                // Add site limit info
                $licenseLimit = $this->data['license_limit'] ?? 0;
                if ($licenseLimit > 0) {
                    $details[] = sprintf(_n('allows %d site', 'allows %d sites', $licenseLimit, 'duplicator-pro'), $licenseLimit);
                }

                if (!empty($details)) {
                    $description .= ' (' . implode(', ', $details) . ')';
                }
                return $description;

            case self::SUB_TYPE_ERROR:
                return sprintf(__('License %1$s activation failed', 'duplicator-pro'), $licenseKeyMasked);

            case self::SUB_TYPE_INVALID:
                return sprintf(__('License %1$s activation failed: Invalid license key', 'duplicator-pro'), $licenseKeyMasked);

            case self::SUB_TYPE_EXPIRED:
                return sprintf(__('License %1$s activation failed: License expired', 'duplicator-pro'), $licenseKeyMasked);

            default:
                return sprintf(__('License %1$s activation attempted', 'duplicator-pro'), $licenseKeyMasked);
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

        <?php
        $expirationDate = $this->data['expiration_date'] ?? '';
        if (!empty($expirationDate)) :
            ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Expiration:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($expirationDate); ?>
                    <?php
                    $expirationDays = $this->data['expiration_days'] ?? 0;
                    // Only show days if not lifetime (PHP_INT_MAX)
                    if ($expirationDays > 0 && $expirationDays < PHP_INT_MAX) :
                        ?>
                        (<?php printf(esc_html__('%d days', 'duplicator-pro'), (int) $expirationDays); ?>)
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php
        $siteCount = $this->data['site_count'] ?? 0;
        if ($siteCount > 0) :
            ?>
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Site Usage:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php printf(
                        esc_html__('%1$d of %2$d sites', 'duplicator-pro'),
                        (int) $siteCount,
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
     * Set severity and title based on sub-type
     *
     * @return void
     */
    private function setSeverityAndTitle(): void
    {
        $licenseKeyMasked = $this->data['license_key'] ?? 'Unknown';

        switch ($this->subType) {
            case self::SUB_TYPE_SUCCESS:
                $this->severity = self::SEVERITY_INFO;
                $this->title    = sprintf(__('License Activated: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            case self::SUB_TYPE_ERROR:
            case self::SUB_TYPE_INVALID:
            case self::SUB_TYPE_EXPIRED:
                $this->severity = self::SEVERITY_ERROR;
                $this->title    = sprintf(__('License Activation Failed: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
            default:
                $this->severity = self::SEVERITY_INFO;
                $this->title    = sprintf(__('License Activation: %s', 'duplicator-pro'), $licenseKeyMasked);
                break;
        }
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
     * Get human-readable label for sub-type
     *
     * @return string
     */
    private function getSubTypeLabel(): string
    {
        switch ($this->subType) {
            case self::SUB_TYPE_SUCCESS:
                return __('License Activated', 'duplicator-pro');
            case self::SUB_TYPE_ERROR:
                return __('License Activation Failed', 'duplicator-pro');
            case self::SUB_TYPE_INVALID:
                return __('License Activation Failed (Invalid Key)', 'duplicator-pro');
            case self::SUB_TYPE_EXPIRED:
                return __('License Activation Failed (Expired)', 'duplicator-pro');
            default:
                return __('License Activation', 'duplicator-pro');
        }
    }
}
