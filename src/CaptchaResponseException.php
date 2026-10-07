<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Thrown when the provider responded, but the response body wasn't valid
 * JSON or wasn't a JSON object — the HTTP request itself succeeded, but its
 * content can't be interpreted.
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaResponseException extends CaptchaVerificationException
{
}
