<?php

/**
 * DupCloud ajax services
 *
 * @package   Duplicator\Addons\DupCloudAddon
 * @copyright (c) 2024, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Addons\DupCloudAddon\Ajax;

use Duplicator\Addons\DupCloudAddon\Models\QuickConnect;
use Duplicator\Ajax\AbstractAjaxService;
use Duplicator\Ajax\AjaxWrapper;
use Duplicator\Core\CapMng;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;

/**
 * Handles connection requests for DupCloud
 */
class ServicesDupCloud extends AbstractAjaxService
{
    const AJAX_ACTION_QUICK_CONNECT = 'duplicator_dupcloud_quick_connect';

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init(): void
    {
        $this->addAjaxCall('wp_ajax_' . self::AJAX_ACTION_QUICK_CONNECT, 'getStorageTokens');
    }

    /**
     * Get storage tokens via license
     *
     * @return void
     */
    public function getStorageTokens(): void
    {
        AjaxWrapper::json(
            [
                $this,
                'getStorageTokensCallback',
            ],
            self::AJAX_ACTION_QUICK_CONNECT,
            SnapUtil::sanitizeStrictInput(INPUT_POST, 'nonce'),
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Get storage tokens callback
     *
     * @return array{tokens:array<string,mixed>,html:string}
     */
    public function getStorageTokensCallback(): array
    {
        $tokens = QuickConnect::getStorageTokens();

        return [
            'tokens' => $tokens,
            'html'   => TplMng::getInstance()->render(
                'dupcloudaddon/connect/quick_connect',
                ['tokens' => $tokens],
                false
            ),
        ];
    }
}
