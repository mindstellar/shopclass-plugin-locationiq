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

use Closure;

/**
 * Keeps calls under a per-second cap by counting them in the key-value store, and waiting
 * for the next second when the cap is reached. Several workers share the count.
 */
final class RateLimiter
{
    public const GROUP = 'locationiq';

    /**
     * @param Closure(string):int      $read   count stored under a key, 0 when none
     * @param Closure(string,int):void $write  store a count that lives a few seconds
     * @param Closure():float          $now    seconds, with fractions
     * @param Closure(int):void        $sleep  wait this many microseconds
     */
    public function __construct(
        private Closure $read,
        private Closure $write,
        private Closure $now,
        private Closure $sleep,
        private int $perSecond = 2
    ) {
    }

    /** A limiter backed by the key-value store and the real clock. */
    public static function forSite(int $perSecond = 2): self
    {
        return new self(
            static fn (string $key): int => (int) osc_kv_get(self::GROUP, $key, 0),
            static function (string $key, int $count): void {
                osc_kv_set(self::GROUP, $key, $count, 5);
            },
            static fn (): float => microtime(true),
            static function (int $micros): void {
                usleep($micros);
            },
            $perSecond
        );
    }

    /** Take a slot, waiting for the next second if this one is full. */
    public function acquire(): void
    {
        // Two waits at most: another worker may fill the next second before we get to it.
        for ($tries = 0; $tries < 3; $tries++) {
            $now = ($this->now)();
            $key = 'rate:' . (int) floor($now);
            $n   = ($this->read)($key);
            if ($n < $this->perSecond) {
                ($this->write)($key, $n + 1);

                return;
            }
            $wait = (int) ceil((floor($now) + 1 - $now) * 1000000) + 1000;
            ($this->sleep)($wait);
        }
    }
}
