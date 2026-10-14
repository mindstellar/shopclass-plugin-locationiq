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
use mindstellar\location\GeocodeLimitReached;
use mindstellar\security\RateLimit;

/**
 * Keeps calls under a per-second cap using core's shared counter, and waits for the next
 * second when the cap is reached. Several workers share the count.
 */
final class RateLimiter
{
    public const CONTEXT = 'locationiq';

    /**
     * @param Closure(int):?int $count one more call in this second (whole seconds); the count so far, null when unknown
     * @param Closure():float   $now   seconds, with fractions
     * @param Closure(int):void $sleep wait this many microseconds
     */
    public function __construct(
        private Closure $count,
        private Closure $now,
        private Closure $sleep,
        private int $perSecond = 2,
        private int $perDay = 0,
        private ?Closure $countDay = null
    ) {
    }

    /** A limiter backed by core's counter and the real clock. */
    public static function forSite(int $perSecond = 2, int $perDay = 0): self
    {
        return new self(
            static fn (int $second): ?int => RateLimit::increment(self::CONTEXT, 'search', 1, true, $second),
            static fn (): float => microtime(true),
            static function (int $micros): void {
                usleep($micros);
            },
            $perSecond,
            $perDay,
            static fn (int $now): ?int => RateLimit::increment(self::CONTEXT, 'day', 86400, true, $now)
        );
    }

    /** Take a slot, waiting for the next second if this one is full. */
    public function acquire(): void
    {
        $this->spendDay();
        // Three tries at most: other workers may fill the next second before we get to it.
        for ($tries = 0; $tries < 3; $tries++) {
            $now = ($this->now)();
            $n   = ($this->count)((int) floor($now));
            // An unreachable counter allows the call, as core's own limiter does.
            if ($n === null || $n <= $this->perSecond) {
                return;
            }
            ($this->sleep)((int) ceil((floor($now) + 1 - $now) * 1000000) + 1000);
        }
    }
    /**
     * Count one request against the daily allowance, which renews at midnight UTC.
     *
     * @throws \RuntimeException a GeocodeLimitReached on cores that have it, when the day is used up
     */
    private function spendDay(): void
    {
        if ($this->perDay <= 0 || $this->countDay === null) {
            return;
        }
        $now = (int) floor(($this->now)());
        $n   = ($this->countDay)($now);
        if ($n !== null && $n > $this->perDay) {
            throw self::limitReached('LocationIQ daily request limit reached', self::untilTomorrow($now), $now);
        }
    }

    /** Seconds from $now to just after the next midnight UTC. */
    public static function untilTomorrow(int $now): int
    {
        return 86400 - $now % 86400 + 60;
    }

    /** The "wait, then try again" signal; a plain failure on a core from before it. */
    public static function limitReached(string $message, int $wait, ?int $now = null): \RuntimeException
    {
        if (function_exists('osc_kv_set')) {
            osc_kv_set(self::CONTEXT, 'paused_until', ($now ?? time()) + $wait, $wait);
        }

        return class_exists(GeocodeLimitReached::class) ? new GeocodeLimitReached($message, $wait) : new \RuntimeException($message);
    }
}
