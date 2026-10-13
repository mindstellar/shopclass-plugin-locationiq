<?php
/*
 * This file is part of the LocationIQ Geocoder plugin for Shopclass.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use mindstellar\http\HttpClient;
use mindstellar\http\HttpException;

/** Answers from a script and records every URL. */
final class FakeHttp implements HttpClient
{
    /** @var string[] */
    public array $urls = array();

    /** @var array<int,array<string,mixed>> */
    public array $options = array();

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

    public function send(string $method, string $url, array $headers = [], ?string $body = null, array $options = []): array
    {
        $this->urls[]    = $url;
        $this->options[] = $options;
        $next = array_shift($this->queue) ?? array('status' => 500, 'body' => '');
        if ($next instanceof HttpException) {
            throw $next;
        }

        return array('status' => $next['status'], 'headers' => array(), 'body' => $next['body']);
    }
}
