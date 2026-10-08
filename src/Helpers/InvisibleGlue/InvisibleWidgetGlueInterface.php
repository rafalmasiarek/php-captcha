<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Helpers\InvisibleGlue;

/**
 * Per-provider glue for an invisible CAPTCHA widget: script URL + JS to
 * obtain a token on submit. Providers' JS APIs differ (Promise vs callback),
 * so no generic implementation is possible.
 *
 * @package rafalmasiarek\Captcha\Helpers\InvisibleGlue
 */
interface InvisibleWidgetGlueInterface
{
    /**
     * @param string $siteKey Raw site key.
     *
     * @return string Full <script src="..."> URL, including any query params.
     */
    public function scriptUrl(string $siteKey): string;

    /**
     * Finds ".js-captcha-invisible", intercepts its form's submit, obtains
     * a token, writes it into the input, resubmits.
     *
     * @param string $siteKeyJs JSON-encoded site key.
     * @param string $actionJs JSON-encoded action name.
     *
     * @return string Raw (unminified) JS source.
     */
    public function glueJs(string $siteKeyJs, string $actionJs): string;
}
