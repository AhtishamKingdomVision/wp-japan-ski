<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\CapMng;
use Duplicator\Models\BrandEntity;

/**
 * Log event for brand create and update operations
 */
class LogEventBrandCreateUpdate extends AbstractLogEvent
{
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';

    /**
     * Class constructor
     *
     * @param string      $action Brand action (create or update)
     * @param BrandEntity $brand  Brand entity object
     */
    public function __construct(string $action, BrandEntity $brand)
    {
        $this->subType  = $action;
        $this->severity = self::SEVERITY_INFO;

        $currentUser = wp_get_current_user();
        $screen      = get_current_screen();

        // Store brand-specific data
        $this->data = [
            'user_id'      => get_current_user_id(),
            'user_display' => $currentUser->display_name ?: $currentUser->user_login ?: 'system',
            'role'         => !empty($currentUser->roles) ? $currentUser->roles[0] : 'unknown',
            'screen'       => $screen ? $screen->id : ($_SERVER['REQUEST_URI'] ?? ''),
            'ip_address'   => self::getClientIP(),
            'timestamp'    => time(),

            // Brand object data
            'action'       => $action,
            'brand_id'     => $brand->getId(),
            'brand_name'   => $brand->name,
            'brand_notes'  => $brand->notes,
            'is_default'   => $brand->isDefault(),
        ];

        // Set user-friendly title
        $this->title = $this->generateTitle($action, $brand);
    }

    /**
     * Create and save a brand create/update log event
     *
     * @param string      $action Brand action (create or update)
     * @param BrandEntity $brand  Brand entity object
     *
     * @return self
     */
    public static function create(string $action, BrandEntity $brand): self
    {
        $logEvent = new self($action, $brand);
        $logEvent->save();
        return $logEvent;
    }

    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'brand_create_update';
    }

    /**
     * Return entity type label
     *
     * @return string
     */
    public static function getTypeLabel(): string
    {
        return __('Brand Management', 'duplicator-pro');
    }

    /**
     * Return required capability for this log event
     *
     * @return string
     */
    public static function getCapability(): string
    {
        return CapMng::CAP_SETTINGS;
    }

    /**
     * Return short description
     *
     * @return string
     */
    public function getShortDescription(): string
    {
        $action = $this->data['action'];

        switch ($action) {
            case self::ACTION_CREATE:
                return __('New installer branding theme created', 'duplicator-pro');
            case self::ACTION_UPDATE:
                return __('Installer branding theme modified', 'duplicator-pro');
            default:
                return __('Branding theme operation performed', 'duplicator-pro');
        }
    }

    /**
     * Display detailed information in html format
     *
     * @return void
     */
    public function detailHtml(): void
    {
        $action      = $this->data['action'];
        $brandId     = $this->data['brand_id'];
        $brandName   = $this->data['brand_name'];
        $brandNotes  = $this->data['brand_notes'];
        $isDefault   = $this->data['is_default'];
        $userDisplay = $this->data['user_display'] ?? 'Unknown';
        $userRole    = $this->data['role'] ?? 'unknown';
        $ipAddress   = $this->data['ip_address'] ?? 'Unknown';
        $screen      = $this->data['screen'] ?? 'Unknown';
        ?>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Action:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html(ucfirst($action)); ?></span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Brand ID:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($brandId); ?></span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Brand Name:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($brandName); ?></span>
            </div>

            <?php if (!empty($brandNotes)) : ?>
                <div class="dup-log-type-wrapper">
                    <strong><?php esc_html_e('Notes:', 'duplicator-pro'); ?></strong>
                    <span class="dup-log-type"><?php echo esc_html($brandNotes); ?></span>
                </div>
            <?php endif; ?>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Default Brand:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo $isDefault ? esc_html__('Yes', 'duplicator-pro') : esc_html__('No', 'duplicator-pro'); ?></span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Changed By:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($userDisplay); ?> (<?php echo esc_html($userRole); ?>)</span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('IP Address:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($ipAddress); ?></span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Page:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php echo esc_html($screen); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Return object type label
     *
     * @return string
     */
    public function getObjectTypeLabel(): string
    {
        switch ($this->subType) {
            case self::ACTION_CREATE:
                return __('Brand Creation', 'duplicator-pro');
            case self::ACTION_UPDATE:
                return __('Brand Update', 'duplicator-pro');
            default:
                return static::getTypeLabel();
        }
    }

    /**
     * Generate user-friendly title
     *
     * @param string      $action Brand action
     * @param BrandEntity $brand  Brand entity
     *
     * @return string
     */
    private function generateTitle(string $action, BrandEntity $brand): string
    {
        switch ($action) {
            case self::ACTION_CREATE:
                return sprintf(__('Brand created: %s', 'duplicator-pro'), $brand->name);
            case self::ACTION_UPDATE:
                return sprintf(__('Brand updated: %s', 'duplicator-pro'), $brand->name);
            default:
                return __('Brand operation', 'duplicator-pro');
        }
    }
}
