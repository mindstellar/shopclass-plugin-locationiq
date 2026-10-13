<?php

/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace mindstellar\locationiq;

use mindstellar\http\CurlHttpClient;
use mindstellar\location\GeocodeProviderRegistry;

/**
 * The wiring: the provider, its settings and the admin notice.
 */
final class Plugin
{
    /** Settings page id, and the preference section its values live in. */
    public const PAGE = 'locationiq';

    /** Core's preference naming the active provider. Spelled out so uninstall works on an older core. */
    private const CORE_PREF = 'geocode_provider';

    /** Build the provider from the saved settings. */
    public static function provider(): LocationIqProvider
    {
        return new LocationIqProvider(
            (string) osc_get_preference('api_key', self::PAGE),
            (string) osc_get_preference('region', self::PAGE),
            (string) osc_get_preference('plan', self::PAGE),
            new CurlHttpClient(),
            RateLimiter::forSite()
        );
    }

    /** Hook callback: add the provider to core's registry. */
    public static function register(GeocodeProviderRegistry $registry): void
    {
        $registry->register(self::provider());
    }

    /** Nothing to set up: the settings page makes its own preferences. */
    public static function install(): void
    {
    }

    /** Remove the stored settings. */
    public static function uninstall(): void
    {
        // Do not leave core pointing at a provider that is gone.
        if ((string) osc_get_preference(self::CORE_PREF) === LocationIqProvider::ID) {
            osc_set_preference(self::CORE_PREF, '');
        }
        foreach (array('api_key', 'region', 'plan') as $name) {
            osc_delete_preference($name, self::PAGE);
        }
    }

    /** Warn the owner when this plugin is the chosen lookup but has no key. */
    public static function adminNotice(): void
    {
        if ((string) osc_get_preference(self::CORE_PREF) !== LocationIqProvider::ID
            || (string) osc_get_preference('api_key', self::PAGE) !== '') {
            return;
        }
        echo '<div class="flashmessage flashmessage-warning" role="status">'
            . osc_esc_html(__('LocationIQ is chosen for address lookup but has no API key, so no address is looked up.', 'locationiq'))
            . ' <a href="' . osc_esc_html(osc_settings_page_url(self::PAGE)) . '">'
            . osc_esc_html(__('LocationIQ settings', 'locationiq')) . '</a></div>';
    }
}
