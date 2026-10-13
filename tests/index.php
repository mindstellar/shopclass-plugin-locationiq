<?php

/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */


/**
 * index.php itself: what it hooks on a core with the geocode API, and on one without.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\location\GeocodeProviderRegistry;
use mindstellar\locationiq\Plugin;

$GLOBALS['registered'] = array();
$GLOBALS['pages']      = array();
$GLOBALS['redirects']  = array();

function osc_plugin_path($file)
{
    return 'plugin/index.php';
}
function osc_register_plugin($path, $install)
{
    $GLOBALS['registered'][$path] = $install;
}
function osc_register_settings_page($id, $spec)
{
    $GLOBALS['pages'][$id] = $spec;
}
function osc_redirect_to($url)
{
    $GLOBALS['redirects'][] = $url;
}

harness_section('core with the geocode API');
require __DIR__ . '/../index.php';
$hooks = $GLOBALS['hooks'];
pin('the lazy provider hook is used', array(array(Plugin::class, 'register')), $hooks[GeocodeProviderRegistry::HOOK] ?? null);
check('uninstall is hooked', isset($hooks['plugin/index.php_uninstall']) && $hooks['plugin/index.php_uninstall'] === array(array(Plugin::class, 'uninstall')));
check('configure is hooked', count($hooks['plugin/index.php_configure'] ?? array()) === 1);
pin('the admin notice is hooked', array(array(Plugin::class, 'adminNotice')), $hooks['admin_page_header'] ?? null);
pin('the install callback is registered', array('plugin/index.php' => array(Plugin::class, 'install')), $GLOBALS['registered']);
pin('the settings page is registered', array(Plugin::PAGE), array_keys($GLOBALS['pages']));
pin('only those hooks and no others', array('admin_page_header', 'plugin/index.php_configure', 'plugin/index.php_uninstall', GeocodeProviderRegistry::HOOK), (static function () use ($hooks): array {
    $names = array_keys($hooks);
    sort($names);

    return $names;
})());
$hooks['plugin/index.php_configure'][0]();
pin('configure goes to the settings page', array(osc_settings_page_url(Plugin::PAGE)), $GLOBALS['redirects']);

harness_section('core without the geocode API');
$script = __DIR__ . '/lib/index-oldcore.php';
$out    = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
$seen   = json_decode($out, true);
check('the old-core run answered', is_array($seen), $out);
$names = $seen['hooks'] ?? array();
sort($names);
pin('only the notice and uninstall are hooked', array('admin_page_header', 'plugin/index.php_uninstall'), $names);
pin('no settings page', 0, $seen['pages'] ?? null);
pin('no provider hook', false, $seen['provider'] ?? null);
check('the notice says it is switched off', str_contains((string) ($seen['notice'] ?? ''), 'switched off'), (string) ($seen['notice'] ?? ''));

exit(harness_result());
