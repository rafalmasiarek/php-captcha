<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers;

use rafalmasiarek\Captcha\Provider\CaptchaWidgetVariant;

/**
 * Generic (template-engine-agnostic) HTML/JS helper for rendering a CAPTCHA
 * widget — no dependency on Twig, Blade, Plates, or anything else. Framework
 * wrappers (e.g. Helpers\Twig\CaptchaExtension) delegate to this class; a
 * caller with no template engine at all can call it directly.
 *
 * @package rafalmasiarek\Captcha\Helpers
 */
final class HtmlHelper
{
    /**
     * Widget markup: a visible checkbox div for RecaptchaV2/Turnstile/
     * HCaptcha, or a hidden input for the invisible RecaptchaV3.
     *
     * @param CaptchaWidgetVariant $variant
     * @param string $siteKey
     * @param string $successCallback JS global function name called when solved.
     * @param string $expiredCallback JS global function name called when expired.
     *
     * @return string
     */
    public static function widget(
        CaptchaWidgetVariant $variant,
        string $siteKey,
        string $successCallback = 'captchaSuccess',
        string $expiredCallback = 'captchaExpired',
    ): string {
        if ($variant->isInvisible()) {
            return '<input type="hidden" name="' . \htmlspecialchars($variant->tokenFieldName(), \ENT_QUOTES)
                . '" class="js-captcha-invisible">';
        }

        return '<div class="' . \htmlspecialchars((string) $variant->widgetCssClass(), \ENT_QUOTES) . ' js-captcha"'
            . ' data-sitekey="' . \htmlspecialchars($siteKey, \ENT_QUOTES) . '"'
            . ' data-callback="' . \htmlspecialchars($successCallback, \ENT_QUOTES) . '"'
            . ' data-expired-callback="' . \htmlspecialchars($expiredCallback, \ENT_QUOTES) . '"></div>';
    }

    /**
     * <script> tags: the official widget script plus an inline glue script —
     * button-disable-until-solved for a visible variant, intercept-submit-
     * and-execute for the invisible one.
     *
     * @param CaptchaWidgetVariant $variant
     * @param string $siteKey
     * @param string $action reCAPTCHA v3 only; ignored by every other variant.
     * @param string $successCallback Must match widget()'s $successCallback.
     * @param string $expiredCallback Must match widget()'s $expiredCallback.
     *
     * @return string
     */
    public static function scripts(
        CaptchaWidgetVariant $variant,
        string $siteKey,
        string $action = '',
        string $successCallback = 'captchaSuccess',
        string $expiredCallback = 'captchaExpired',
    ): string {
        return $variant->isInvisible()
            ? self::invisibleScripts($variant, $siteKey, $action)
            : self::visibleScripts($variant, $successCallback, $expiredCallback);
    }

    /**
     * @param CaptchaWidgetVariant $variant
     * @param string $successCallback
     * @param string $expiredCallback
     *
     * @return string
     */
    private static function visibleScripts(
        CaptchaWidgetVariant $variant,
        string $successCallback,
        string $expiredCallback,
    ): string {
        $successJs = \json_encode($successCallback, \JSON_UNESCAPED_SLASHES);
        $expiredJs = \json_encode($expiredCallback, \JSON_UNESCAPED_SLASHES);

        $script = self::minifyInlineJs(<<<JS
        (function () {
            var el     = document.querySelector('.js-captcha');
            var form   = el ? el.closest('form') : null;
            var btn    = form ? form.querySelector('[type="submit"]') : null;
            var solved = false;

            if (btn) btn.disabled = true;

            var hint = null;
            if (el) {
                hint = document.createElement('div');
                hint.className = 'text-danger small mt-1';
                hint.style.display = 'none';
                hint.textContent = 'Please complete the CAPTCHA.';
                el.insertAdjacentElement('afterend', hint);
            }

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (!solved) {
                        e.preventDefault();
                        if (el)   el.style.outline = '2px solid #dc3545';
                        if (hint) hint.style.display = '';
                    }
                });
            }

            window[{$successJs}] = function () {
                solved = true;
                if (btn)  btn.disabled = false;
                if (el)   el.style.outline = '';
                if (hint) hint.style.display = 'none';
            };
            window[{$expiredJs}] = function () {
                solved = false;
                if (btn)  btn.disabled = true;
                if (el)   el.style.outline = '2px solid #dc3545';
                if (hint) hint.style.display = '';
            };
        }());
        JS);

        return '<script src="' . \htmlspecialchars($variant->scriptUrl(), \ENT_QUOTES) . '" async defer></script>'
            . '<script>' . $script . '</script>';
    }

    /**
     * The grecaptcha.ready()/execute() JS call below is reCAPTCHA v3's own API —
     * correct today only because RecaptchaV3 is the sole isInvisible() variant.
     * A future invisible variant from another provider would need its own
     * glue script here, keyed off $variant.
     *
     * @param CaptchaWidgetVariant $variant
     * @param string $siteKey
     * @param string $action
     *
     * @return string
     */
    private static function invisibleScripts(CaptchaWidgetVariant $variant, string $siteKey, string $action): string
    {
        $siteKeyJs = \json_encode($siteKey, \JSON_UNESCAPED_SLASHES);
        $actionJs  = \json_encode($action, \JSON_UNESCAPED_SLASHES);

        $script = self::minifyInlineJs(<<<JS
        (function () {
            var el   = document.querySelector('.js-captcha-invisible');
            var form = el ? el.closest('form') : null;
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute({$siteKeyJs}, { action: {$actionJs} }).then(function (token) {
                        el.value = token;
                        form.submit();
                    });
                });
            });
        }());
        JS);

        $scriptUrl = $variant->scriptUrl() . '?render=' . \rawurlencode($siteKey);

        return '<script src="' . \htmlspecialchars($scriptUrl, \ENT_QUOTES) . '"></script>'
            . '<script>' . $script . '</script>';
    }

    /**
     * Collapses a multi-line JavaScript snippet to a single line.
     *
     * Trims each line, collapses internal whitespace, drops blank lines.
     * Safe only for simple scripts — no regex literals, no template strings.
     *
     * @param string $js Multi-line JS source.
     *
     * @return string Single-line output.
     */
    private static function minifyInlineJs(string $js): string
    {
        $out = [];
        foreach (\explode("\n", $js) as $line) {
            $line = \preg_replace('/\s+/', ' ', \trim($line));
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return \implode(' ', $out);
    }
}
