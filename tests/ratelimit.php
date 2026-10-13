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
 * The two-per-second limiter, on a fake clock and a fake counter.
 */

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/ratelimit-stub.php';

use mindstellar\locationiq\LocationIqProvider;
use mindstellar\locationiq\RateLimiter;
use mindstellar\security\RateLimit;

/** A limiter whose counter is a per-second tally, as core's is. */
function limiter(float &$clock, array &$slept, array &$tally, int $perSecond = 2): RateLimiter
{
    return new RateLimiter(
        static function (int $second) use (&$tally): int {
            $tally[$second] = ($tally[$second] ?? 0) + 1;

            return $tally[$second];
        },
        static function () use (&$clock): float {
            return $clock;
        },
        static function (int $micros) use (&$clock, &$slept): void {
            $slept[] = $micros;
            $clock += $micros / 1000000;
        },
        $perSecond
    );
}

harness_section('limiter');
$clock = 1000.1;
$slept = array();
$tally = array();
$l     = limiter($clock, $slept, $tally);
$l->acquire();
$l->acquire();
pin('two calls in a second do not wait', 0, count($slept));
$l->acquire();
pin('the third waits once', 1, count($slept));
check('it waits for the next second, not longer', $slept[0] >= 900000 && $slept[0] < 1100000, (string) $slept[0]);
pin('the clock moved into the next second', 1001, (int) floor($clock));
pin('the third call counted in the new second', 1, $tally[1001]);
$l->acquire();
pin('a fourth call fits the same second', 1, count($slept));

harness_section('other workers');
$clock = 2000.5;
$slept = array();
$tally = array(2000 => 2, 2001 => 2, 2002 => 2);
limiter($clock, $slept, $tally)->acquire();
pin('gives up waiting after a few tries', 3, count($slept));

harness_section('counter unreachable');
$clock = 2500.5;
$slept = array();
(new RateLimiter(static fn (int $s): ?int => null, static fn (): float => $clock, static function (int $m) use (&$slept): void {
    $slept[] = $m;
}))->acquire();
pin('allows the call without waiting', 0, count($slept));

harness_section('provider uses it');
$clock = 3000.0;
$slept = array();
$tally = array();
$http  = new FakeHttp();
for ($i = 0; $i < 3; $i++) {
    $http->answer(200, '[{"lat":"1","lon":"2"}]');
}
$p = new LocationIqProvider('k', 'us1', 'free', $http, limiter($clock, $slept, $tally));
$p->geocode('a');
$p->geocode('b');
pin('two lookups, no wait', 0, count($slept));
$p->geocode('c');
pin('third lookup waits', 1, count($slept));
pin('all three were sent', 3, count($http->urls));

harness_section('core-backed limiter');
RateLimit::$counts = array();
RateLimiter::forSite(1)->acquire();
pin('counts one call in core\'s counter', 1, count(RateLimit::$counts));
check('under the locationiq context', str_starts_with(array_key_first(RateLimit::$counts), 'locationiq|'));

exit(harness_result());
