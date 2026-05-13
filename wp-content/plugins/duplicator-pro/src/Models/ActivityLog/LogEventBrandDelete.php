<?php

namespace Duplicator\Models\ActivityLog;

use Duplicator\Core\CapMng;

/**
 * Log event for brand delete operations
 */
class LogEventBrandDelete extends AbstractLogEvent
{
    /**
     * Class constructor
     *
     * @param int[] $brandIds Array of brand IDs that were deleted
     */
    public function __construct(array $brandIds)
    {
        $this->subType  = 'delete';
        $this->severity = self::SEVERITY_INFO;

        $currentUser = wp_get_current_user();
        $screen      = get_current_screen();
        $count       = count($brandIds);

        // Store deletion-specific data
        $this->data = [
            'user_id'           => get_current_user_id(),
            'user_display'      => $currentUser->display_name ?: $currentUser->user_login ?: 'system',
            'role'              => !empty($currentUser->roles) ? $currentUser->roles[0] : 'unknown',
            'screen'            => $screen ? $screen->id : ($_SERVER['REQUEST_URI'] ?? ''),
            'ip_address'        => self::getClientIP(),
            'timestamp'         => time(),

            // Deletion-specific data
            'deleted_brand_ids' => $brandIds,
            'count'             => $count,
        ];

        // Set user-friendly title
        $this->title = $this->generateTitle($count);
    }

    /**
     * Create and save a brand delete log event
     *
     * @param int[] $brandIds Array of brand IDs that were deleted
     *
     * @return self
     */
    public static function create(array $brandIds): self
    {
        $logEvent = new self($brandIds);
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
        return 'brand_delete';
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
        $count = $this->data['count'];

        if ($count === 1) {
            return __('Installer branding theme removed', 'duplicator-pro');
        } else {
            return sprintf(__('%d installer branding themes removed', 'duplicator-pro'), $count);
        }
    }

    /**
     * Display detailed information in html format
     *
     * @return void
     */
    public function detailHtml(): void
    {
        $count           = $this->data['count'];
        $deletedBrandIds = $this->data['deleted_brand_ids'];
        $userDisplay     = $this->data['user_display'] ?? 'Unknown';
        $userRole        = $this->data['role'] ?? 'unknown';
        $ipAddress       = $this->data['ip_address'] ?? 'Unknown';
        $screen          = $this->data['screen'] ?? 'Unknown';
        ?>
        <div class="dup-log-detail-meta">
            <div class="dup-log-type-wrapper">
                <strong><?php esc_html_e('Action:', 'duplicator-pro'); ?></strong>
                <span class="dup-log-type"><?php esc_html_e('Delete', 'duplicator-pro'); ?></span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong>
                    <?php echo $count === 1 ?
                        esc_html__('Brand Deleted:', 'duplicator-pro') :
                        esc_html__('Brands Deleted:', 'duplicator-pro'); ?>
                </strong>
                <span class="dup-log-type"><?php echo esc_html($count); ?></span>
            </div>

            <div class="dup-log-type-wrapper">
                <strong>
                    <?php echo $count === 1 ?
                        esc_html__('Brand ID:', 'duplicator-pro') :
                        esc_html__('Brand IDs:', 'duplicator-pro'); ?>
                </strong>
                <span class="dup-log-type"><?php echo esc_html(implode(', ', $deletedBrandIds)); ?></span>
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
        return __('Brand Deletion', 'duplicator-pro');
    }

    /**
     * Generate user-friendly title
     *
     * @param int $count Number of brands deleted
     *
     * @return string
     */
    private function generateTitle(int $count): string
    {
        if ($count === 1) {
            return __('Brand deleted', 'duplicator-pro');
        } else {
            return sprintf(__('%d Brands deleted', 'duplicator-pro'), $count);
        }
    }
}
