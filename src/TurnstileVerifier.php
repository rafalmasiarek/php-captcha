<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Verifies a Cloudflare Turnstile token.
 *
 * @package rafalmasiarek\Captcha
 */
final class TurnstileVerifier extends AbstractSiteVerifyVerifier
{
    /**
     * {@inheritDoc}
     */
    protected function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }
}
