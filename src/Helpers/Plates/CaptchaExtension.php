<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\Plates;

use League\Plates\Engine;
use rafalmasiarek\Captcha\Helpers\HtmlHelper;
use rafalmasiarek\Captcha\Provider\CaptchaWidgetVariant;

/**
 * Plates extension exposing captcha_widget() and captcha_scripts() template
 * functions, delegating to HtmlHelper.
 *
 * Usage:
 *   \rafalmasiarek\Captcha\Helpers\Plates\CaptchaExtension::register($engine);
 *
 * Template usage:
 *   <?= $this->captcha_widget($variant, $siteKey) ?>
 *   <?= $this->captcha_scripts($variant, $siteKey) ?>
 *
 * @package rafalmasiarek\Captcha\Helpers\Plates
 */
final class CaptchaExtension
{
    /**
     * @param Engine $engine
     *
     * @return void
     */
    public static function register(Engine $engine): void
    {
        $engine->registerFunction(
            'captcha_widget',
            static function (
                CaptchaWidgetVariant $variant,
                string $siteKey,
                string $successCallback = 'captchaSuccess',
                string $expiredCallback = 'captchaExpired',
            ): string {
                return HtmlHelper::widget($variant, $siteKey, $successCallback, $expiredCallback);
            }
        );

        $engine->registerFunction(
            'captcha_scripts',
            static function (
                CaptchaWidgetVariant $variant,
                string $siteKey,
                string $action = '',
                string $successCallback = 'captchaSuccess',
                string $expiredCallback = 'captchaExpired',
            ): string {
                return HtmlHelper::scripts($variant, $siteKey, $action, $successCallback, $expiredCallback);
            }
        );
    }
}
