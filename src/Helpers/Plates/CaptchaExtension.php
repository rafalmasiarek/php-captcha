<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\Plates;

use League\Plates\Engine;
use rafalmasiarek\Captcha\Helpers\HtmlHelper;
use rafalmasiarek\Captcha\Provider\CaptchaWidgetDescriptor;

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
                CaptchaWidgetDescriptor $widget,
                string $siteKey,
                ?string $successCallback = null,
                ?string $expiredCallback = null,
                string $instanceId = 'default',
                string $validationMessage = 'Please complete the CAPTCHA.',
            ): string {
                return HtmlHelper::widget($widget, $siteKey, $successCallback, $expiredCallback, $instanceId, $validationMessage);
            }
        );

        $engine->registerFunction(
            'captcha_scripts',
            static function (
                CaptchaWidgetDescriptor $widget,
                string $siteKey,
                string $action = '',
                ?string $successCallback = null,
                ?string $expiredCallback = null,
                string $instanceId = 'default',
                ?string $nonce = null,
            ): string {
                return HtmlHelper::scripts($widget, $siteKey, $action, $successCallback, $expiredCallback, $instanceId, $nonce);
            }
        );
    }
}
