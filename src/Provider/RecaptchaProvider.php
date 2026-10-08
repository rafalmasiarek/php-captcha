<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\CaptchaErrorCategory;
use rafalmasiarek\Captcha\CaptchaProviderInterface;

/**
 * Google reCAPTCHA (v2 or v3) configuration. v2 responses have no
 * score/action; v3 responses populate both — Captcha's generic handling
 * covers this without any v2/v3 distinction needed here, only in which
 * secret key is configured.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
final class RecaptchaProvider implements CaptchaProviderInterface
{
    /**
     * @param string $secretKey Provider-issued secret key for this site.
     */
    public function __construct(
        private readonly string $secretKey,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    /**
     * {@inheritDoc}
     */
    public function secretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * {@inheritDoc}
     */
    public function defaultTokenFieldName(): string
    {
        return 'g-recaptcha-response';
    }

    /**
     * {@inheritDoc}
     */
    public function extraParams(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
    {
        $code = RecaptchaErrorCode::tryFrom($rawCode);

        return match (true) {
            $code === null => CaptchaErrorCategory::Unknown,
            $code->isConfigurationError() => CaptchaErrorCategory::ConfigurationError,
            $code->isTokenRejection() => CaptchaErrorCategory::TokenRejected,
            default => CaptchaErrorCategory::Unknown,
        };
    }

    /**
     * Widget metadata for the visible v2 checkbox.
     *
     * @return CaptchaWidgetDescriptor
     */
    public static function widgetV2(): CaptchaWidgetDescriptor
    {
        return new CaptchaWidgetDescriptor(
            scriptUrl: 'https://www.google.com/recaptcha/api.js',
            widgetCssClass: 'g-recaptcha',
            tokenFieldName: 'g-recaptcha-response',
        );
    }

    /**
     * Widget metadata for invisible v3 — widgetCssClass null means no
     * checkbox; extraJs carries the whole grecaptcha.ready()/execute()
     * (Promise-based) flow since there's no generic baseline for it.
     *
     * Uses form.requestSubmit(), never form.submit() — the latter skips
     * native validation and the submit event, which defeats the point.
     * No requestSubmit() support reports "requestsubmit_unsupported" via
     * captcha:error instead of degrading. The "ready" flag is set only
     * right before requestSubmit() (after checkValidity() passes), so it
     * never sits stale on the form for a later, unrelated submit to
     * consume without a fresh token.
     *
     * @return CaptchaWidgetDescriptor
     */
    public static function widgetV3(): CaptchaWidgetDescriptor
    {
        return new CaptchaWidgetDescriptor(
            scriptUrl: 'https://www.google.com/recaptcha/api.js',
            widgetCssClass: null,
            tokenFieldName: 'g-recaptcha-response',
            scriptUrlParams: ['render' => '__CAPTCHA_SITE_KEY__'],
            extraJs: [
                <<<'JS'
                (function () {
                    var el   = document.querySelector('[data-captcha-instance="' + __CAPTCHA_INSTANCE_ID_JS__ + '"]');
                    var form = el ? el.closest('form') : null;
                    if (!form) return;

                    var readyFlag = '__captchaTokenReady_' + __CAPTCHA_INSTANCE_ID_JS__;
                    var pending = false;

                    function reportError(reason) {
                        form.dispatchEvent(new CustomEvent('captcha:error', {
                            bubbles: true,
                            detail: { instanceId: __CAPTCHA_INSTANCE_ID_JS__, reason: reason }
                        }));
                    }

                    form.addEventListener('submit', function (e) {
                        if (form[readyFlag]) {
                            delete form[readyFlag];
                            return;
                        }

                        e.preventDefault();
                        if (pending) return;
                        pending = true;

                        var submitter = e.submitter || null;
                        var timedOut = false;
                        var timeout = window.setTimeout(function () {
                            timedOut = true;
                            pending = false;
                            reportError('timeout');
                        }, 15000);

                        try {
                            grecaptcha.ready(function () {
                                grecaptcha.execute(__CAPTCHA_SITE_KEY_JS__, { action: __CAPTCHA_ACTION_JS__ }).then(function (token) {
                                    window.clearTimeout(timeout);
                                    if (timedOut) return;
                                    pending = false;
                                    el.value = token;

                                    if (typeof form.requestSubmit !== 'function') {
                                        reportError('requestsubmit_unsupported');
                                        return;
                                    }

                                    if (form.checkValidity()) {
                                        form[readyFlag] = true;
                                        form.requestSubmit(submitter || undefined);
                                    } else {
                                        form.reportValidity();
                                    }
                                }).catch(function () {
                                    window.clearTimeout(timeout);
                                    pending = false;
                                    reportError('execute_failed');
                                });
                            });
                        } catch (err) {
                            window.clearTimeout(timeout);
                            pending = false;
                            reportError('exception');
                        }
                    });
                }());
                JS,
            ],
        );
    }
}
