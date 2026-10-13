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

harness_section('default is two a second');
$clock = 4000.2;
$slept = array();
$n     = 0;
$l     = new RateLimiter(static function (int $second) use (&$n): int {
    return ++$n <= 3 ? $n : 1;
}, static fn (): float => $clock, static function (int $m) use (&$clock, &$slept): void {
    $slept[] = $m;
    $clock += 1;
});
$l->acquire();
$l->acquire();
pin('two calls pass at once', 0, count($slept));
$l->acquire();
pin('the third in the same second waits', 1, count($slept));

harness_section('core-backed limiter');
RateLimit::$counts = array();
RateLimit::$calls  = array();
$GLOBALS['usleeps'] = array();
RateLimiter::forSite()->acquire();
pin('counts one call in core\'s counter', 1, count(RateLimit::$counts));
check('under the locationiq context', str_starts_with(array_key_first(RateLimit::$counts), 'locationiq|'));
$call = RateLimit::$calls[0];
pin('context', 'locationiq', $call['context']);
pin('one-second window', 1, $call['windowSeconds']);
pin('fails open', true, $call['failOpen']);
check('counted against the current whole second', is_int($call['now']) && abs($call['now'] - time()) <= 1, (string) $call['now']);
pin('a free slot does not wait', 0, count($GLOBALS['usleeps']));

harness_section('real wait and retry cap');
$GLOBALS['usleeps'] = array();
RateLimit::$calls   = array();
RateLimit::$force   = static fn (int $second): int => 99;
RateLimiter::forSite()->acquire();
pin('a full second makes it wait, three times at most', 3, count($GLOBALS['usleeps']));
pin('and asks the counter each time', 3, count(RateLimit::$calls));
check('each wait is up to a second', max($GLOBALS['usleeps']) <= 1001000 && min($GLOBALS['usleeps']) >= 1000, json_encode($GLOBALS['usleeps']));
$GLOBALS['usleeps'] = array();
$answers            = array(3, 1);
RateLimit::$force   = static function (int $second) use (&$answers): int {
    return array_shift($answers) ?? 1;
};
RateLimiter::forSite()->acquire();
pin('one full second, one wait, then it goes', 1, count($GLOBALS['usleeps']));
$GLOBALS['usleeps'] = array();
RateLimit::$force   = static fn (int $second): ?int => null;
RateLimiter::forSite()->acquire();
pin('an unreachable counter does not wait', 0, count($GLOBALS['usleeps']));

harness_section('Plugin::provider() uses the site limiter');
RateLimit::$force   = null;
RateLimit::$calls   = array();
$GLOBALS['prefs']['locationiq'] = array('api_key' => 'pk.test', 'region' => 'us1', 'plan' => 'free');
$provider = mindstellar\locationiq\Plugin::provider();
$http     = (new FakeHttp())->answer(200, '[{"lat":"1","lon":"2"}]');
$prop     = new ReflectionProperty($provider, 'http');
$prop->setAccessible(true);
$prop->setValue($provider, $http);
$provider->geocode('a');
pin('the lookup moved the counter', 1, count(RateLimit::$calls));
pin('and sent its request', 1, count($http->urls));

exit(harness_result());
