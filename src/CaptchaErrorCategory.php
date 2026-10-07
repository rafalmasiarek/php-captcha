<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Provider-agnostic classification of why a verification was rejected.
 * CaptchaResult::$errorCategories always uses this enum regardless of which
 * provider was used — a caller that reads it (e.g. for stats/debug logging)
 * never needs to change when a different provider is configured, unlike
 * CaptchaResult::$errorCodes, which are raw, provider-specific strings.
 *
 * ConfigurationError/TokenRejected/ProviderInternalError come from a
 * provider's own CaptchaProviderInterface::classifyErrorCode() (each
 * provider maps its own vocabulary into this shared vocabulary).
 * ScoreTooLow/ActionMismatch are computed by Captcha itself from the
 * $minScore/$expectedAction checks — not something any provider's API
 * reports directly.
 *
 * @package rafalmasiarek\Captcha
 */
enum CaptchaErrorCategory
{
    /** Our own integration is misconfigured (wrong/missing secret, malformed request) —
     *  fix the integration, not something a user retry will resolve. */
    case ConfigurationError;

    /** The token itself was legitimately rejected (expired, reused, malformed). */
    case TokenRejected;

    /** The provider itself had a transient problem processing the request. */
    case ProviderInternalError;

    /** The token verified, but its score was below the configured minimum. */
    case ScoreTooLow;

    /** The token verified, but its action didn't match the configured expectation. */
    case ActionMismatch;

    /** A raw error code the provider sent that isn't recognized. */
    case Unknown;
}
