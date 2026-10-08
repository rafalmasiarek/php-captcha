<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\InvisibleGlue;

use rafalmasiarek\Captcha\Provider\CaptchaWidgetVariant;

/**
 * Maps CaptchaWidgetVariant to its InvisibleWidgetGlueInterface implementation.
 *
 * @package rafalmasiarek\Captcha\Helpers\InvisibleGlue
 */
final class InvisibleWidgetGlueRegistry
{
    /**
     * @param CaptchaWidgetVariant $variant
     *
     * @return InvisibleWidgetGlueInterface
     *
     * @throws \LogicException When $variant has no registered glue.
     */
    public static function for(CaptchaWidgetVariant $variant): InvisibleWidgetGlueInterface
    {
        return match ($variant) {
            CaptchaWidgetVariant::RecaptchaV3 => new RecaptchaV3Glue(),
            default => throw new \LogicException(
                "No InvisibleWidgetGlueInterface registered for {$variant->name}."
            ),
        };
    }
}
