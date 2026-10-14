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
use mindstellar\listing\ListingGeocode;
use mindstellar\location\GeocodeProviderRegistry;

/**
 * The wiring: the provider, its settings and the admin notice.
 */
final class Plugin
{
    /** Settings page id, and the preference section its values live in. */
    public const PAGE = 'locationiq';

    /** The free plan's daily allowance. */
    public const DEFAULT_DAILY_LIMIT = 5000;

    /** Core's preference naming the active provider. Spelled out so uninstall works on an older core. */
    private const CORE_PREF = 'geocode_provider';

    /** Build the provider from the saved settings. */
    public static function provider(): LocationIqProvider
    {
        return new LocationIqProvider(
            (string) osc_get_preference('api_key', self::PAGE),
            (string) osc_get_preference('region', self::PAGE),
            new CurlHttpClient(),
            RateLimiter::forSite(2, self::dailyLimit())
        );
    }

    /** Requests a day the owner allows; 0 is no limit. */
    public static function dailyLimit(): int
    {
        $limit = osc_get_preference('daily_limit', self::PAGE);

        return $limit === '' || $limit === null ? self::DEFAULT_DAILY_LIMIT : max(0, (int) $limit);
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
        foreach (array('api_key', 'region', 'daily_limit') as $name) {
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
    /** Whether core can queue older listings and report on them (7.0.0 and later). */
    public static function canQueueExisting(): bool
    {
        return method_exists(ListingGeocode::class, 'queueExisting');
    }

    /**
     * Queue the older listings when the owner ticked the box.
     *
     * @param array<string,mixed> $values
     */
    public static function afterSave(array $values): void
    {
        if (empty($values['queue_existing']) || !self::canQueueExisting()) {
            return;
        }
        if (ListingGeocode::queueExisting()) {
            osc_add_flash_ok_message(__('Older listings are being looked up in the background.', 'locationiq'), 'admin');
        } else {
            osc_add_flash_warning_message(__('Pick LocationIQ under Settings > General > Maps > Address lookup and save an API key first.', 'locationiq'), 'admin');
        }
    }

    /** How many listings wait for a position, and whether the daily limit is used up. */
    public static function drawStatus(): void
    {
        $waiting = osc_job_count('pending', ListingGeocode::JOB);
        $older   = osc_job_count('pending', ListingGeocode::BACKFILL) + osc_job_count('running', ListingGeocode::BACKFILL);
        $until   = (int) osc_kv_get(RateLimiter::CONTEXT, 'paused_until', 0);
        echo '<p>' . osc_esc_html(sprintf(__('Listings waiting for a map position: %d', 'locationiq'), $waiting)) . '</p>';
        if ($older > 0) {
            echo '<p>' . osc_esc_html(__('Older listings are being looked up.', 'locationiq')) . '</p>';
        }
        if ($until > time()) {
            echo '<p>' . osc_esc_html(sprintf(__('The request limit is used up. Lookups go on at %s UTC.', 'locationiq'), gmdate('Y-m-d H:i', $until))) . '</p>';
        }
    }
}
