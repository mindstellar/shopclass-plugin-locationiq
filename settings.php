<?php

/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use mindstellar\locationiq\LocationIqProvider;
use mindstellar\locationiq\Plugin;

/*
 * The settings page declaration. Core renders the form, checks CSRF and the admin's
 * capability, validates each field and stores it under the "locationiq" section.
 */

return array(
    'title'  => __('LocationIQ Geocoder', 'locationiq'),
    'menu'   => 'plugins',
    'intro'  => __('Finds the exact map position of a listing from its street address. Pick LocationIQ under Settings > General > Maps > Address lookup to use it.', 'locationiq'),
    'after_save' => array(Plugin::class, 'afterSave'),
    'groups' => array(
        array(
            'title'  => __('Account', 'locationiq'),
            'fields' => array(
                array(
                    'type'        => 'secret',
                    'name'        => 'api_key',
                    'label'       => __('API key', 'locationiq'),
                    'help'        => __('The access token from your LocationIQ dashboard. It is never shown again; leave the box blank to keep the saved key.', 'locationiq'),
                    'write_only'  => true,
                    'masked'      => true,
                    'attrs'       => array('autocomplete' => 'new-password', 'spellcheck' => 'false'),
                    'persist'     => static fn ($value) => trim((string) $value) === '' ? null : trim((string) $value),
                    'validate'    => static fn ($value) => preg_match('/^[A-Za-z0-9._-]*$/', trim((string) $value)) ? null : __('This does not look like a LocationIQ key', 'locationiq'),
                ),
                array(
                    'type'    => 'radio',
                    'name'    => 'region',
                    'label'   => __('Region', 'locationiq'),
                    'help'    => __('The server your lookups go to. Pick the one nearer your site.', 'locationiq'),
                    'options' => array(
                        'us1' => __('United States (us1)', 'locationiq'),
                        'eu1' => __('Europe (eu1)', 'locationiq'),
                    ),
                    'default' => 'us1',
                ),
                array(
                    'type'    => 'radio',
                    'name'    => 'plan',
                    'label'   => __('Plan', 'locationiq'),
                    'help'    => __('The free plan allows 2 requests a second and lets answers be stored for 48 hours; after that an address is looked up again. A paid plan lets answers be kept, up to a year.', 'locationiq'),
                    'options' => array(
                        LocationIqProvider::PLAN_FREE => __('Free', 'locationiq'),
                        LocationIqProvider::PLAN_PAID => __('Paid', 'locationiq'),
                    ),
                    'default' => LocationIqProvider::PLAN_FREE,
                ),
            ),
        ),
        array(
            'title'  => __('Lookups', 'locationiq'),
            // Older cores have no way to queue older listings; those two fields stay away there.
            'fields' => array_merge(array(
                array(
                    'type'    => 'number',
                    'name'    => 'daily_limit',
                    'label'   => __('Daily request limit', 'locationiq'),
                    'help'    => __('Lookups a day, counted from midnight UTC; 0 is no limit. When it is used up, lookups wait until the next day. The free plan allows 5,000.', 'locationiq'),
                    'default' => (string) Plugin::DEFAULT_DAILY_LIMIT,
                    'attrs'   => array('min' => '0', 'step' => '1'),
                ),
            ), Plugin::canQueueExisting() ? array(
                array(
                    'type'   => 'custom',
                    'name'   => 'lookup_status',
                    'label'  => __('Status', 'locationiq'),
                    'render' => array(Plugin::class, 'drawStatus'),
                ),
                array(
                    'type'       => 'checkbox',
                    'name'       => 'queue_existing',
                    'label'      => __('Older listings', 'locationiq'),
                    'help'       => __('Look up every listing that has a street address and no map position yet. They are looked up in the background, within the daily limit.', 'locationiq'),
                    'persist'    => false,
                    'write_only' => true,
                ),
            ) : array()),
        ),
        array(
            'title'  => __('Credit', 'locationiq'),
            'fields' => array(
                array(
                    'type'   => 'custom',
                    'name'   => 'attribution_info',
                    'label'  => __('Attribution', 'locationiq'),
                    'render' => static function (): void {
                        echo '<p>' . osc_esc_html(__('The free plan requires a visible link to LocationIQ wherever its data is shown. Print it in your theme with osc_geocode_attribution(); it reads:', 'locationiq')) . '</p>';
                        echo '<p><code>' . osc_esc_html(LocationIqProvider::ATTRIBUTION) . '</code></p>';
                    },
                ),
            ),
        ),
    ),
);
