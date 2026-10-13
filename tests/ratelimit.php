<?php
/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * The two-per-second limiter, on a fake clock.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\locationiq\LocationIqProvider;
use mindstellar\locationiq\RateLimiter;

function limiter(float &$clock, array &$slept, array &$store, int $perSecond = 2): RateLimiter
{
    return new RateLimiter(
        static function (string $key) use (&$store): int {
            return $store[$key] ?? 0;
        },
        static function (string $key, int $n) use (&$store): void {
            $store[$key] = $n;
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
$store = array();
$l     = limiter($clock, $slept, $store);
$l->acquire();
$l->acquire();
pin('two calls in a second do not wait', 0, count($slept));
$l->acquire();
pin('the third waits once', 1, count($slept));
check('it waits for the next second, not longer', $slept[0] >= 900000 && $slept[0] < 1100000, (string) $slept[0]);
pin('the clock moved into the next second', 1001, (int) floor($clock));
pin('the third call counted in the new second', 1, $store['rate:1001']);
$l->acquire();
pin('a fourth call fits the same second', 1, count($slept));

harness_section('other workers');
$clock = 2000.5;
$slept = array();
$store = array('rate:2000' => 2, 'rate:2001' => 2, 'rate:2002' => 2);
limiter($clock, $slept, $store)->acquire();
pin('gives up waiting after a few tries', 3, count($slept));

harness_section('provider uses it');
$clock = 3000.0;
$slept = array();
$store = array();
$http  = new FakeHttp();
for ($i = 0; $i < 3; $i++) {
    $http->answer(200, '[{"lat":"1","lon":"2"}]');
}
$p = new LocationIqProvider('k', 'us1', 'free', $http, limiter($clock, $slept, $store));
$p->geocode('a');
$p->geocode('b');
pin('two lookups, no wait', 0, count($slept));
$p->geocode('c');
pin('third lookup waits', 1, count($slept));
pin('all three were sent', 3, count($http->urls));

harness_section('kv-backed limiter');
$GLOBALS['kv'] = array();
$real          = RateLimiter::forSite(1);
$real->acquire();
check('writes into the locationiq group', isset($GLOBALS['kv']['locationiq']) && count($GLOBALS['kv']['locationiq']) === 1);

exit(harness_result());
