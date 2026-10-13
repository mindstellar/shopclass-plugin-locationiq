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

namespace mindstellar\locationiq\Http;

/**
 * Collects a response body and refuses to grow past a limit.
 */
final class BodyBuffer
{
    private string $body = '';
    private bool $overflowed = false;

    public function __construct(private int $max)
    {
    }

    /** Add a chunk. Returns the bytes taken, or 0 once the limit would be passed, which stops curl. */
    public function append(string $chunk): int
    {
        if (strlen($this->body) + strlen($chunk) > $this->max) {
            $this->overflowed = true;

            return 0;
        }
        $this->body .= $chunk;

        return strlen($chunk);
    }

    public function overflowed(): bool
    {
        return $this->overflowed;
    }

    public function body(): string
    {
        return $this->body;
    }
}
