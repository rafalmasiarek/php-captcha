<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\HttpClient\Http\HttpClientInterface;

/**
 * Verifies an hCaptcha token. Standard responses have no score; hCaptcha
 * Enterprise additionally returns a confidence score, picked up by the
 * base class's generic handling with no code difference here.
 *
 * hCaptcha-specific response fields not named on CaptchaResult — "credit"
 * (whether this solve counts toward hCaptcha's publisher reward program)
 * and Enterprise's "score_reason" (an explanation array for the score) —
 * are always available via CaptchaResult::$raw.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class HCaptchaVerifier extends AbstractSiteVerifyVerifier
{
    /**
     * @param HttpClientInterface $http
     * @param string $secretKey Provider-issued secret key for this site.
     * @param string|null $siteKey Site key to send alongside the token — hCaptcha
     *                             recommends this to disambiguate shared secrets
     *                             across multiple sites. Optional.
     * @param float $timeout Request timeout in seconds.
     */
    public function __construct(
        HttpClientInterface $http,
        string $secretKey,
        private readonly ?string $siteKey = null,
        float $timeout = 10.0,
    ) {
        parent::__construct($http, $secretKey, $timeout);
    }

    /**
     * {@inheritDoc}
     */
    protected function endpoint(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    /**
     * {@inheritDoc}
     */
    protected function extraParams(): array
    {
        return $this->siteKey !== null ? ['sitekey' => $this->siteKey] : [];
    }
}
