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
 * @package rafalmasiarek\Captcha
 */
final readonly class CaptchaResult
{
    /**
     * @param bool $success Whether the provider accepted the token.
     * @param float|null $score Confidence score (0.0-1.0), when the provider/mode returns one.
     * @param string|null $action The action name the token was issued for, when the
     *                            provider/mode returns one.
     * @param list<string> $errorCodes Provider-specific error codes, e.g. "timeout-or-duplicate".
     * @param string|null $challengeTs ISO 8601 timestamp of the challenge, when returned.
     * @param string|null $hostname Hostname the challenge was solved on, when returned.
     */
    public function __construct(
        public bool $success,
        public ?float $score = null,
        public ?string $action = null,
        public array $errorCodes = [],
        public ?string $challengeTs = null,
        public ?string $hostname = null,
    ) {
    }
}
