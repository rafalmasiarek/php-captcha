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
     * Widget markup: a div with a checkbox (widgetCssClass set), or a hidden
     * input (widgetCssClass null) — plus any extraCssClasses/extraAttributes/
     * extraHiddenFields the descriptor carries.
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
        $extraFields = self::renderExtraHiddenFields($widget);
        $extraAttrs  = self::renderExtraAttributes($widget);

        if ($widget->widgetCssClass === null) {
            return '<input type="hidden" name="' . \htmlspecialchars($widget->tokenFieldName, \ENT_QUOTES)
                . '" class="js-captcha-invisible"' . $extraAttrs . '>' . $extraFields;
        }

        $classes = \implode(' ', [$widget->widgetCssClass, 'js-captcha', ...$widget->extraCssClasses]);

        return '<div class="' . \htmlspecialchars($classes, \ENT_QUOTES) . '"'
            . ' data-sitekey="' . \htmlspecialchars($siteKey, \ENT_QUOTES) . '"'
            . ' data-callback="' . \htmlspecialchars($successCallback, \ENT_QUOTES) . '"'
            . ' data-expired-callback="' . \htmlspecialchars($expiredCallback, \ENT_QUOTES) . '"'
            . $extraAttrs . '></div>' . $extraFields;
    }

    /**
     * <script> tags: the base widget script (scriptUrl + scriptUrlParams)
     * plus glue JS — button-disable-until-solved when widgetCssClass is
     * set, nothing baseline otherwise — followed by any extraJs entries.
     *
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $action Substituted into any "{action}"/"{actionJs}" placeholder.
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
        $scriptUrl = self::buildScriptUrl($widget, $siteKey, $action);

        $script = $widget->widgetCssClass !== null
            ? self::baseGlueJs($successCallback, $expiredCallback)
            : '';

        foreach ($widget->extraJs as $js) {
            $script .= ' ' . self::substitutePlaceholders($js, $siteKey, $action);
        }

        $asyncDefer = $widget->widgetCssClass !== null ? ' async defer' : '';

        return '<script src="' . \htmlspecialchars($scriptUrl, \ENT_QUOTES) . '"' . $asyncDefer . '></script>'
            . '<script>' . self::minifyInlineJs($script) . '</script>';
    }

    /**
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $action
     *
     * @return string $widget->scriptUrl with $widget->scriptUrlParams appended as a query string.
     */
    private static function buildScriptUrl(CaptchaWidgetDescriptor $widget, string $siteKey, string $action): string
    {
        if ($widget->scriptUrlParams === []) {
            return $widget->scriptUrl;
        }

        $pairs = [];
        foreach ($widget->scriptUrlParams as $name => $value) {
            $pairs[] = \rawurlencode($name) . '=' . \rawurlencode(self::substitutePlaceholders($value, $siteKey, $action));
        }

        return $widget->scriptUrl . (\str_contains($widget->scriptUrl, '?') ? '&' : '?') . \implode('&', $pairs);
    }

    /**
     * Replaces "{siteKey}"/"{action}" (raw) and "{siteKeyJs}"/"{actionJs}"
     * (JSON-encoded, safe to embed directly in JS source) placeholders.
     *
     * @param string $template
     * @param string $siteKey
     * @param string $action
     *
     * @return string
     */
    private static function substitutePlaceholders(string $template, string $siteKey, string $action): string
    {
        return \str_replace(
            ['{siteKey}', '{action}', '{siteKeyJs}', '{actionJs}'],
            [
                $siteKey,
                $action,
                \json_encode($siteKey, \JSON_UNESCAPED_SLASHES),
                \json_encode($action, \JSON_UNESCAPED_SLASHES),
            ],
            $template,
        );
    }

    /**
     * @param CaptchaWidgetDescriptor $widget
     *
     * @return string Extra HTML attributes, each preceded by a space.
     */
    private static function renderExtraAttributes(CaptchaWidgetDescriptor $widget): string
    {
        $out = '';
        foreach ($widget->extraAttributes as $name => $value) {
            $out .= ' ' . \htmlspecialchars($name, \ENT_QUOTES) . '="' . \htmlspecialchars($value, \ENT_QUOTES) . '"';
        }

        return $out;
    }

    /**
     * @param CaptchaWidgetDescriptor $widget
     *
     * @return string Extra hidden <input> tags.
     */
    private static function renderExtraHiddenFields(CaptchaWidgetDescriptor $widget): string
    {
        $out = '';
        foreach ($widget->extraHiddenFields as $name => $value) {
            $out .= '<input type="hidden" name="' . \htmlspecialchars($name, \ENT_QUOTES)
                . '" value="' . \htmlspecialchars($value, \ENT_QUOTES) . '">';
        }

        return $out;
    }

    /**
     * Button-disable-until-solved glue for a visible widget: blocks form
     * submission until the official script's data-callback fires.
     *
     * @param string $successCallback
     * @param string $expiredCallback
     *
     * @return string
     */
    private static function baseGlueJs(string $successCallback, string $expiredCallback): string
    {
        $successJs = \json_encode($successCallback, \JSON_UNESCAPED_SLASHES);
        $expiredJs = \json_encode($expiredCallback, \JSON_UNESCAPED_SLASHES);

        return <<<JS
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
        JS;
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
