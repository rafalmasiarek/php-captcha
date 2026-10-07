<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Verifies a CAPTCHA token server-side. One interface for every provider —
 * reCAPTCHA, Cloudflare Turnstile, hCaptcha all speak a near-identical
 * token-verification protocol, so a consumer (a login brute-force guard, a
 * contact form validator, ...) can depend on this interface alone.
 *
 * Captcha is the one general-purpose implementation, parametrized by a
 * CaptchaProviderInterface. A caller with an entirely custom provider that
 * doesn't follow the siteverify protocol at all implements this interface
 * directly instead, bypassing Captcha/CaptchaProviderInterface entirely.
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
     * @param string|RemoteIpProviderInterface|null $remoteIp The end user's real IP address
     *                              (post-trusted-proxy-resolution — e.g. from
     *                              rafalmasiarek/real-ip-resolver's RealIpResolver, not a raw
     *                              $_SERVER['REMOTE_ADDR']), as a string, or an object that
     *                              resolves it lazily via RemoteIpProviderInterface. Optional —
     *                              omitting it uses whatever default the implementation falls
     *                              back to.
     *
     * @throws CaptchaTimeoutException When the provider doesn't respond within the timeout.
     * @throws CaptchaTransportException On any other transport failure.
     * @throws CaptchaResponseException When the response body isn't valid JSON.
     *
     * @return CaptchaResult
     */
    public function verify(string $token, string|RemoteIpProviderInterface|null $remoteIp = null): CaptchaResult;
}
