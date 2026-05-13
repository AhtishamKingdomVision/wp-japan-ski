<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;

/**
 * Log event for license visibility changes
 */
class LogEventLicenseVisibilityChanged extends AbstractLogEvent
{
    const SUB_TYPE_SUCCESS = 'success';
    const SUB_TYPE_ERROR   = 'error';

    /**
     * Class constructor
     *
     * @param int    $oldVisibility Old visibility setting
     * @param int    $newVisibility New visibility setting
     * @param string $subType       Sub-type of the event
     */
    public function __construct(int $oldVisibility, int $newVisibility, string $subType = self::SUB_TYPE_SUCCESS)
    {
        $this->subType  = $subType;
        $this->severity = $this->subType === self::SUB_TYPE_SUCCESS ? self::SEVERITY_INFO : self::SEVERITY_ERROR;

        $currentUser = wp_get_current_user();

        // Store base user information and license data
        $this->data = [
            'user_id'        => get_current_user_id(),
            'user_display'   => $currentUser->display_name ?: $currentUser->user_login ?: 'system',
            'role'           => !empty($currentUser->roles) ? $currentUser->roles[0] : 'unknown',
            'ip_address'     => self::getClientIP(),
            'timestamp'      => time(),
            'old_visibility' => $this->getVisibilityString($oldVisibility),
            'new_visibility' => $this->getVisibilityString($newVisibility),
        ];

        // Set severity and title
        $this->title = __('License Visibility Changed', 'duplicator-pro');
    }

    /**
     * Create and save a license visibility changed log event
     *
     * @param int    $oldVisibility Old visibility setting
     * @param int    $newVisibility New visibility setting
     * @param string $subType       Sub-type of the event
     *
     * @return self
     */
    public static function create(int $oldVisibility, int $newVisibility, string $subType = self::SUB_TYPE_SUCCESS): self
    {
        $logEvent = new self($oldVisibility, $newVisibility, $subType);
        $logEvent->save();
        return $logEvent;
    }

    /**
     * Return the event type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'license_visibility_changed';
    }

    /**
     * Get type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('License Visibility', 'duplicator-pro');
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
        switch ($this->subType) {
            case self::SUB_TYPE_SUCCESS:
                return sprintf(
                    __('License visibility changed from "%1$s" to "%2$s"', 'duplicator-pro'),
                    $this->data['old_visibility'],
                    $this->data['new_visibility']
                );
            case self::SUB_TYPE_ERROR:
                return __('License visibility change failed', 'duplicator-pro');
            default:
                return __('License visibility change', 'duplicator-pro');
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
                    <?php esc_html_e('License Visibility Changed', 'duplicator-pro'); ?>
                </span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('User:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type">
                    <?php echo esc_html($this->data['user_display'] ?? 'Unknown'); ?>
                    (<?php echo esc_html($this->data['role'] ?? 'unknown'); ?>)
                </span>
            </div>

            <?php if ($this->subType === self::SUB_TYPE_SUCCESS) : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Visibility Change:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php printf(
                            esc_html__('%1$s → %2$s', 'duplicator-pro'),
                            esc_html($this->data['old_visibility']),
                            esc_html($this->data['new_visibility'])
                        ); ?>
                    </span>
                </div>
            <?php else : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Error:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type">
                        <?php esc_html_e('Could not change license visibility.', 'duplicator-pro'); ?>
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
        </div>
        <?php
    }

    /**
     * Get visibility string from integer value
     *
     * @param int $visibility Visibility constant
     *
     * @return string Human-readable visibility string
     */
    private function getVisibilityString(int $visibility): string
    {
        switch ($visibility) {
            case License::VISIBILITY_ALL:
                return __('License Visible', 'duplicator-pro');
            case License::VISIBILITY_INFO:
                return __('Info Only', 'duplicator-pro');
            case License::VISIBILITY_NONE:
                return __('License Invisible', 'duplicator-pro');
            default:
                return __('Unknown', 'duplicator-pro');
        }
    }
}
