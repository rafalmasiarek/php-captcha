<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\Helpers\InvisibleGlue\InvisibleWidgetGlueInterface;

/**
 * Raw client-side widget metadata for one widget shape, built by its own
 * Provider class (e.g. RecaptchaProvider::widgetV2()). Pure data, no
 * HTML/JS generation — see Helpers\HtmlHelper for that.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class CaptchaWidgetDescriptor
{
    /**
     * @param string $scriptUrl Absolute <script src> URL, including any query params.
     * @param string|null $widgetCssClass CSS class the official script scans for, or null when invisible.
     * @param string $tokenFieldName HTML form field name this widget's token arrives under.
     * @param InvisibleWidgetGlueInterface|null $invisibleGlue Set only for an invisible widget.
     */
    public function __construct(
        public readonly string $scriptUrl,
        public readonly ?string $widgetCssClass,
        public readonly string $tokenFieldName,
        public readonly ?InvisibleWidgetGlueInterface $invisibleGlue = null,
    ) {
    }

    /**
     * @return bool Whether this widget has no visible checkbox.
     */
    public function isInvisible(): bool
    {
        return $this->invisibleGlue !== null;
    }
}
