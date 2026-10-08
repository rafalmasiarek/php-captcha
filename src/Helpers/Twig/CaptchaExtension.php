<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\Twig;

use rafalmasiarek\Captcha\Helpers\HtmlHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension providing {{ captcha_widget(variant, siteKey) }} and
 * {{ captcha_scripts(variant, siteKey, action) }} — thin wrappers around
 * HtmlHelper, which has no Twig dependency of its own. Not autoloaded by
 * anything in this package unless a consumer actually instantiates it, so
 * twig/twig is never required just because this file exists — a consumer
 * who wants it already has Twig in their own project.
 *
 * $variant is a rafalmasiarek\Captcha\Provider\CaptchaWidgetVariant case,
 * passed into the template context by the caller (e.g.
 * CaptchaWidgetVariant::RecaptchaV2) — Twig renders it like any other
 * context variable.
 *
 * @package rafalmasiarek\Captcha\Helpers\Twig
 */
final class CaptchaExtension extends AbstractExtension
{
    /**
     * {@inheritDoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('captcha_widget', [HtmlHelper::class, 'widget'], ['is_safe' => ['html']]),
            new TwigFunction('captcha_scripts', [HtmlHelper::class, 'scripts'], ['is_safe' => ['html']]),
        ];
    }
}
