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
        private int $perSecond = 2
    ) {
    }

    /** A limiter backed by core's counter and the real clock. */
    public static function forSite(int $perSecond = 2): self
    {
        return new self(
            static fn (int $second): ?int => RateLimit::increment(self::CONTEXT, 'search', 1, true, $second),
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
}
