<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Utils\Help\Help;
use Duplicator\Utils\Support\SupportToolkit;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div id="dupli-help-wrapper">
    <div id="dupli-help-header">
        <img src="<?php echo esc_url(DUPLICATOR_PLUGIN_URL . 'assets/img/duplicator-header-logo.svg'); ?>" />
    </div>
    <div id="dupli-help-content">
        <div id="dupli-help-search">
            <input type="text" placeholder="<?php esc_attr_e("Search", "duplicator-pro"); ?>" />
            <ul id="dupli-help-search-results"></ul>
            <div id="dupli-help-search-results-empty"><?php esc_html_e("No results found", "duplicator-pro"); ?></div>
        </div>
        <div id="dupli-context-articles">
            <?php if (count(Help::getInstance()->getArticlesByTag($tplData['tag'])) > 0) : ?>
                <h2><?php esc_html_e("Related Articles", "duplicator-pro"); ?></h2>
                <?php $tplMng->render('parts/help/article-list', ['articles' => Help::getInstance()->getArticlesByTag($tplData['tag'])]); ?>
            <?php endif; ?>
        </div>
        <div id="dupli-help-categories">
            <?php $tplMng->render('parts/help/category-list', ['categories' => Help::getInstance()->getTopLevelCategories()]); ?>
        </div>
        <div id="dupli-help-footer">
            <div class="dupli-help-footer-block">
                <i aria-hidden="true" class="fa fa-file-alt"></i>
                <h3><?php esc_html_e("View Documentation", "duplicator-pro"); ?></h3>
                <p>
                    <?php esc_html_e("Browse documentation, reference material, and tutorials for Duplicator.", "duplicator-pro"); ?>
                </p>
                <a 
                    href="<?php echo esc_url(DUPLICATOR_BLOG_URL . 'docs'); ?>" 
                    rel="noopener noreferrer" 
                    target="_blank" 
                    class="button">
                  <?php esc_html_e("View All Documentation", "duplicator-pro"); ?>
                </a>
            </div>
            <div class="dupli-help-footer-block">
                <i aria-hidden="true" class="fa fa-life-ring"></i>
                <h3><?php esc_html_e("Get Support", "duplicator-pro"); ?></h3>
                <p>
                    <?php esc_html_e("You can access our world-class support below.", "duplicator-pro"); ?>
                    <?php echo wp_kses(
                        sprintf(
                            _x(
                                'If reporting a bug, remember to include the %1$s to speed up the debugging process.',
                                '1: diagnostic data link with label or link to instructions to download logs manually',
                                'duplicator-pro'
                            ),
                            SupportToolkit::getDiagnosticInfoLinks()
                        ),
                        [
                            'a' => [
                                'href'   => [],
                                'target' => [],
                            ],
                        ]
                    ); ?>
                </p>
                <a 
                    href="<?php echo esc_url(DUPLICATOR_BLOG_URL . 'my-account/support/'); ?>" 
                    rel="noopener noreferrer" 
                    target="_blank" 
                    class="button">
                    <?php esc_html_e("Get Support", "duplicator-pro"); ?>
                </a>
            </div>
        </div>
    </div>
</div>
