<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\CapMng;

/**
 * Log event for license key clearing
 */
class LogEventLicenseKeyCleared extends AbstractLogEvent
{
    const SUB_TYPE_SUCCESS = 'success';
    const SUB_TYPE_ERROR   = 'error';

    /**
     * Class constructor
     *
     * @param string $subType Success or failed
     *
     * @return void
     */
    public function __construct(string $subType = self::SUB_TYPE_SUCCESS)
    {
        $this->subType  = $subType;
        $this->severity = $subType === self::SUB_TYPE_SUCCESS ? self::SEVERITY_WARNING : self::SEVERITY_ERROR;
        $this->title    = $subType === self::SUB_TYPE_SUCCESS
            ? __('License Key Cleared', 'duplicator-pro')
            : __('License Key Could Not Be Cleared', 'duplicator-pro');

        $currentUser = wp_get_current_user();

        // Store base user information
        $this->data = [
            'user_id'      => get_current_user_id(),
            'user_display' => $currentUser->display_name ?: $currentUser->user_login ?: 'system',
            'role'         => !empty($currentUser->roles) ? $currentUser->roles[0] : 'unknown',
            'ip_address'   => self::getClientIP(),
            'timestamp'    => time(),
        ];
    }

    /**
     * Create a new license key cleared event
     *
     * @param string $subType Success or failed
     *
     * @return void
     */
    public static function create(string $subType = self::SUB_TYPE_SUCCESS): void
    {
        $logEvent = new self($subType);
        $logEvent->save();
    }

    /**
     * Return the event type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'license_key_cleared';
    }

    /**
     * Get type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('License Cleared', 'duplicator-pro');
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
        if ($this->subType === self::SUB_TYPE_SUCCESS) {
            return __('License key was cleared', 'duplicator-pro');
        } else {
            return __('License key could not be cleared', 'duplicator-pro');
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
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Event Type:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php esc_html_e('License Cleared', 'duplicator-pro'); ?>
                </span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('User:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['user_display'] ?? 'Unknown'); ?>
                    (<?php echo esc_html($this->data['role'] ?? 'unknown'); ?>)
                </span>
            </div>

            <?php if (!empty($this->data['ip_address'])) : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('IP Address:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php echo esc_html($this->data['ip_address']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
