<?php

/**
 * Impost installer page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\AbstractBlankPageController;
use Duplicator\Core\Views\TplMng;
use Duplicator\Utils\Email\EmailSummary;

class EmailSummaryPreviewPageController extends AbstractBlankPageController
{
    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->pageSlug     = EmailSummary::PREVIEW_SLUG;
        $this->capatibility = CapMng::CAP_SETTINGS;

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
        TplMng::getInstance()->render('mail/email_summary', EmailSummary::getInstance()->getData());
    }
}
