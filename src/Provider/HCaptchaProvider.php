<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\CaptchaErrorCategory;
use rafalmasiarek\Captcha\CaptchaProviderInterface;

/**
 * hCaptcha configuration. Standard responses have no score; hCaptcha
 * Enterprise additionally returns a confidence score, picked up by
 * Captcha's generic handling with no code difference here.
 *
 * hCaptcha-specific response fields not named on CaptchaResult — "credit"
 * (whether this solve counts toward hCaptcha's publisher reward program)
 * and Enterprise's "score_reason" (an explanation array for the score) —
 * are always available via CaptchaResult::$raw.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class HCaptchaProvider implements CaptchaProviderInterface
{
    /**
     * @param string $secretKey Provider-issued secret key for this site.
     * @param string|null $siteKey Site key to send alongside the token — hCaptcha
     *                             recommends this to disambiguate shared secrets
     *                             across multiple sites. Optional.
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly ?string $siteKey = null,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function endpoint(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    /**
     * {@inheritDoc}
     */
    public function defaultTokenFieldName(): string
    {
        return 'h-captcha-response';
    }

    /**
     * {@inheritDoc}
     */
    public function secretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * {@inheritDoc}
     */
    public function extraParams(): array
    {
        return $this->siteKey !== null ? ['sitekey' => $this->siteKey] : [];
    }

    /**
     * {@inheritDoc}
     */
    public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
    {
        $code = HCaptchaErrorCode::tryFrom($rawCode);

        return match (true) {
            $code === null => CaptchaErrorCategory::Unknown,
            $code->isConfigurationError() => CaptchaErrorCategory::ConfigurationError,
            $code->isTokenRejection() => CaptchaErrorCategory::TokenRejected,
            default => CaptchaErrorCategory::Unknown,
        };
    }
}
