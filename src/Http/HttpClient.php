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
 * Sends one HTTP request. Tests swap in a fake, so nothing here touches the network.
 */
interface HttpClient
{
    /**
     * The client builds the URL, so the key in $params stays out of the caller's stack.
     *
     * @param array<string,string|int> $params query string values
     *
     * @return array{status:int,body:string}
     * @throws HttpException when no response came back at all
     */
    public function get(string $endpoint, array $params): array;
}
