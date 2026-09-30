<?php

declare(strict_types=1);

namespace VoiceKit\Http;

use VoiceKit\VoiceKitError;

/**
 * Sends HTTP requests. Swap in your own implementation to route the SDK through
 * a proxy, a PSR-7/PSR-18 stack, or a test double.
 *
 * ```php
 * $client = new VoiceKitClient('rtt_…', transport: new MyTransport());
 * ```
 */
interface Transport
{
    /**
     * @throws VoiceKitError on network failures (no response was received).
     */
    public function send(Request $request): Response;
}
