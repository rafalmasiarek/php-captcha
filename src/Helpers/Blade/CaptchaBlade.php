<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\Blade;

/**
 * Blade helper registering @captchaWidget(...) and @captchaScripts(...)
 * directives — each simply echoes the corresponding HtmlHelper call with
 * whatever expression the template passed. No service resolution needed:
 * unlike a stateful service, HtmlHelper's methods take plain arguments
 * (a CaptchaWidgetVariant case + strings), nothing to resolve from a
 * container.
 *
 * Usage:
 *   \rafalmasiarek\Captcha\Helpers\Blade\CaptchaBlade::register($bladeCompiler);
 *
 * Template usage:
 *   @captchaWidget($variant, $siteKey)
 *   @captchaScripts($variant, $siteKey)
 *
 * @package rafalmasiarek\Captcha\Helpers\Blade
 */
final class CaptchaBlade
{
    /**
     * @param object $bladeCompiler Instance of Illuminate\View\Compilers\BladeCompiler.
     *
     * @throws \InvalidArgumentException When $bladeCompiler doesn't support directive().
     *
     * @return void
     */
    public static function register(object $bladeCompiler): void
    {
        if (!\method_exists($bladeCompiler, 'directive')) {
            throw new \InvalidArgumentException('Provided Blade compiler does not support directive().');
        }

        $bladeCompiler->directive('captchaWidget', static function (string $expression): string {
            return "<?php echo \\rafalmasiarek\\Captcha\\Helpers\\HtmlHelper::widget({$expression}); ?>";
        });

        $bladeCompiler->directive('captchaScripts', static function (string $expression): string {
            return "<?php echo \\rafalmasiarek\\Captcha\\Helpers\\HtmlHelper::scripts({$expression}); ?>";
        });
    }
}
