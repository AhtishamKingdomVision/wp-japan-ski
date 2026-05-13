<?php

/**
 * Impost installer page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Core\Controllers\AbstractBlankPageController;
use Duplicator\Libs\Snap\SnapUtil;

class HelpPageController extends AbstractBlankPageController
{
    const HELP_SLUG = 'duplicator-dynamic-help';

    /** @var string Help article tag of current page */
    protected $tag = '';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->pageSlug     = self::HELP_SLUG;
        $this->capatibility = CapMng::CAP_BASIC;
        $this->tag          = SnapUtil::sanitizeInput(INPUT_GET, 'tag', '');

        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
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
        TplMng::getInstance()->render(
            "parts/help/main",
            [
                'tag' => $this->tag,
            ]
        );
    }
}
