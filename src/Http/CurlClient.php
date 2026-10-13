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

namespace mindstellar\locationiq\Http;

/**
 * The real client: curl with short timeouts and certificate checks on.
 */
final class CurlClient implements HttpClient
{
    public function __construct(
        private int $connectTimeout = 3,
        private int $timeout = 5,
        private int $maxBytes = 65536
    ) {
    }

    public function get(string $endpoint, array $params): array
    {
        if (!function_exists('curl_init')) {
            throw new HttpException('The PHP curl extension is not installed');
        }

        $ch = curl_init($endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
        $buffer = new BodyBuffer($this->maxBytes);
        curl_setopt_array($ch, array(
                        CURLOPT_HTTPHEADER     => array('Accept: application/json'),
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION  => static fn ($handle, string $chunk): int => $buffer->append($chunk),
        ));

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($buffer->overflowed()) {
            throw new HttpException('The answer was larger than ' . $this->maxBytes . ' bytes');
        }
        if ($response === false || $status === 0) {
            throw new HttpException('LocationIQ could not be reached: ' . $error);
        }

        return array('status' => $status, 'body' => $buffer->body());
    }
}
