<?php
/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Loads the plugin and core's geocode classes with no database and no network. Core is
 * found through SHOPCLASS_CORE, or a sibling checkout named osclass.
 */

require_once __DIR__ . '/harness.php';

/**
 * Path of a core class file from its 7.0 folder path, such as 'billing/CartPricer.php'. Uses
 * oc-includes/classes when the file is there, else the 7.0 folder oc-includes/osclass/classes.
 */
function core_class_file(string $core, string $rel): string
{
    $top   = strtok($rel, '/');
    $group = array('category' => 'listing/category', 'fields' => 'listing/fields', 'moderation' => 'listing/moderation',
        'auth' => 'user/auth', 'apikey' => 'user/apikey', 'privacy' => 'user/privacy', 'currency' => 'billing/currency',
        'push' => 'messaging/push', 'live' => 'messaging/live', 'image' => 'media/image');
    foreach (array('pages', 'comment', 'widgets', 'menu', 'seo') as $name) {
        $group[$name] = 'content/' . $name;
    }
    foreach (explode(' ', 'boot container event http routing cli database migration cache job mail logger security storage settings install upgrade exception base utility form language theme site model') as $name) {
        $group[$name] = 'core/' . $name;
    }
    $new = rtrim($core, '/') . '/oc-includes/classes/' . ($group[$top] ?? $top) . substr($rel, strlen($top));

    return is_file($new) ? $new : rtrim($core, '/') . '/oc-includes/osclass/classes/' . $rel;
}

function core_path(): string
{
    $path = getenv('SHOPCLASS_CORE') ?: dirname(__DIR__, 3) . '/shopclass';
    if (!is_file(core_class_file($path, 'location/GeocodeProvider.php'))) {
        fwrite(STDERR, "Shopclass core not found at $path. Set SHOPCLASS_CORE to a checkout of 7.0 or later.\n");
        exit(2);
    }

    return rtrim($path, '/');
}

if (!defined('ABS_PATH')) {
    define('ABS_PATH', core_path() . '/');
}

spl_autoload_register(static function (string $class): void {
    foreach (array('mindstellar\\location\\' => 'location', 'mindstellar\\base\\' => 'base', 'mindstellar\\http\\' => 'http', 'mindstellar\\search\\' => 'search', 'mindstellar\\container\\' => 'container') as $prefix => $dir) {
        $file = core_class_file(core_path(), $dir . '/' . substr($class, strlen($prefix)) . '.php');
        if (strncmp($class, $prefix, strlen($prefix)) === 0 && is_file($file)) {
            require $file;
        }
    }
});

spl_autoload_register(static function (string $class): void {
    $prefix = 'mindstellar\\locationiq\\';
    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        require __DIR__ . '/../../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

ini_set('display_errors', '1');

if (!function_exists('__')) {
    function __($text, $domain = '')
    {
        return $text;
    }
}

/** In-memory stand-ins for the core functions the plugin calls. */
$GLOBALS['prefs'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['kv']    = array();

function osc_get_preference($name, $section = 'osclass')
{
    return $GLOBALS['prefs'][$section][$name] ?? '';
}
function osc_set_preference($key, $value = '', $section = 'osclass', $type = 'STRING')
{
    $GLOBALS['prefs'][$section][$key] = $value;
}
function osc_delete_preference($name, $section = 'osclass')
{
    unset($GLOBALS['prefs'][$section][$name]);
}
function osc_kv_get(string $group, string $key, $default = null)
{
    return $GLOBALS['kv'][$group][$key] ?? $default;
}
function osc_kv_set(string $group, string $key, $value, ?int $ttl = null): void
{
    $GLOBALS['kv'][$group][$key] = $value;
}
function osc_add_hook($hook, $callback, $priority = 5)
{
    $GLOBALS['hooks'][$hook][] = $callback;
}
function osc_run_hook($hook, ...$args)
{
    foreach ($GLOBALS['hooks'][$hook] ?? array() as $callback) {
        $callback(...$args);
    }
}
function osc_esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}
function osc_settings_page_url($id)
{
    return 'http://example.test/admin/?page=settings&id=' . $id;
}

require_once __DIR__ . '/fakes.php';
