<?php

/**
 * Impost installer page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Core\Bootstrap;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractSinglePageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Import\PackageImporter;

class ImportInstallerPageController extends AbstractSinglePageController
{
    /** @var PackageImporter */
    protected static $importObj;
    /** @var string */
    protected static $iframeSrc;

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->pageSlug     = ControllersManager::IMPORT_INSTALLER_PAGE;
        $this->pageTitle    = __('Install Backup', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_IMPORT;

        add_action('duplicator_before_run_actions_' . $this->pageSlug, [$this, 'packageCheck']);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
    }

    /**
     * Return true if current page is enabled
     *
     * @return boolean
     */
    public function isEnabled(): bool
    {
        return !((bool) DUPLICATOR_DISALLOW_IMPORT); // @phpstan-ignore-line
    }

    /**
     * called on admin_print_styles-[page] hook
     *
     * @return void
     */
    public function pageStyles(): void
    {
        Bootstrap::enqueueStyles();
        wp_enqueue_style('dupli-import');
    }

    /**
     * called on admin_print_scripts-[page] hook
     *
     * @return void
     */
    public function pageScripts(): void
    {
        self::dequeueAllScripts();
        Bootstrap::enqueueScripts();
        wp_enqueue_script('dupli-import-installer');
    }

    /**
     * dequeue all scripts except jquery and dupli- scripts
     *
     * @return boolean // false if scripts can't be dequeued
     */
    public static function dequeueAllScripts(): bool
    {

        if (!function_exists('wp_scripts')) {
            return false;
        }

        $scripts = wp_scripts();
        foreach ($scripts->registered as $handle => $script) {
            if (
                strpos($handle, 'jquery') === 0 ||
                strpos($handle, 'dupli-') === 0
            ) {
                continue;
            }
            wp_dequeue_script($handle);
        }

        return true;
    }

    /**
     * Load import object and make a redirect if is a lite Backup
     *
     * @param array<string, string> $currentLevelSlugs current menu page
     *
     * @return void
     */
    public function packageCheck($currentLevelSlugs): void
    {
        $archivePath     = SnapUtil::sanitizeDefaultInput(INPUT_GET, 'package');
        self::$importObj = new PackageImporter($archivePath);
        self::$iframeSrc = self::$importObj->prepareToInstall();

        /* uncomment this to enable installer on new page
        if (self::$importObj->isLite()) {
            wp_redirect(self::$iframeSrc);
            die;
        }*/
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
        $tplMng = TplMng::getInstance();
        $data   = $tplMng->getGlobalData();

        if ($data['actionsError']) {
            $tplMng->render('admin_pages/import/import-installer-error');
        } else {
            $tplMng->render(
                'admin_pages/import/import-installer',
                [
                    'importObj' => self::$importObj,
                    'iframeSrc' => self::$iframeSrc,
                ]
            );
        }
    }
}
