<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Error codes documented by reCAPTCHA, Turnstile, and hCaptcha's siteverify
 * APIs. Values overlap heavily across providers (all three originate from
 * reCAPTCHA's original vocabulary); a few are provider-specific, noted below.
 *
 * CaptchaResult::$errorCodes always keeps the raw strings the provider sent —
 * an unrecognized/future code is never dropped. This enum exists only to
 * classify the *known* ones into something a caller can branch on without
 * hardcoding string literals; see CaptchaResult::isConfigurationError().
 *
 * Sources:
 * - reCAPTCHA: https://developers.google.com/recaptcha/docs/verify
 * - Turnstile: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 * - hCaptcha:  https://docs.hcaptcha.com/#siteverify-error-codes-table
 *
 * @package rafalmasiarek\Captcha
 */
enum CaptchaErrorCode: string
{
    /** Our secret key was not sent. All three providers. */
    case MissingInputSecret = 'missing-input-secret';

    /** Our secret key is malformed or wrong. All three providers. */
    case InvalidInputSecret = 'invalid-input-secret';

    /** The request itself was malformed (missing required fields). All three providers. */
    case BadRequest = 'bad-request';

    /** The secret key doesn't belong to the sitekey used client-side. hCaptcha only. */
    case SitekeySecretMismatch = 'sitekey-secret-mismatch';

    /** The token/response field was not sent. All three providers. */
    case MissingInputResponse = 'missing-input-response';

    /** The token is malformed. All three providers. */
    case InvalidInputResponse = 'invalid-input-response';

    /** The token is too old or was already used. reCAPTCHA and Turnstile. */
    case TimeoutOrDuplicate = 'timeout-or-duplicate';

    /** hCaptcha's equivalent of TimeoutOrDuplicate — token expired or already verified. */
    case InvalidOrAlreadySeenResponse = 'invalid-or-already-seen-response';

    /** The provider itself had a transient problem processing the request. Turnstile only. */
    case InternalError = 'internal-error';

    /** Test-mode secret was used without the matching dummy response token. hCaptcha only. */
    case NotUsingDummyPasscode = 'not-using-dummy-passcode';

    /**
     * Whether this code indicates a problem with our own integration (wrong/missing
     * secret key, malformed request) rather than a legitimately rejected token —
     * worth alerting on or failing loudly, not something a user retrying will fix.
     *
     * @return bool
     */
    public function isConfigurationError(): bool
    {
        return match ($this) {
            self::MissingInputSecret, self::InvalidInputSecret, self::BadRequest, self::SitekeySecretMismatch => true,
            default => false,
        };
    }

    /**
     * Whether this code indicates the token itself was legitimately rejected
     * (expired, reused, malformed) — the normal "user needs to retry the
     * challenge" case, not a sign our integration is broken.
     *
     * @return bool
     */
    public function isTokenRejection(): bool
    {
        return match ($this) {
            self::MissingInputResponse,
            self::InvalidInputResponse,
            self::TimeoutOrDuplicate,
            self::InvalidOrAlreadySeenResponse => true,
            default => false,
        };
    }
}
