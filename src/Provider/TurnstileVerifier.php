<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\CaptchaResponseException;
use rafalmasiarek\Captcha\CaptchaResult;
use rafalmasiarek\Captcha\CaptchaTimeoutException;
use rafalmasiarek\Captcha\CaptchaTransportException;
use rafalmasiarek\Captcha\RemoteIpProviderInterface;

/**
 * Verifies a Cloudflare Turnstile token.
 *
 * Turnstile-specific response fields not named on CaptchaResult — "cdata"
 * (custom data set via the widget's data-cdata attribute) and
 * "metadata.ephemeral_id" (for Cloudflare's own fraud-detection pipeline) —
 * are always available via CaptchaResult::$raw.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class TurnstileVerifier extends AbstractSiteVerifyVerifier
{
    /**
     * {@inheritDoc}
     */
    protected function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    /**
     * Verifies a token while supplying an idempotency key — pass the SAME
     * key across repeated verification attempts of the same token (e.g. a
     * retried request after a transient failure) so Cloudflare does not
     * flag the repeat as token reuse. Turnstile-specific; not part of
     * CaptchaVerifierInterface, so a caller needs this concrete class (not
     * just the interface) to use it.
     *
     * @param string $token
     * @param string $idempotencyKey A UUID identifying this specific verification attempt.
     * @param string|RemoteIpProviderInterface|null $remoteIp
     *
     * @throws CaptchaTimeoutException When the provider doesn't respond within the timeout.
     * @throws CaptchaTransportException On any other transport failure.
     * @throws CaptchaResponseException When the response body isn't valid JSON.
     *
     * @return CaptchaResult
     */
    public function verifyWithIdempotencyKey(
        string $token,
        string $idempotencyKey,
        string|RemoteIpProviderInterface|null $remoteIp = null,
    ): CaptchaResult {
        return $this->sendVerification($token, $remoteIp, ['idempotency_key' => $idempotencyKey]);
    }
}
