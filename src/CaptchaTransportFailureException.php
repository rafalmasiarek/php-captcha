<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Base type for CaptchaTimeoutException/CaptchaTransportException — both
 * carry a snapshot of the failed call's transport diagnostics, for a
 * caller that wants to log them (e.g. alongside a Bugsnag report) without
 * having had access to the underlying HttpResponseInterface itself.
 *
 * @package rafalmasiarek\Captcha
 */
abstract class CaptchaTransportFailureException extends CaptchaVerificationException
{
    /**
     * @param string $message
     * @param array<string, mixed> $transportInfo Snapshot of
     *        HttpResponseInterface::getInfo() — e.g. namelookup_time,
     *        connect_time, appconnect_time, total_time, primary_ip, when the
     *        underlying client provides them. Empty when unavailable.
     */
    public function __construct(
        string $message,
        public readonly array $transportInfo = [],
    ) {
        parent::__construct($message);
    }
}
