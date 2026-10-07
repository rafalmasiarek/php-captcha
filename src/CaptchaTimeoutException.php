<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Thrown when the provider's siteverify endpoint did not respond within the
 * configured timeout.
 *
 * Detection is a best-effort match against HttpResponseInterface::getError()'s
 * free-text message (e.g. curl's "Operation timed out after..." string) —
 * rafalmasiarek/http-client does not currently expose a structured transport-
 * error code, only this human-readable string, so this is a heuristic, not a
 * guarantee. A transport failure that isn't recognized as a timeout is thrown
 * as the less specific CaptchaTransportException instead.
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaTimeoutException extends CaptchaTransportFailureException
{
}
