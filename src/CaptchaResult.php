<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Result of a CAPTCHA verification call. Pure data, provider-agnostic —
 * every field here is either universal (success) or "present when the
 * provider happens to return it, null otherwise" (score/action/.../hostname).
 * No knowledge of any specific provider's error-code vocabulary lives here;
 * see each provider's own error-code enum under the Provider\ namespace
 * (e.g. rafalmasiarek\Captcha\Provider\RecaptchaErrorCode) for that.
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
     *                                 always kept as-is; map them through the concrete
     *                                 provider's own error-code enum to classify them.
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
}
