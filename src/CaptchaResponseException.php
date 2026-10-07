<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Thrown when the provider responded, but the response body wasn't valid
 * JSON or wasn't a JSON object — the HTTP request itself succeeded, but its
 * content can't be interpreted (e.g. the provider returned an HTML error
 * page instead of JSON, often alongside a non-2xx $statusCode).
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaResponseException extends CaptchaVerificationException
{
    /**
     * @param string $message
     * @param int $statusCode HTTP status code of the response, 0 when unknown.
     */
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
    ) {
        parent::__construct($message);
    }
}
