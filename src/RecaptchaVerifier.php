<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Verifies a Google reCAPTCHA (v2 or v3) token. v2 responses have no
 * score/action; v3 responses populate both via the base class's generic
 * handling — no v2/v3 distinction is needed in code, only in which secret
 * key is configured.
 *
 * @package rafalmasiarek\Captcha
 */
final class RecaptchaVerifier extends AbstractSiteVerifyVerifier
{
    /**
     * {@inheritDoc}
     */
    protected function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }
}
