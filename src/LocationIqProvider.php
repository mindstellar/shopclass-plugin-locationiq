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

use mindstellar\location\GeocodeProvider;
use mindstellar\http\HttpClient;
use mindstellar\http\HttpException;
use RuntimeException;

/**
 * Finds the coordinates of an address through the LocationIQ search API.
 */
final class LocationIqProvider implements GeocodeProvider
{
    public const ID = 'locationiq';

    public const REGIONS = array('us1', 'eu1');

    public const PLAN_FREE = 'free';
    public const PLAN_PAID = 'paid';

    /** The free plan lets results be stored for 48 hours. */
    public const FREE_TTL = 172800;

    public const ATTRIBUTION = '<a href="https://locationiq.com/" rel="noopener">Search by LocationIQ.com</a>';

    /** Short timeouts so a slow service cannot hold up the job, and a cap on the answer. */
    private const HTTP_OPTIONS = array('connect_timeout' => 3, 'timeout' => 5, 'max_bytes' => 65536);

    private string $apiKey;
    private string $region;

    public function __construct(
        string $apiKey,
        string $region,
        private string $plan,
        private HttpClient $http,
        private ?RateLimiter $limiter = null
    ) {
        $this->apiKey = trim($apiKey);
        $this->region = in_array($region, self::REGIONS, true) ? $region : 'us1';
    }

    public function id(): string
    {
        return self::ID;
    }

    public function label(): string
    {
        return 'LocationIQ';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function cacheTtl(): ?int
    {
        return $this->plan === self::PLAN_PAID ? null : self::FREE_TTL;
    }

    public function attribution(): string
    {
        return self::ATTRIBUTION;
    }

    /** The request URL, key included. */
    public function url(string $address, ?string $countryCode = null): string
    {
        $query = array(
            'key'    => $this->apiKey,
            'q'      => $address,
            'format' => 'json',
            'limit'  => 1,
        );
        if ($countryCode !== null && preg_match('/^[A-Za-z]{2}\z/', $countryCode)) {
            $query['countrycodes'] = strtolower($countryCode);
        }


        return 'https://' . $this->region . '.locationiq.com/v1/search' . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function geocode(string $address, ?string $countryCode = null): ?array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('LocationIQ has no API key');
        }

        $this->limiter?->acquire();

        try {
            $response = $this->http->send('GET', $this->url($address, $countryCode), array('Accept' => 'application/json'), null, self::HTTP_OPTIONS);
        } catch (HttpException $e) {
            throw new RuntimeException($this->mask($e->getMessage()));
        }

        $status = $response['status'];
        if ($status === 404) {
            return null;
        }
        if ($status === 401 || $status === 403) {
            throw new RuntimeException('LocationIQ refused the API key (HTTP ' . $status . ')');
        }
        if ($status === 429) {
            // The body names the window: "Rate Limited Second", "Minute" or "Day".
            $day = stripos($response['body'], 'day') !== false;
            throw RateLimiter::limitReached('LocationIQ rate limit reached (HTTP 429)', $day ? RateLimiter::untilTomorrow(time()) : 60);
        }
        if ($status !== 200) {
            throw new RuntimeException('LocationIQ answered HTTP ' . $status);
        }

        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            throw new RuntimeException('LocationIQ sent an answer that is not JSON');
        }
        if ($data === []) {
            return null;
        }
        $first = $data[0] ?? null;
        if (!is_array($first) || !isset($first['lat'], $first['lon']) || !is_numeric($first['lat']) || !is_numeric($first['lon'])) {
            throw new RuntimeException('LocationIQ sent an answer without coordinates');
        }

        return array((float) $first['lat'], (float) $first['lon']);
    }

    private function mask(string $text): string
    {
        return $this->apiKey === '' ? $text : str_replace($this->apiKey, '***', $text);
    }
}
