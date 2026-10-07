<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Thrown when the provider's siteverify endpoint could not be reached at
 * all — DNS failure, connection refused, TLS failure, a block_private_network
 * refusal — for any transport failure not recognized as a timeout (see
 * CaptchaTimeoutException).
 *
 * @package rafalmasiarek\Captcha
 */
class CaptchaTransportException extends CaptchaTransportFailureException
{
}
