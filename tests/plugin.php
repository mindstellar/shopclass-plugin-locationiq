<?php
/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * The wiring, against core's real GeocodeProviderRegistry and Geocoder.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\location\GeocodeProviderRegistry;
use mindstellar\location\Geocoder;
use mindstellar\locationiq\LocationIqProvider;
use mindstellar\locationiq\Plugin;

harness_section('registry');
osc_add_hook(GeocodeProviderRegistry::HOOK, array(Plugin::class, 'register'));
$GLOBALS['prefs']['locationiq'] = array('api_key' => 'pk.test', 'region' => 'eu1');
$registry = GeocodeProviderRegistry::getInstance();
$provider = $registry->get('locationiq');
check('the hook registers the provider', $provider !== null);
pin('and it is the only one', array('locationiq'), array_keys($registry->all()));
check('built from the saved region', str_contains($provider->url('x'), 'eu1.locationiq.com'));

harness_section('active only when chosen and configured');
pin('nothing chosen', null, $registry->active());
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = 'locationiq';
check('chosen and configured', $registry->active() !== null);
$GLOBALS['prefs']['locationiq']['api_key'] = '';
Plugin::register($registry);
pin('chosen without a key', null, $registry->active());
$GLOBALS['prefs']['locationiq']['api_key'] = 'pk.test';

harness_section('admin notice');
$GLOBALS['prefs']['locationiq']['api_key'] = '';
ob_start();
Plugin::adminNotice();
$out = ob_get_clean();
check('warns when chosen with no key', str_contains($out, 'no API key') && str_contains($out, 'flashmessage-warning'));
$GLOBALS['prefs']['locationiq']['api_key'] = 'pk.test';
ob_start();
Plugin::adminNotice();
pin('quiet with a key', '', ob_get_clean());
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = 'other';
$GLOBALS['prefs']['locationiq']['api_key'] = '';
ob_start();
Plugin::adminNotice();
pin('quiet when another provider is chosen', '', ob_get_clean());

harness_section('uninstall');
$GLOBALS['prefs']['locationiq'] = array('api_key' => 'k', 'region' => 'us1');
Plugin::uninstall();
pin('settings removed', array(), $GLOBALS['prefs']['locationiq']);

harness_section('uninstall resets the chosen provider');
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = LocationIqProvider::ID;
Plugin::uninstall();
pin('core no longer points at it', '', $GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF]);
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = 'other';
Plugin::uninstall();
pin('another choice is left alone', 'other', $GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF]);

harness_section('settings page declaration');
$spec = require __DIR__ . '/../settings.php';
$names = array();
foreach ($spec['groups'] as $group) {
    foreach ($group['fields'] as $field) {
        $names[$field['name']] = $field;
    }
}
check('has the key and region settings, and no plan', isset($names['api_key'], $names['region']) && !isset($names['plan']));
pin('the key is a secret', 'secret', $names['api_key']['type']);
pin('a saved key is read back to show it is set', false, $names['api_key']['write_only']);
pin('the key is never printed', true, $names['api_key']['masked']);
pin('a blank box keeps the key', null, ($names['api_key']['persist'])('  '));
pin('a new key is trimmed', 'pk.new', ($names['api_key']['persist'])(' pk.new '));
check('a key with a space is rejected', ($names['api_key']['validate'])('a b') !== null);
pin('a normal key passes', null, ($names['api_key']['validate'])('pk.abc_123-X'));
pin('us1 is the default region', 'us1', $names['region']['default']);
pin('the daily limit defaults to 5,000', '5000', $names['daily_limit']['default']);
check('a core with no way to queue older listings gets no status or older-listings box', !mindstellar\locationiq\Plugin::canQueueExisting() && !isset($names['lookup_status']) && !isset($names['queue_existing']));

harness_section('lookup through core\'s Geocoder');
$GLOBALS['prefs']['osclass'][GeocodeProviderRegistry::PREF] = 'locationiq';
$http     = (new FakeHttp())->answer(200, '[{"lat":"18.5","lon":"73.8"}]');
$custom   = new mindstellar\locationiq\LocationIqProvider('pk.test', 'us1', $http);
if (!function_exists('osc_apply_filter')) {
    function osc_apply_filter($hook, $value = null, ...$args)
    {
        return $value;
    }
}
$geocoder = new Geocoder($custom);
pin('first lookup hits the service', array(18.5, 73.8), $geocoder->geocode('12 Main St, Pune', 'IN'));
pin('second lookup comes from the cache', array(18.5, 73.8), $geocoder->geocode('12 Main St, Pune', 'IN'));
pin('only one request was made', 1, count($http->urls));

exit(harness_result());
