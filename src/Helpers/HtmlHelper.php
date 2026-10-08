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
 * $instanceId correlates one widget()/scripts() pair so multiple widgets on
 * the same page never share DOM lookups or window-global callback names —
 * pass the SAME $instanceId to both calls for one widget; pass a distinct
 * one per widget when rendering more than one on a page. The 'default'
 * default covers the common single-widget-per-page case with zero config.
 *
 * No CSS framework assumptions: feedback is a plain `.captcha-feedback`
 * element (role="alert", aria-live), toggled via a `.captcha-invalid` class
 * on the widget element — style both however your app likes. Nothing here
 * disables a submit button; it gates the form's submit event instead, so
 * disabled-state ownership always stays with the host application.
 *
 * @package rafalmasiarek\Captcha\Helpers
 */
final class HtmlHelper
{
    /** Placeholders valid only in CaptchaWidgetDescriptor::$scriptUrlParams (raw, URL-encoded by HtmlHelper). */
    public const ALLOWED_URL_TOKENS = ['__CAPTCHA_SITE_KEY__', '__CAPTCHA_ACTION__'];

    /** Placeholders valid only in CaptchaWidgetDescriptor::$extraJs (JSON-encoded, safe to embed in JS source). */
    public const ALLOWED_JS_TOKENS = ['__CAPTCHA_SITE_KEY_JS__', '__CAPTCHA_ACTION_JS__', '__CAPTCHA_INSTANCE_ID_JS__'];

    // Case-insensitive on the middle segment deliberately: a miscased attempt
    // (e.g. "__CAPTCHA_site_key__") must still be DETECTED as a token-shaped
    // string so it gets rejected by assertKnownTokens() below, rather than
    // silently passing through unrecognized as ordinary text.
    private const TOKEN_PATTERN = '/__CAPTCHA_[A-Za-z0-9_]+__/';

    private const INSTANCE_ID_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * Widget markup: a div with a checkbox (widgetCssClass set) or a hidden
     * input (widgetCssClass null), plus extraCssClasses/extraAttributes/
     * extraHiddenFields. A visible widget also gets an adjacent, initially
     * hidden feedback element (role="alert") toggled by scripts()'s glue JS.
     *
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string|null $successCallback JS global function name called when solved.
     *                                      Defaults to a name derived from $instanceId.
     * @param string|null $expiredCallback JS global function name called when expired.
     *                                      Defaults to a name derived from $instanceId.
     * @param string $instanceId Correlates with the scripts() call for this same widget.
     *                            Must match "/^[a-zA-Z0-9_-]+$/". Give each widget on a
     *                            page a distinct value.
     * @param string $validationMessage Feedback text shown when a visible widget is
     *                                   submitted unsolved. Not imposed — pass your own
     *                                   localized string.
     *
     * @return string
     *
     * @throws \InvalidArgumentException When $instanceId doesn't match the required pattern.
     */
    public static function widget(
        CaptchaWidgetDescriptor $widget,
        string $siteKey,
        ?string $successCallback = null,
        ?string $expiredCallback = null,
        string $instanceId = 'default',
        string $validationMessage = 'Please complete the CAPTCHA.',
    ): string {
        self::assertSafeInstanceId($instanceId);

        $extraFields  = self::renderExtraHiddenFields($widget);
        $extraAttrs   = self::renderExtraAttributes($widget);
        $instanceAttr = ' data-captcha-instance="' . \htmlspecialchars($instanceId, \ENT_QUOTES) . '"';

        if ($widget->widgetCssClass === null) {
            $classes = \implode(' ', ['js-captcha-invisible', ...$widget->extraCssClasses]);

            return '<input type="hidden" name="' . \htmlspecialchars($widget->tokenFieldName, \ENT_QUOTES)
                . '" class="' . \htmlspecialchars($classes, \ENT_QUOTES) . '"'
                . $instanceAttr . $extraAttrs . '>' . $extraFields;
        }

        $successCallback ??= 'captchaSuccess_' . $instanceId;
        $expiredCallback ??= 'captchaExpired_' . $instanceId;
        $feedbackId = 'captcha-feedback-' . $instanceId;

        $classes = \implode(' ', [$widget->widgetCssClass, 'js-captcha', ...$widget->extraCssClasses]);

        $main = '<div class="' . \htmlspecialchars($classes, \ENT_QUOTES) . '"'
            . ' data-sitekey="' . \htmlspecialchars($siteKey, \ENT_QUOTES) . '"'
            . ' data-callback="' . \htmlspecialchars($successCallback, \ENT_QUOTES) . '"'
            . ' data-expired-callback="' . \htmlspecialchars($expiredCallback, \ENT_QUOTES) . '"'
            . $instanceAttr
            . ' aria-describedby="' . \htmlspecialchars($feedbackId, \ENT_QUOTES) . '"'
            . $extraAttrs . '></div>';

        $feedback = '<div id="' . \htmlspecialchars($feedbackId, \ENT_QUOTES) . '" class="captcha-feedback"'
            . ' role="alert" aria-live="assertive" hidden>' . \htmlspecialchars($validationMessage, \ENT_QUOTES) . '</div>';

        return $main . $feedback . $extraFields;
    }

    /**
     * <script> tags: the base widget script (scriptUrl + scriptUrlParams)
     * plus glue JS — submit-gating-until-solved when widgetCssClass is set,
     * nothing baseline otherwise — followed by any extraJs entries, each
     * joined as an independent statement (not concatenated mid-expression).
     *
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $action Substituted into any __CAPTCHA_ACTION__/__CAPTCHA_ACTION_JS__ token.
     * @param string|null $successCallback Must match widget()'s $successCallback.
     * @param string|null $expiredCallback Must match widget()'s $expiredCallback.
     * @param string $instanceId Must match widget()'s $instanceId for this same widget.
     * @param string|null $nonce CSP nonce applied to both emitted <script> tags, when set.
     *
     * @return string
     *
     * @throws \InvalidArgumentException When $instanceId is invalid, or a descriptor value
     *                                    uses an unrecognized or wrong-context placeholder token.
     */
    public static function scripts(
        CaptchaWidgetDescriptor $widget,
        string $siteKey,
        string $action = '',
        ?string $successCallback = null,
        ?string $expiredCallback = null,
        string $instanceId = 'default',
        ?string $nonce = null,
    ): string {
        self::assertSafeInstanceId($instanceId);

        $scriptUrl = self::buildScriptUrl($widget, $siteKey, $action);

        $parts = [];
        if ($widget->widgetCssClass !== null) {
            $successCallback ??= 'captchaSuccess_' . $instanceId;
            $expiredCallback ??= 'captchaExpired_' . $instanceId;
            $parts[] = self::baseGlueJs($successCallback, $expiredCallback, $instanceId);
        }
        foreach ($widget->extraJs as $js) {
            $parts[] = self::substituteJsTokens($js, $siteKey, $action, $instanceId);
        }

        $script = \implode("\n;\n", $parts);

        $nonceAttr  = $nonce !== null ? ' nonce="' . \htmlspecialchars($nonce, \ENT_QUOTES) . '"' : '';
        $asyncDefer = $widget->widgetCssClass !== null ? ' async defer' : '';

        return '<script src="' . \htmlspecialchars($scriptUrl, \ENT_QUOTES) . '"' . $asyncDefer . $nonceAttr . '></script>'
            . '<script' . $nonceAttr . '>' . $script . '</script>';
    }

    /**
     * @param string $instanceId
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function assertSafeInstanceId(string $instanceId): void
    {
        if (!\preg_match(self::INSTANCE_ID_PATTERN, $instanceId)) {
            throw new \InvalidArgumentException('instanceId must match "/^[a-zA-Z0-9_-]+$/", got: "' . $instanceId . '".');
        }
    }

    /**
     * Scans $template for every "__CAPTCHA_..._​__"-shaped token and rejects
     * any that isn't in $allowed — catches both a typo (e.g. "__CAPTCHA_SITEKEY__")
     * and a right-token-wrong-context mistake (a JS token in a URL field, or
     * vice versa) without misfiring on ordinary JS object/block syntax, since
     * the token shape doesn't collide with "{...}" or any real JS syntax.
     *
     * @param string $template
     * @param list<string> $allowed
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function assertKnownTokens(string $template, array $allowed): void
    {
        \preg_match_all(self::TOKEN_PATTERN, $template, $matches);
        foreach ($matches[0] as $token) {
            if (!\in_array($token, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "Unknown or wrong-context placeholder \"{$token}\" — allowed here: " . \implode(', ', $allowed) . '.'
                );
            }
        }
    }

    /**
     * @param CaptchaWidgetDescriptor $widget
     * @param string $siteKey
     * @param string $action
     *
     * @return string $widget->scriptUrl with $widget->scriptUrlParams appended as a query string.
     *
     * @throws \InvalidArgumentException
     */
    private static function buildScriptUrl(CaptchaWidgetDescriptor $widget, string $siteKey, string $action): string
    {
        if ($widget->scriptUrlParams === []) {
            return $widget->scriptUrl;
        }

        $pairs = [];
        foreach ($widget->scriptUrlParams as $name => $value) {
            self::assertKnownTokens($value, self::ALLOWED_URL_TOKENS);
            $resolved = \str_replace(self::ALLOWED_URL_TOKENS, [$siteKey, $action], $value);
            $pairs[] = \rawurlencode($name) . '=' . \rawurlencode($resolved);
        }

        return $widget->scriptUrl . (\str_contains($widget->scriptUrl, '?') ? '&' : '?') . \implode('&', $pairs);
    }

    /**
     * Replaces ALLOWED_JS_TOKENS with JSON-encoded, <script>-safe values.
     *
     * @param string $template
     * @param string $siteKey
     * @param string $action
     * @param string $instanceId
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private static function substituteJsTokens(string $template, string $siteKey, string $action, string $instanceId): string
    {
        self::assertKnownTokens($template, self::ALLOWED_JS_TOKENS);

        return \str_replace(
            self::ALLOWED_JS_TOKENS,
            [self::jsonForJs($siteKey), self::jsonForJs($action), self::jsonForJs($instanceId)],
            $template,
        );
    }

    /**
     * JSON-encodes a value for direct embedding in inline <script> text —
     * hex-escapes "<", ">", "&", "'", "\"" so an attacker-influenced value
     * (e.g. $action from request input) can never prematurely close the
     * surrounding <script> element or break out of the string literal.
     *
     * @param string $value
     *
     * @return string
     */
    private static function jsonForJs(string $value): string
    {
        return \json_encode(
            $value,
            \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR,
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
     * Submit-gating glue for a visible widget: blocks form submission and
     * shows the adjacent feedback element until the official script's
     * data-callback fires. Never touches any button's disabled state —
     * disabling ownership stays entirely with the host application.
     *
     * @param string $successCallback
     * @param string $expiredCallback
     * @param string $instanceId Already validated by the caller.
     *
     * @return string
     */
    private static function baseGlueJs(string $successCallback, string $expiredCallback, string $instanceId): string
    {
        $successJs = self::jsonForJs($successCallback);
        $expiredJs = self::jsonForJs($expiredCallback);
        $instanceJs = self::jsonForJs($instanceId);

        return <<<JS
        (function () {
            var el     = document.querySelector('[data-captcha-instance="' + {$instanceJs} + '"]');
            var form   = el ? el.closest('form') : null;
            var hint   = document.querySelector('#captcha-feedback-' + {$instanceJs});
            var solved = false;

            if (form) {
                form.addEventListener('submit', function (e) {
                    if (!solved) {
                        e.preventDefault();
                        if (el)   { el.classList.add('captcha-invalid'); el.setAttribute('aria-invalid', 'true'); }
                        if (hint) { hint.hidden = false; }
                    }
                });
            }

            window[{$successJs}] = function () {
                solved = true;
                if (el)   { el.classList.remove('captcha-invalid'); el.removeAttribute('aria-invalid'); }
                if (hint) { hint.hidden = true; }
            };
            window[{$expiredJs}] = function () {
                solved = false;
            };
        }());
        JS;
    }
}
