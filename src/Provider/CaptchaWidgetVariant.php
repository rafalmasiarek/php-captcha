<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

/**
 * Raw client-side widget metadata for each of the four widget shapes a
 * caller might render — script URL, the CSS class the official script looks
 * for, whether it has no visible checkbox, and the form field name its
 * token arrives under. Pure data, no HTML/JS generation — see
 * rafalmasiarek\Captcha\Helpers\HtmlHelper for that.
 *
 * Deliberately separate from CaptchaProviderInterface: that interface's
 * granularity is the verification protocol (3 providers — RecaptchaProvider
 * covers both v2 and v3, since siteverify doesn't distinguish them), while
 * widget rendering needs 4 variants — v2 and v3 render completely
 * differently client-side despite verifying through the same provider.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
enum CaptchaWidgetVariant
{
    case RecaptchaV2;
    case RecaptchaV3;
    case Turnstile;
    case HCaptcha;

    /**
     * @return string Absolute URL of the official widget script.
     */
    public function scriptUrl(): string
    {
        return match ($this) {
            self::RecaptchaV2, self::RecaptchaV3 => 'https://www.google.com/recaptcha/api.js',
            self::Turnstile => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
            self::HCaptcha => 'https://js.hcaptcha.com/1/api.js',
        };
    }

    /**
     * @return string|null CSS class the official script looks for on the
     *                      widget div, or null for a variant with no
     *                      visible widget (reCAPTCHA v3).
     */
    public function widgetCssClass(): ?string
    {
        return match ($this) {
            self::RecaptchaV2 => 'g-recaptcha',
            self::RecaptchaV3 => null,
            self::Turnstile => 'cf-turnstile',
            self::HCaptcha => 'h-captcha',
        };
    }

    /**
     * @return bool Whether this variant has no visible checkbox widget.
     */
    public function isInvisible(): bool
    {
        return $this === self::RecaptchaV3;
    }

    /**
     * @return string The HTML form field name this variant's token arrives under.
     */
    public function tokenFieldName(): string
    {
        return match ($this) {
            self::RecaptchaV2, self::RecaptchaV3 => 'g-recaptcha-response',
            self::Turnstile => 'cf-turnstile-response',
            self::HCaptcha => 'h-captcha-response',
        };
    }

    /**
     * @return string The verification provider key this variant maps to —
     *                'recaptcha'|'turnstile'|'hcaptcha'. Both reCAPTCHA
     *                variants map to the same verification provider.
     */
    public function providerName(): string
    {
        return match ($this) {
            self::RecaptchaV2, self::RecaptchaV3 => 'recaptcha',
            self::Turnstile => 'turnstile',
            self::HCaptcha => 'hcaptcha',
        };
    }
}
