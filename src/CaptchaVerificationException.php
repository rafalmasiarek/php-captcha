<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Base type for every exception thrown when a verification call itself
 * fails — never for a legitimate "the token was rejected" outcome, which is
 * a normal CaptchaResult with success=false (see CaptchaResult::$errorCodes
 * and CaptchaResult::$errorCategories for why it was rejected).
 *
 * Thrown directly only when no more specific subtype applies; prefer
 * catching CaptchaTimeoutException / CaptchaTransportException /
 * CaptchaResponseException when the distinction matters, or this base type
 * to catch all of them at once.
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaVerificationException extends \RuntimeException
{
}
