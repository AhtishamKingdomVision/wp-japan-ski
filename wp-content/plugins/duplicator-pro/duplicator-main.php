<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || exit;

/** @var string $currentPluginBootFile */

use Duplicator\Pro as Duplicator;

// CHECK IF PLUGIN CAN BE EXECTUED
require_once __DIR__ . '/src/Core/RequirementsInterface.php';
require_once __DIR__ . '/src/Pro/Requirements.php';

if (Duplicator\Requirements::canRun($currentPluginBootFile) === false) {
    return;
} else {
    // NOTE: Plugin code must be inside a conditional block to prevent functions definition, simple return is not enough
    define('DUPLICATOR____PATH', dirname($currentPluginBootFile));
    define('DUPLICATOR____FILE', $currentPluginBootFile);
    define('DUPLICATOR____PLUGIN_URL', plugins_url('', $currentPluginBootFile));

    require_once DUPLICATOR____PATH . '/src/Utils/AbstractAutoloader.php';
    require_once DUPLICATOR____PATH . '/src/Utils/Autoloader.php';
    \Duplicator\Utils\Autoloader::register();

    /** @todo Remove this legacy constants loading once ProLegacy addon is refactored to use early hook */
    $legacyConstantsFile = DUPLICATOR____PATH . '/addons/prolegacy/src/LegacyConstants.php';
    if (file_exists($legacyConstantsFile)) {
        require_once $legacyConstantsFile;
        \Duplicator\Addons\ProLegacy\LegacyConstants::apply();
    }
    require_once DUPLICATOR____PATH . "/define.php";
    \Duplicator\Core\Bootstrap::init(Duplicator\Requirements::getAddsHash());
}
