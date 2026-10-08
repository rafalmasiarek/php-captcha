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
 *
 * The four extension points below let a provider add whatever it needs
 * without any named concept (no "invisible mode", no "override") — they
 * are generic and always additive to the base widget:
 * - $scriptUrlParams: extra query params on the <script src>.
 * - $extraCssClasses / $extraAttributes: extra class/attributes on the widget element.
 * - $extraHiddenFields: extra hidden <input>s alongside the main token field.
 * - $extraJs: extra inline JS appended after the base glue (none, when invisible).
 *
 * Values in $scriptUrlParams/$extraJs may contain the placeholders
 * "{siteKey}"/"{action}" (raw) or "{siteKeyJs}"/"{actionJs}" (JSON-encoded,
 * for embedding directly into JS source) — HtmlHelper substitutes them at
 * render time, once the caller's actual site key/action are known.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class CaptchaWidgetDescriptor
{
    /**
     * @param string $scriptUrl Absolute base <script src> URL.
     * @param string|null $widgetCssClass CSS class the official script scans for, or null for no checkbox.
     * @param string $tokenFieldName HTML form field name this widget's token arrives under.
     * @param array<string,string> $scriptUrlParams Extra query params, merged onto $scriptUrl.
     * @param list<string> $extraCssClasses Extra classes added to the widget element.
     * @param array<string,string> $extraAttributes Extra HTML attributes (e.g. data-*) on the widget element.
     * @param array<string,string> $extraHiddenFields Extra hidden <input>s: name => value.
     * @param list<string> $extraJs Extra inline JS, appended after the base glue.
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
    }
}
