<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

/**
 * Error codes documented by hCaptcha's siteverify API.
 *
 * CaptchaResult::$errorCodes always keeps the raw strings hCaptcha sent —
 * an unrecognized/future code is never dropped by using this enum. This
 * exists only to classify the *known* ones; HCaptchaProvider uses it
 * internally for CaptchaProviderInterface::classifyErrorCode(). A caller
 * who wants raw-level detail beyond CaptchaResult::$errorCategories can
 * also map $result->errorCodes through self::tryFrom() directly.
 *
 * Source: https://docs.hcaptcha.com/#siteverify-error-codes-table
 *
 * @package rafalmasiarek\Captcha\Provider
 */
enum HCaptchaErrorCode: string
{
    /** Our secret key was not sent. */
    case MissingInputSecret = 'missing-input-secret';

    /** Our secret key is malformed or wrong. */
    case InvalidInputSecret = 'invalid-input-secret';

    /** The request itself was malformed (missing required fields). */
    case BadRequest = 'bad-request';

    /** The secret key doesn't belong to the sitekey used client-side. */
    case SitekeySecretMismatch = 'sitekey-secret-mismatch';

    /** The token/response field was not sent. */
    case MissingInputResponse = 'missing-input-response';

    /** The token is malformed. */
    case InvalidInputResponse = 'invalid-input-response';

    /** The token expired or was already verified — hCaptcha's equivalent of
     *  reCAPTCHA/Turnstile's "timeout-or-duplicate". */
    case InvalidOrAlreadySeenResponse = 'invalid-or-already-seen-response';

    /** Test-mode secret was used without the matching dummy response token. */
    case NotUsingDummyPasscode = 'not-using-dummy-passcode';

    /**
     * Whether this code indicates a problem with our own integration (wrong/missing
     * secret key, malformed request) rather than a legitimately rejected token.
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
     * (expired, reused, malformed) rather than our integration being broken.
     *
     * @return bool
     */
    public function isTokenRejection(): bool
    {
        return match ($this) {
            self::MissingInputResponse, self::InvalidInputResponse, self::InvalidOrAlreadySeenResponse => true,
            default => false,
        };
    }
}
