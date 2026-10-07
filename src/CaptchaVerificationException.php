<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Thrown when a verification call itself fails (transport error, malformed
 * JSON response). Never thrown for a legitimate "the token was rejected"
 * outcome — that is a normal CaptchaResult with success=false.
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaVerificationException extends \RuntimeException
{
}
