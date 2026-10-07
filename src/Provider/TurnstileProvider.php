<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\CaptchaErrorCategory;
use rafalmasiarek\Captcha\CaptchaProviderInterface;

/**
 * Cloudflare Turnstile configuration.
 *
 * Turnstile-specific response fields not named on CaptchaResult — "cdata"
 * (custom data set via the widget's data-cdata attribute) and
 * "metadata.ephemeral_id" (for Cloudflare's own fraud-detection pipeline) —
 * are always available via CaptchaResult::$raw.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class TurnstileProvider implements CaptchaProviderInterface
{
    /**
     * @param string $secretKey Provider-issued secret key for this site.
     * @param string|null $idempotencyKey See withIdempotencyKey().
     */
    public function __construct(
        private readonly string $secretKey,
        private readonly ?string $idempotencyKey = null,
    ) {
    }

    /**
     * Returns a clone carrying an idempotency key — pass the SAME key across
     * repeated verification attempts of the same token (e.g. a retried
     * request after a transient failure) so Cloudflare does not flag the
     * repeat as token reuse.
     *
     * @param string $idempotencyKey A UUID identifying this specific verification attempt.
     *
     * @return self
     */
    public function withIdempotencyKey(string $idempotencyKey): self
    {
        return new self($this->secretKey, $idempotencyKey);
    }

    /**
     * {@inheritDoc}
     */
    public function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    /**
     * {@inheritDoc}
     */
    public function defaultTokenFieldName(): string
    {
        return 'cf-turnstile-response';
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
        return $this->idempotencyKey !== null ? ['idempotency_key' => $this->idempotencyKey] : [];
    }

    /**
     * {@inheritDoc}
     */
    public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
    {
        $code = TurnstileErrorCode::tryFrom($rawCode);

        return match (true) {
            $code === null => CaptchaErrorCategory::Unknown,
            $code === TurnstileErrorCode::InternalError => CaptchaErrorCategory::ProviderInternalError,
            $code->isConfigurationError() => CaptchaErrorCategory::ConfigurationError,
            $code->isTokenRejection() => CaptchaErrorCategory::TokenRejected,
            default => CaptchaErrorCategory::Unknown,
        };
    }
}
