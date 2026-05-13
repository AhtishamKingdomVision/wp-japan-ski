<?php

/**
 * Activity Log page controller
 *
 * @package   Duplicator
 * @copyright (c) 2024, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Controllers\SubMenuItem;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\ActivityLog\AbstractLogEvent;
use Duplicator\Models\ActivityLog\LogUtils;
use Duplicator\Views\ActivityLogScreen;
use Duplicator\Views\UserUIOptions;

/**
 * Activity Log page controller
 *
 * This controller manages the Activity Log page where users can view
 * all system activities, filter by type, severity, and date range.
 */
class ActivityLogPageController extends AbstractMenuPageController
{
    public const INNER_PAGE_LIST = 'list';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::ACTIVITY_LOG_SUBMENU_SLUG;
        $this->pageTitle    = __('Activity Log', 'duplicator-pro');
        $this->menuLabel    = __('Activity Log', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_BASIC;
        $this->menuPos      = 60;

        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_filter('set_screen_option_activity_log_screen_options', [ActivityLogScreen::class, 'setScreenOptions'], 11, 3);
    }

    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current menu slugs
     * @param string   $innerPage         current inner page, empty if not set
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs, $innerPage): void
    {
        switch ($innerPage) {
            case self::INNER_PAGE_LIST:
            default:
                // Get filter parameters
                $filters = [
                    'type'      => SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'filter_type', ''),
                    'severity'  => SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'filter_severity', -1),
                    'date_from' => SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'filter_date_from', ''),
                    'date_to'   => SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'filter_date_to', ''),
                    'search'    => SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'filter_search', ''),
                    'show_all'  => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'filter_show_all', false),
                ];

                // Get user preferences for pagination
                $uiOpts         = UserUIOptions::getInstance();
                $defaultPerPage = $uiOpts->get(UserUIOptions::VAL_ACTIVITY_LOG_PER_PAGE);

                // Get pagination parameters (use user preference as default)
                $page    = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'paged', 1);
                $perPage = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'per_page', $defaultPerPage);

                // Build query args
                $args = [
                    'page'     => $page,
                    'per_page' => $perPage,
                ];

                // Set parent_id based on show_all filter
                if (!$filters['show_all']) {
                    $args['parent_id'] = 0; // Only show parent events (no children)
                }

                // Apply other filters
                if (!empty($filters['type'])) {
                    $args['type'] = $filters['type'];
                }
                if ($filters['severity'] >= 0) {
                    $args['severity'] = $filters['severity'];
                }
                if (!empty($filters['date_from'])) {
                    $args['date_from'] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $args['date_to'] = $filters['date_to'];
                }
                if (!empty($filters['search'])) {
                    $args['search'] = $filters['search'];
                }

                // Get logs
                $logs       = AbstractLogEvent::getList($args);
                $totalItems = AbstractLogEvent::count($args);
                $totalPages = $perPage > 0 ? ceil($totalItems / $perPage) : 1;

                TplMng::getInstance()->render(
                    'admin_pages/activity_log/log_list',
                    [
                        'logs'           => $logs,
                        'filters'        => $filters,
                        'page'           => $page,
                        'perPage'        => $perPage,
                        'totalItems'     => $totalItems,
                        'totalPages'     => $totalPages,
                        'logTypes'       => LogUtils::getAllLogTypes(),
                        'severityLevels' => LogUtils::getSeverityLabels(),
                    ]
                );
                break;
        }
    }

    /**
     * Get severity levels
     *
     * @return array<int,string>
     */
    public function getSeverityLevels(): array
    {
        return [
            AbstractLogEvent::SEVERITY_INFO    => __('Info', 'duplicator-pro'),
            AbstractLogEvent::SEVERITY_WARNING => __('Warning', 'duplicator-pro'),
            AbstractLogEvent::SEVERITY_ERROR   => __('Error', 'duplicator-pro'),
        ];
    }

    /**
     * Get severity CSS class
     *
     * @param int $severity Severity level
     *
     * @return string CSS class name
     */
    public static function getSeverityClass(int $severity): string
    {
        switch ($severity) {
            case AbstractLogEvent::SEVERITY_ERROR:
                return 'dup-log-error';
            case AbstractLogEvent::SEVERITY_WARNING:
                return 'dup-log-warning';
            default:
                return 'dup-log-info';
        }
    }

    /**
     * Check if current page is activity log page
     *
     * @return bool
     */
    public static function isActivityLogPage(): bool
    {
        return ControllersManager::isCurrentPage(
            ControllersManager::ACTIVITY_LOG_SUBMENU_SLUG
        );
    }
}
