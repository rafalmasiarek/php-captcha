<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\InvisibleGlue;

/**
 * reCAPTCHA v3 glue: grecaptcha.ready()/execute() (Promise-based).
 *
 * @package rafalmasiarek\Captcha\Helpers\InvisibleGlue
 */
final class RecaptchaV3Glue implements InvisibleWidgetGlueInterface
{
    public function scriptUrl(string $siteKey): string
    {
        return 'https://www.google.com/recaptcha/api.js?render=' . \rawurlencode($siteKey);
    }

    public function glueJs(string $siteKeyJs, string $actionJs): string
    {
        return <<<JS
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
        JS;
    }
}
