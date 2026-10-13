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
 * Stands in for core's counter, which needs a database, and for usleep, so no test waits.
 * The counter keeps a tally per second unless a test sets $force.
 */

namespace mindstellar\security {
    final class RateLimit
    {
        /** @var array<string,int> */
        public static array $counts = array();

        /** @var array<int,array<string,mixed>> */
        public static array $calls = array();

        /** @var null|callable(int):?int */
        public static $force = null;

        public static function increment(string $context, string $key, int $windowSeconds = 60, bool $failOpen = true, ?int $now = null): ?int
        {
            self::$calls[] = compact('context', 'key', 'windowSeconds', 'failOpen', 'now');
            if (self::$force !== null) {
                return (self::$force)((int) $now);
            }
            $id                = $context . '|' . $key . '|' . $windowSeconds . '|' . $now;
            self::$counts[$id] = (self::$counts[$id] ?? 0) + 1;

            return self::$counts[$id];
        }
    }
}

namespace mindstellar\locationiq {
    /** Shadows the global usleep for the plugin's own code. */
    function usleep(int $micros): void
    {
        $GLOBALS['usleeps'][] = $micros;
    }
}
