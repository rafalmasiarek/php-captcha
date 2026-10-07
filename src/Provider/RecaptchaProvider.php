<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\CaptchaErrorCategory;
use rafalmasiarek\Captcha\CaptchaProviderInterface;

/**
 * Google reCAPTCHA (v2 or v3) configuration. v2 responses have no
 * score/action; v3 responses populate both — Captcha's generic handling
 * covers this without any v2/v3 distinction needed here, only in which
 * secret key is configured.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class RecaptchaProvider implements CaptchaProviderInterface
{
    /**
     * @param string $secretKey Provider-issued secret key for this site.
     */
    public function __construct(
        private readonly string $secretKey,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
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
    public function defaultTokenFieldName(): string
    {
        return 'g-recaptcha-response';
    }

    /**
     * {@inheritDoc}
     */
    public function extraParams(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
    {
        $code = RecaptchaErrorCode::tryFrom($rawCode);

        return match (true) {
            $code === null => CaptchaErrorCategory::Unknown,
            $code->isConfigurationError() => CaptchaErrorCategory::ConfigurationError,
            $code->isTokenRejection() => CaptchaErrorCategory::TokenRejected,
            default => CaptchaErrorCategory::Unknown,
        };
    }
}
