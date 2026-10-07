<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

/**
 * Error codes documented by Google reCAPTCHA's siteverify API.
 *
 * CaptchaResult::$errorCodes always keeps the raw strings reCAPTCHA sent —
 * an unrecognized/future code is never dropped by using this enum. This
 * exists only to classify the *known* ones: a caller who knows they're
 * dealing with RecaptchaVerifier can map $result->errorCodes through
 * self::tryFrom() to branch without hardcoding string literals.
 *
 * Source: https://developers.google.com/recaptcha/docs/verify
 *
 * @package rafalmasiarek\Captcha\Provider
 */
enum RecaptchaErrorCode: string
{
    /** Our secret key was not sent. */
    case MissingInputSecret = 'missing-input-secret';

    /** Our secret key is malformed or wrong. */
    case InvalidInputSecret = 'invalid-input-secret';

    /** The request itself was malformed (missing required fields). */
    case BadRequest = 'bad-request';

    /** The token/response field was not sent. */
    case MissingInputResponse = 'missing-input-response';

    /** The token is malformed. */
    case InvalidInputResponse = 'invalid-input-response';

    /** The token is too old or was already used. */
    case TimeoutOrDuplicate = 'timeout-or-duplicate';

    /**
     * Whether this code indicates a problem with our own integration (wrong/missing
     * secret key, malformed request) rather than a legitimately rejected token.
     *
     * @return bool
     */
    public function isConfigurationError(): bool
    {
        return match ($this) {
            self::MissingInputSecret, self::InvalidInputSecret, self::BadRequest => true,
            default => false,
        };
    }

    /**
     * Whether this code indicates the token itself was legitimately rejected
     * (expired, reused, malformed) rather than our integration being broken.
     *
     * @return bool
     */
    public function isTokenRejection(): bool
    {
        return match ($this) {
            self::MissingInputResponse, self::InvalidInputResponse, self::TimeoutOrDuplicate => true,
            default => false,
        };
    }
}
