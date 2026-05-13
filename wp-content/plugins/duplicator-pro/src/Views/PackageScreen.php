<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Views;

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use WP_Screen;

class PackageScreen extends ScreenBase
{
    /**
     * Class contructor
     *
     * @param string $page Page
     *
     * @return void
     */
    public function __construct($page)
    {
        add_action('load-' . $page, [$this, 'init']);
        add_filter('screen_settings', [$this, 'showOptions'], 10, 2);
    }

    /**
     * Init Backup screen
     *
     * @return void
     */
    public function init(): void
    {
        add_action('admin_head', [self::class, 'displayColsCss']);
    }

    /**
     * Display columns css
     *
     * @return void
     */
    public static function displayColsCss(): void
    {
        $uiOpts = UserUIOptions::getInstance();

        $showNote    = $uiOpts->get(UserUIOptions::VAL_SHOW_COL_NOTE);
        $showSize    = $uiOpts->get(UserUIOptions::VAL_SHOW_COL_SIZE);
        $showCreated = $uiOpts->get(UserUIOptions::VAL_SHOW_COL_CREATED);
        $showAge     = $uiOpts->get(UserUIOptions::VAL_SHOW_COL_AGE);
        ?>
        <style>
            <?php if (!$showNote) { ?>
                .dup-packtbl .dup-note-column {
                    display: none;
                }
            <?php } ?>

            <?php if (!$showSize) { ?>
                .dup-packtbl .dup-size-column {
                    display: none;
                }
            <?php } ?>

            <?php if (!$showCreated) { ?>
                .dup-packtbl .dup-created-column {
                    display: none;
                }
            <?php } ?>

            <?php if (!$showAge) { ?>
                .dup-packtbl .dup-age-column {
                    display: none;
                }
            <?php } ?>
        </style>
        <?php
    }

    /**
     * Packages List: Screen Options Tab
     *
     * @param string    $screen_settings Screen settings
     * @param WP_Screen $args            Screen args
     *
     * @return string
     */
    public function showOptions($screen_settings, WP_Screen $args)
    {


        // Only display on Backups screen and not build screens
        if (
            !PackagesPageController::getInstance()->isCurrentPage() ||
            PackagesPageController::getCurrentInnerPage(PackagesPageController::LIST_INNER_PAGE_LIST) !== PackagesPageController::LIST_INNER_PAGE_LIST
        ) {
            return $screen_settings;
        }

        return TplMng::getInstance()->render('admin_pages/packages/screen_options', [], false);
    }

    /**
     * Set duplicator screen option
     *
     * @param mixed  $screen_option The value to save instead of the option value. Default false (to skip saving the current option).
     * @param string $option        The option name.
     * @param int    $value         The option value.
     *
     * @return bool
     */
    public static function setScreenOptions($screen_option, $option, $value): bool
    {
        $uiOpts = UserUIOptions::getInstance();

        $perPage = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'dupli-per-page', 10);
        $perPage = max(10, $perPage); // Minimum 10 entries per page

        $uiOpts->set(UserUIOptions::VAL_PACKAGES_PER_PAGE, $perPage);

        $dateFormat = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'dupli-created-format', 1);
        $uiOpts->set(UserUIOptions::VAL_CREATED_DATE_FORMAT, $dateFormat);

        $showNote = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dupli-note-hide');
        $uiOpts->set(UserUIOptions::VAL_SHOW_COL_NOTE, $showNote);

        $showSize = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dupli-size-hide');
        $uiOpts->set(UserUIOptions::VAL_SHOW_COL_SIZE, $showSize);

        $showCreated = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dupli-created-hide');
        $uiOpts->set(UserUIOptions::VAL_SHOW_COL_CREATED, $showCreated);

        $showAge = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dupli-age-hide');
        $uiOpts->set(UserUIOptions::VAL_SHOW_COL_AGE, $showAge);

        $uiOpts->save();

        // Returning false from the filter will skip saving the current option
        return false;
    }
}
