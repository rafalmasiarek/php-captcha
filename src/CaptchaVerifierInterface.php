<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Verifies a CAPTCHA token server-side. One interface for every provider —
 * reCAPTCHA, Cloudflare Turnstile, hCaptcha all speak a near-identical
 * token-verification protocol, so a consumer (a login brute-force guard, a
 * contact form validator, ...) can depend on this interface alone and let
 * configuration pick the concrete provider.
 *
 * Deliberately does not cover widget rendering (script URL, data-* attributes,
 * the form field name the client posts the token under) — that differs per
 * provider and per consuming framework, so it belongs to the caller, not here.
 *
 * @package rafalmasiarek\Captcha
 */
interface CaptchaVerifierInterface
{
    /**
     * @param string $token The token the client submitted (e.g. "g-recaptcha-response",
     *                       "cf-turnstile-response", or "h-captcha-response" form field).
     * @param string|null $remoteIp The end user's IP address, when available. Optional for
     *                              every provider; improves verification accuracy.
     *
     * @throws CaptchaVerificationException When the verification call itself fails
     *                                       (transport error, malformed response) —
     *                                       never thrown for a rejected token.
     *
     * @return CaptchaResult
     */
    public function verify(string $token, ?string $remoteIp = null): CaptchaResult;
}
