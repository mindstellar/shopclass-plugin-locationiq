<?php
/*
Plugin Name: LocationIQ Geocoder
Plugin URI: https://github.com/mindstellar/shopclass-plugin-locationiq
Description: Finds the exact map position of a listing from its street address with LocationIQ. Pick it in Settings > General > Maps > Address lookup.
Version: 0.1.1
Author: Navjot Tomer (Mindstellar)
Author URI: https://mindstellar.com
Short Name: locationiq
Requires Shopclass: 7.0.0
Tested up to: 7.0
Requires PHP: 8.0
Support URI: https://github.com/mindstellar/shopclass-plugin-locationiq/issues
*/

/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use mindstellar\location\GeocodeProviderRegistry;
use mindstellar\locationiq\Plugin;

if (!defined('ABS_PATH')) {
    exit('Direct access is not allowed.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'mindstellar\\locationiq\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

osc_register_plugin(osc_plugin_path(__FILE__), array(Plugin::class, 'install'));
osc_add_hook(osc_plugin_path(__FILE__) . '_uninstall', array(Plugin::class, 'uninstall'));

// Older Shopclass has no geocode provider API, so the plugin stays off there.
if (!interface_exists('mindstellar\\location\\GeocodeProvider')) {
    osc_add_hook('admin_page_header', static function (): void {
        echo '<div class="flashmessage flashmessage-warning" role="status">'
            . osc_esc_html(__('LocationIQ Geocoder needs Shopclass 7.0 or later and is switched off. Update Shopclass to use it.', 'locationiq'))
            . '</div>';
    }, 10);

    return;
}

osc_register_settings_page(Plugin::PAGE, require __DIR__ . '/settings.php');
osc_add_hook(osc_plugin_path(__FILE__) . '_configure', static function () {
    osc_redirect_to(osc_settings_page_url(Plugin::PAGE));
});

// Built only when something geocodes.
osc_add_hook(GeocodeProviderRegistry::HOOK, array(Plugin::class, 'register'));

osc_add_hook('admin_page_header', array(Plugin::class, 'adminNotice'), 10);
