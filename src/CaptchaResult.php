<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Result of a CAPTCHA verification call.
 *
 * $score and $action are populated only by providers/modes that return
 * them (reCAPTCHA v3, hCaptcha Enterprise) — null for reCAPTCHA v2,
 * Turnstile, and standard hCaptcha, which have no notion of either.
 *
 * $raw always carries the full decoded response, so a provider-specific
 * field this DTO doesn't name (Turnstile's "cdata"/"metadata", hCaptcha's
 * "credit"/"score_reason", ...) is never lost — reach into $raw for it.
 *
 * @package rafalmasiarek\Captcha
 */
final readonly class CaptchaResult
{
    /**
     * @param bool $success Whether the provider accepted the token.
     * @param float|null $score Confidence score (0.0-1.0), when the provider/mode returns one.
     * @param string|null $action The action name the token was issued for, when the
     *                            provider/mode returns one.
     * @param list<string> $errorCodes Raw provider error codes, e.g. "timeout-or-duplicate" —
     *                                 kept as-is (never filtered) so an unrecognized/future
     *                                 code is never dropped; see knownErrorCodes() to work
     *                                 with the typed subset.
     * @param string|null $challengeTs ISO 8601 timestamp of the challenge, when returned.
     * @param string|null $hostname Hostname the challenge was solved on, when returned.
     * @param array<string, mixed> $raw The full decoded JSON response, unfiltered.
     */
    public function __construct(
        public bool $success,
        public ?float $score = null,
        public ?string $action = null,
        public array $errorCodes = [],
        public ?string $challengeTs = null,
        public ?string $hostname = null,
        public array $raw = [],
    ) {
    }

    /**
     * $errorCodes, filtered to the ones CaptchaErrorCode recognizes. An
     * unrecognized/future provider code is silently skipped here — it's
     * still present in $errorCodes.
     *
     * @return list<CaptchaErrorCode>
     */
    public function knownErrorCodes(): array
    {
        return \array_values(\array_filter(\array_map(
            static fn(string $code): ?CaptchaErrorCode => CaptchaErrorCode::tryFrom($code),
            $this->errorCodes,
        )));
    }

    /**
     * Whether any error code indicates our own integration is misconfigured
     * (wrong/missing secret key, malformed request) rather than the token
     * being legitimately rejected — see CaptchaErrorCode::isConfigurationError().
     *
     * @return bool
     */
    public function isConfigurationError(): bool
    {
        foreach ($this->knownErrorCodes() as $code) {
            if ($code->isConfigurationError()) {
                return true;
            }
        }
        return false;
    }
}
