<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Result of a CAPTCHA verification call. Provider-agnostic shape — every
 * field is either universal ($success) or "present when the provider/mode
 * happens to return it, null/empty otherwise".
 *
 * $errorCodes stays raw, provider-specific strings (never filtered —
 * an unrecognized/future code is never dropped). $errorCategories is the
 * provider-agnostic classification of the same facts, via
 * CaptchaProviderInterface::classifyErrorCode() plus Captcha's own
 * $minScore/$expectedAction checks — the field a caller's downstream
 * error-handling/stats code should actually branch on, since it stays the
 * same shape no matter which provider produced it.
 *
 * $raw always carries the full decoded response, so a provider-specific
 * field this DTO doesn't name (Turnstile's "cdata"/"metadata", hCaptcha's
 * "credit"/"score_reason", ...) is never lost.
 *
 * @package rafalmasiarek\Captcha
 */
final readonly class CaptchaResult
{
    /**
     * @param bool $success Whether verification passed overall — the provider accepted the
     *                       token AND (when configured) the score/action checks passed.
     * @param float|null $score Confidence score (0.0-1.0), when the provider/mode returns one.
     * @param string|null $action The action name the token was issued for, when the
     *                            provider/mode returns one.
     * @param list<string> $errorCodes Raw provider error codes, e.g. "timeout-or-duplicate".
     * @param list<CaptchaErrorCategory> $errorCategories The same facts, classified into a
     *                                    shared, provider-agnostic vocabulary.
     * @param string|null $challengeTs ISO 8601 timestamp of the challenge, when returned.
     * @param string|null $hostname Hostname the challenge was solved on, when returned.
     * @param array<string, mixed> $raw The full decoded JSON response, unfiltered.
     */
    public function __construct(
        public bool $success,
        public ?float $score = null,
        public ?string $action = null,
        public array $errorCodes = [],
        public array $errorCategories = [],
        public ?string $challengeTs = null,
        public ?string $hostname = null,
        public array $raw = [],
    ) {
    }

    /**
     * A flat, provider-agnostic snapshot suitable for logging (e.g. as a
     * Bugsnag/PSR-3 context array) — the same keys regardless of which
     * provider produced this result.
     *
     * @return array<string, mixed>
     */
    public function toDebugArray(): array
    {
        return [
            'success'         => $this->success,
            'score'           => $this->score,
            'action'          => $this->action,
            'error_codes'     => $this->errorCodes,
            'error_categories' => \array_map(static fn(CaptchaErrorCategory $c): string => $c->name, $this->errorCategories),
            'challenge_ts'    => $this->challengeTs,
            'hostname'        => $this->hostname,
        ];
    }
}
