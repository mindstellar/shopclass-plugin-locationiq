<?php
/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * URL building, parsing, errors, cache lifetime and key handling, against a fake HTTP client.
 */

require __DIR__ . '/lib/bootstrap.php';

use mindstellar\location\GeocodeProvider;
use mindstellar\locationiq\LocationIqProvider;

function make(FakeHttp $http, string $key = 'pk.abc123', string $region = 'us1', string $plan = 'free'): LocationIqProvider
{
    return new LocationIqProvider($key, $region, $plan, $http);
}

function thrown(callable $fn): ?string
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }

    return null;
}

harness_section('identity');
$p = make(new FakeHttp());
check('implements the core interface', $p instanceof GeocodeProvider);
pin('id', 'locationiq', $p->id());
pin('label', 'LocationIQ', $p->label());
pin('configured with a key', true, $p->isConfigured());
pin('not configured without a key', false, make(new FakeHttp(), '')->isConfigured());
pin('a blank key is not a key', false, make(new FakeHttp(), '   ')->isConfigured());

harness_section('cache lifetime');
pin('free plan keeps 48 hours', 172800, make(new FakeHttp(), 'k', 'us1', 'free')->cacheTtl());
pin('paid plan sets no limit (core caps it at a year)', null, make(new FakeHttp(), 'k', 'us1', 'paid')->cacheTtl());
pin('an unknown plan is treated as free', 172800, make(new FakeHttp(), 'k', 'us1', 'x')->cacheTtl());

harness_section('attribution');
$a = $p->attribution();
check('links to LocationIQ', str_contains($a, 'href="https://locationiq.com/"'));
check('says who', str_contains($a, 'LocationIQ.com'));
check('has rel noopener', str_contains($a, 'rel="noopener"'));

harness_section('URL');
$url = $p->url('12 Main St, Pune', 'IN');
check('us1 host and path', str_starts_with($url, 'https://us1.locationiq.com/v1/search?'));
parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
pin('key', 'pk.abc123', $q['key']);
pin('address', '12 Main St, Pune', $q['q']);
pin('format', 'json', $q['format']);
pin('limit', '1', $q['limit']);
pin('country lower-cased', 'in', $q['countrycodes']);
parse_str((string) parse_url(make(new FakeHttp())->url('x'), PHP_URL_QUERY), $q2);
check('no country, no countrycodes', !isset($q2['countrycodes']));
parse_str((string) parse_url($p->url('x', 'India'), PHP_URL_QUERY), $q3);
check('a bad country code is dropped', !isset($q3['countrycodes']));
check('eu1 host', str_starts_with(make(new FakeHttp(), 'k', 'eu1')->url('x'), 'https://eu1.locationiq.com/'));
check('unknown region falls back to us1', str_starts_with(make(new FakeHttp(), 'k', 'evil.example/')->url('x'), 'https://us1.locationiq.com/'));
check('spaces and ampersands are encoded', str_contains($p->url('a & b'), 'q=a%20%26%20b'));

harness_section('answers');
$http = (new FakeHttp())->answer(200, '[{"lat":"18.5204303","lon":"73.8567437","display_name":"Pune"}]');
pin('first result', array(18.5204303, 73.8567437), make($http)->geocode('Pune', 'IN'));
pin('one request', 1, count($http->urls));
pin('no match: 404', null, make((new FakeHttp())->answer(404, '{"error":"Unable to geocode"}'))->geocode('zzzz'));
pin('no match: empty list', null, make((new FakeHttp())->answer(200, '[]'))->geocode('zzzz'));

harness_section('errors throw');
check('429 throws', thrown(fn () => make((new FakeHttp())->answer(429, '{"error":"Rate Limited"}'))->geocode('x')) !== null);
check('500 throws', thrown(fn () => make((new FakeHttp())->answer(500, ''))->geocode('x')) !== null);
check('503 throws', thrown(fn () => make((new FakeHttp())->answer(503, ''))->geocode('x')) !== null);
check('401 throws', thrown(fn () => make((new FakeHttp())->answer(401, '{"error":"Invalid key"}'))->geocode('x')) !== null);
check('403 throws', thrown(fn () => make((new FakeHttp())->answer(403, ''))->geocode('x')) !== null);
check('400 throws', thrown(fn () => make((new FakeHttp())->answer(400, ''))->geocode('x')) !== null);
check('bad JSON throws', thrown(fn () => make((new FakeHttp())->answer(200, '<html>'))->geocode('x')) !== null);
check('missing coordinates throw', thrown(fn () => make((new FakeHttp())->answer(200, '[{"foo":1}]'))->geocode('x')) !== null);
check('unreachable throws', thrown(fn () => make((new FakeHttp())->fail('timeout'))->geocode('x')) !== null);
check('no key throws', thrown(fn () => make(new FakeHttp(), '')->geocode('x')) !== null);

harness_section('the key never leaks');
$msg = (string) thrown(fn () => make((new FakeHttp())->fail('could not connect to https://us1.locationiq.com/v1/search?key=pk.abc123&q=x'))->geocode('x'));
check('curl text is masked', !str_contains($msg, 'pk.abc123') && str_contains($msg, '***'), $msg);
foreach (array(401, 403, 429, 500, 418) as $status) {
    $msg = (string) thrown(fn () => make((new FakeHttp())->answer($status, 'key=pk.abc123'))->geocode('x'));
    check("HTTP $status message has no key", !str_contains($msg, 'pk.abc123'), $msg);
}

harness_section('hardening');
function caught(callable $fn): ?Throwable
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        return $e;
    }

    return null;
}
$e = caught(fn () => make((new FakeHttp())->fail('boom pk.abc123'))->geocode('x'));
pin('the HTTP error is not chained', null, $e->getPrevious());
check('no stack frame argument holds the key', !str_contains(json_encode(array_map(fn ($f) => $f['args'] ?? array(), $e->getTrace()), JSON_PARTIAL_OUTPUT_ON_ERROR), 'pk.abc123'));
check('the trace text has no key', !str_contains($e->getTraceAsString(), 'pk.abc123'));
$spy = new FakeHttp();
$spy->answer(200, '[]');
make($spy)->geocode('addr', 'IN');
pin('timeouts and size cap are passed', array('connect_timeout' => 3, 'timeout' => 5, 'max_bytes' => 65536), $spy->options[0]);


harness_section('no key');
$spy = new FakeHttp();
check('geocode without a key throws', thrown(fn () => make($spy, '')->geocode('x', 'IN')) !== null);
pin('and sends no request', 0, count($spy->urls));

harness_section('region');
foreach (array('us2', 'ap1', 'EU1', 'us1.evil.com', '', 'us1/../x') as $region) {
    check("region '$region' gives the us1 host", str_starts_with(make(new FakeHttp(), 'k', $region)->url('x'), 'https://us1.locationiq.com/v1/search?'), $region);
}

exit(harness_result());
