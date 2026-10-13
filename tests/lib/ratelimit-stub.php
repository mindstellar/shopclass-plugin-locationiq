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
 * Stands in for core's counter, which needs a database. It counts calls per context, key and second.
 */

namespace mindstellar\security;

final class RateLimit
{
    /** @var array<string,int> */
    public static array $counts = array();

    public static function increment(string $context, string $key, int $windowSeconds = 60, bool $failOpen = true, ?int $now = null): ?int
    {
        $id = $context . '|' . $key . '|' . $windowSeconds . '|' . $now;
        self::$counts[$id] = (self::$counts[$id] ?? 0) + 1;

        return self::$counts[$id];
    }
}
