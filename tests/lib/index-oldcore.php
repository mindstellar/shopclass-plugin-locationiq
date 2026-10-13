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
 * Runs index.php where core has no GeocodeProvider interface, and prints what it hooked as JSON.
 * Used by tests/index.php in a separate process, so the interface cannot be loaded by an earlier test.
 */

define('ABS_PATH', '/');

$hooks = array();
$pages = 0;

function __($text, $domain = '')
{
    return $text;
}
function osc_esc_html($text)
{
    return htmlspecialchars((string) $text);
}
function osc_plugin_path($file)
{
    return 'plugin/index.php';
}
function osc_register_plugin($path, $install)
{
}
function osc_add_hook($hook, $callback, $priority = 5)
{
    $GLOBALS['hooks'][$hook][] = $callback;
}
function osc_register_settings_page($id, $spec)
{
    $GLOBALS['pages']++;
}

require __DIR__ . '/../../index.php';

ob_start();
foreach ($hooks['admin_page_header'] ?? array() as $callback) {
    $callback();
}
echo json_encode(array(
    'hooks'    => array_keys($hooks),
    'pages'    => $pages,
    'provider' => interface_exists('mindstellar\\location\\GeocodeProvider', false),
    'notice'   => ob_get_clean(),
));
