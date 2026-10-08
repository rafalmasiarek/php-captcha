<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers;

use rafalmasiarek\Captcha\Provider\CaptchaWidgetDescriptor;

/**
 * Template-engine-agnostic HTML/JS helper for rendering a CAPTCHA widget.
 * Holds no provider knowledge — takes a CaptchaWidgetDescriptor built by the
 * Provider class in use (e.g. RecaptchaProvider::widgetV2()). Framework
 * wrappers (e.g. Helpers\Twig\CaptchaExtension) delegate here; usable
 * directly with no template engine.
 *
 * @package rafalmasiarek\Captcha\Helpers
 */
final class HtmlHelper
{
    /**
     * Widget markup: a visible checkbox div, or a hidden input when $widget is invisible.
     *
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $successCallback JS global function name called when solved.
     * @param string $expiredCallback JS global function name called when expired.
     *
     * @return string
     */
    public static function widget(
        CaptchaWidgetDescriptor $widget,
        string $siteKey,
        string $successCallback = 'captchaSuccess',
        string $expiredCallback = 'captchaExpired',
    ): string {
        if ($widget->isInvisible()) {
            return '<input type="hidden" name="' . \htmlspecialchars($widget->tokenFieldName, \ENT_QUOTES)
                . '" class="js-captcha-invisible">';
        }

        return '<div class="' . \htmlspecialchars((string) $widget->widgetCssClass, \ENT_QUOTES) . ' js-captcha"'
            . ' data-sitekey="' . \htmlspecialchars($siteKey, \ENT_QUOTES) . '"'
            . ' data-callback="' . \htmlspecialchars($successCallback, \ENT_QUOTES) . '"'
            . ' data-expired-callback="' . \htmlspecialchars($expiredCallback, \ENT_QUOTES) . '"></div>';
    }

    /**
     * <script> tags: the official widget script plus an inline glue script —
     * button-disable-until-solved for a visible widget, intercept-submit-
     * and-execute for an invisible one.
     *
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $action Only meaningful when $widget is invisible; ignored otherwise.
     * @param string $successCallback Must match widget()'s $successCallback.
     * @param string $expiredCallback Must match widget()'s $expiredCallback.
     *
     * @return string
     */
    public static function scripts(
        CaptchaWidgetDescriptor $widget,
        string $siteKey,
        string $action = '',
        string $successCallback = 'captchaSuccess',
        string $expiredCallback = 'captchaExpired',
    ): string {
        return $widget->isInvisible()
            ? self::invisibleScripts($widget, $siteKey, $action)
            : self::visibleScripts($widget, $successCallback, $expiredCallback);
    }

    /**
     * @param CaptchaWidgetDescriptor $widget
     * @param string $successCallback
     * @param string $expiredCallback
     *
     * @return string
     */
    private static function visibleScripts(
        CaptchaWidgetDescriptor $widget,
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

        return '<script src="' . \htmlspecialchars($widget->scriptUrl, \ENT_QUOTES) . '" async defer></script>'
            . '<script>' . $script . '</script>';
    }

    /**
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $action
     *
     * @return string
     */
    private static function invisibleScripts(CaptchaWidgetDescriptor $widget, string $siteKey, string $action): string
    {
        $glue = $widget->invisibleGlue;
        if ($glue === null) {
            throw new \LogicException('invisibleScripts() called with a widget that has no invisibleGlue.');
        }

        $siteKeyJs = \json_encode($siteKey, \JSON_UNESCAPED_SLASHES);
        $actionJs  = \json_encode($action, \JSON_UNESCAPED_SLASHES);

        $script = self::minifyInlineJs($glue->glueJs($siteKeyJs, $actionJs));

        return '<script src="' . \htmlspecialchars($glue->scriptUrl($siteKey), \ENT_QUOTES) . '"></script>'
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
