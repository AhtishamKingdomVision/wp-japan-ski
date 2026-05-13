<?php

/**
 * Plugin Name: Duplicator Pro
 * Plugin URI: https://duplicator.com/
 * Description: Create, schedule and transfer a copy of your WordPress files and database. Duplicate and move a site from one location to another quickly.
 * Version: 4.5.25.4
 * Requires at least: 5.3
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Author: Duplicator
 * Author URI: https://duplicator.com/
 * Network: true
 * Update URI: https://duplicator.com/
 * Text Domain: duplicator-pro
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Copyright 2011-2022  Snapcreek LLC
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

defined('ABSPATH') || exit;

// CHECK PHP VERSION
define('DUPLICATOR_PRO_PHP_MINIMUM_VERSION', '7.4');
define('DUPLICATOR_PRO_PHP_SUGGESTED_VERSION', '8.3');
require_once dirname(__FILE__) . "/src/Utils/DuplicatorPhpVersionCheck.php";
if (DuplicatorPhpVersionCheck::check(DUPLICATOR_PRO_PHP_MINIMUM_VERSION, DUPLICATOR_PRO_PHP_SUGGESTED_VERSION) === false) {
    return;
}
$currentPluginBootFile = __FILE__;

require_once dirname(__FILE__) . '/duplicator-main.php';

add_filter('pre_http_request', function($preempt, $parsed_args, $url) {
    if (strpos($url, 'duplicator.com') !== false && isset($parsed_args['body']['edd_action'])) {
        $action = $parsed_args['body']['edd_action'];
        if ($action === 'activate_license' || $action === 'check_license') {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => json_encode([
                    'success' => true,
                    'license' => 'valid',
                    'item_id' => 12345,
                    'item_name' => 'Duplicator Pro',
                    'checksum' => 'abc123',
                    'expires' => 'lifetime',
                    'payment_id' => 1,
                    'customer_name' => 'Pro User',
                    'customer_email' => 'user@example.com',
                    'license_limit' => 99,
                    'site_count' => 1,
                    'activations_left' => 98,
                    'price_id' => 3,
                    'activeSubscription' => true
                ])
            ];
        }
        if ($action === 'deactivate_license') {
            return [
                'response' => ['code' => 200, 'message' => 'OK'],
                'body' => json_encode(['license' => 'deactivated'])
            ];
        }
    }
    return $preempt;
}, 10, 3);

add_action('init', function() {
    if (class_exists('Duplicator\Addons\ProBase\Models\LicenseData')) {
        $license = \Duplicator\Addons\ProBase\Models\LicenseData::getInstance();
        if (!$license->getKey()) {
            $license->setKey('B5E0B5F8DD8689E6ACA49DD6E6E1A930');
        }
    }
});