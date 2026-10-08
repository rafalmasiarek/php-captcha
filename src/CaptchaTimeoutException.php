<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Thrown when the provider's siteverify endpoint did not respond within the
 * configured timeout.
 *
 * Detection checks HttpResponseInterface::getErrorKind() === TransportErrorKind::Timeout
 * — a structured classification based on curl's own error code (CURLE_OPERATION_TIMEDOUT),
 * not a string match against the free-text getError() message. A transport failure
 * that isn't a timeout is thrown as the less specific CaptchaTransportException instead.
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaTimeoutException extends CaptchaTransportFailureException
{
}
