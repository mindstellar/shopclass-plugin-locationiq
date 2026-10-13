<?php
/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use mindstellar\locationiq\Http\HttpClient;
use mindstellar\locationiq\Http\HttpException;

/** Answers from a script and records every URL. */
final class FakeHttp implements HttpClient
{
    /** @var array<int,array{0:string,1:array}> */
    public array $calls = array();

    /** @var string[] */
    public array $urls = array();

    /** @var array<int,array{status:int,body:string}|HttpException> */
    private array $queue = array();

    public function answer(int $status, string $body): self
    {
        $this->queue[] = array('status' => $status, 'body' => $body);

        return $this;
    }

    public function fail(string $message): self
    {
        $this->queue[] = new HttpException($message);

        return $this;
    }

    public function get(string $endpoint, array $params): array
    {
        $this->calls[] = array($endpoint, $params);
        $this->urls[] = $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $next = array_shift($this->queue) ?? array('status' => 500, 'body' => '');
        if ($next instanceof HttpException) {
            throw $next;
        }

        return $next;
    }
}
