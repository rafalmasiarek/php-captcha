<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

/**
 * Raw client-side widget metadata for one widget shape, built by its own
 * Provider class (e.g. RecaptchaProvider::widgetV2()). Pure data (strings
 * and arrays, no closures) — safe to pass into a Twig context, dump, or
 * cache. No HTML/JS generation here — see Helpers\HtmlHelper for that.
 *
 * $widgetCssClass === null means "no visible checkbox" (e.g. reCAPTCHA v3) —
 * HtmlHelper renders a hidden input instead of a div in that case.
 * $extraCssClasses/$extraAttributes/$extraHiddenFields always apply to
 * whichever element is rendered (div or hidden input) — there is no silently
 * ignored combination.
 *
 * The four extension points below let a provider add whatever it needs
 * without any named concept (no "invisible mode", no "override") — they
 * are generic and always additive to the base widget:
 * - $scriptUrlParams: extra query params on the <script src>.
 * - $extraCssClasses / $extraAttributes: extra class/attributes on the widget element.
 * - $extraHiddenFields: extra hidden <input>s alongside the main token field.
 * - $extraJs: extra inline JS appended after the base glue (none, when invisible).
 *
 * Values may contain placeholder tokens, substituted by HtmlHelper at
 * render time once the caller's actual site key/action/instance id are
 * known. Tokens are "__CAPTCHA_<NAME>__" (not "{name}") specifically so a
 * validator can reject an unrecognized token as a likely typo without
 * misfiring on ordinary JS object/block syntax — see
 * HtmlHelper::ALLOWED_URL_TOKENS/ALLOWED_JS_TOKENS for which token is valid
 * in which field, and why (raw vs JSON-encoded, URL vs JS context).
 *
 * A descriptor is trusted configuration — authored by the app developer's
 * own Provider class, never built from end-user input — but every field is
 * still validated at construction regardless, on the same "fail fast on an
 * unsafe value" principle as the rest of this library.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class CaptchaWidgetDescriptor
{
    /** Attribute names HtmlHelper itself controls — never overridable via $extraAttributes. */
    private const RESERVED_ATTRIBUTES = [
        'class', 'name', 'type', 'value', 'id',
        'data-sitekey', 'data-callback', 'data-expired-callback', 'data-captcha-instance',
    ];

    /**
     * @param string $scriptUrl Absolute https:// <script src> URL for the generic declarative flow.
     * @param string|null $widgetCssClass CSS class the official script scans for, or null for no checkbox.
     * @param string $tokenFieldName HTML form field name this widget's token arrives under.
     * @param array<string,string> $scriptUrlParams Extra query params, merged onto $scriptUrl. May use
     *                                               HtmlHelper::ALLOWED_URL_TOKENS placeholders.
     * @param list<string> $extraCssClasses Extra classes added to the widget element.
     * @param array<string,string> $extraAttributes Extra HTML attributes (e.g. data-*) on the widget
     *                                               element. Not substituted — static values only.
     * @param array<string,string> $extraHiddenFields Extra hidden <input>s: name => value. Not
     *                                                 substituted — static values only.
     * @param list<string> $extraJs Extra inline JS, appended after the base glue. May use
     *                               HtmlHelper::ALLOWED_JS_TOKENS placeholders.
     *
     * @throws \InvalidArgumentException When $scriptUrl isn't an absolute https:// URL, when
     *                                    $widgetCssClass/$tokenFieldName has an unsafe shape, or
     *                                    when an attribute/class/field name is unsafe or reserved.
     */
    public function __construct(
        public readonly string $scriptUrl,
        public readonly ?string $widgetCssClass,
        public readonly string $tokenFieldName,
        public readonly array $scriptUrlParams = [],
        public readonly array $extraCssClasses = [],
        public readonly array $extraAttributes = [],
        public readonly array $extraHiddenFields = [],
        public readonly array $extraJs = [],
    ) {
        if (!\preg_match('#^https://[^\s]+$#', $this->scriptUrl)) {
            throw new \InvalidArgumentException("scriptUrl must be an absolute https:// URL, got: \"{$this->scriptUrl}\".");
        }
        if ($this->widgetCssClass !== null) {
            self::assertSafeCssClass($this->widgetCssClass);
        }
        self::assertSafeFieldName($this->tokenFieldName);

        foreach ($this->extraCssClasses as $class) {
            self::assertSafeCssClass($class);
        }
        foreach ($this->extraAttributes as $name => $value) {
            self::assertSafeAttributeName($name);
        }
        foreach ($this->extraHiddenFields as $name => $value) {
            self::assertSafeFieldName($name);
        }
    }

    /**
     * @param string $name
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function assertSafeAttributeName(string $name): void
    {
        self::assertSafeFieldName($name);

        if (\in_array(\strtolower($name), self::RESERVED_ATTRIBUTES, true)) {
            throw new \InvalidArgumentException("\"{$name}\" is controlled by HtmlHelper and cannot be set via extraAttributes.");
        }
    }

    /**
     * Shared shape check for anything that ends up as an HTML attribute
     * name or a form field "name" value — rejects on* event handlers
     * regardless of context.
     *
     * @param string $name
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function assertSafeFieldName(string $name): void
    {
        if (!\preg_match('/^[a-zA-Z_:][a-zA-Z0-9_.:-]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid HTML attribute/field name: \"{$name}\".");
        }
        if (\stripos($name, 'on') === 0) {
            throw new \InvalidArgumentException("Event handler attributes (\"on*\") are not allowed: \"{$name}\".");
        }
    }

    /**
     * @param string $class
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    private static function assertSafeCssClass(string $class): void
    {
        if (!\preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/', $class)) {
            throw new \InvalidArgumentException("Invalid CSS class name: \"{$class}\".");
        }
    }
}
