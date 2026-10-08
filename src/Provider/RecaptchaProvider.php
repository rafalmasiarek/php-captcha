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
     * The glue:
     * - Uses form.requestSubmit() (not form.submit()) so native constraint
     *   validation and the submit event still run on the real, final
     *   submission — form.submit() bypasses both. Checks form.checkValidity()
     *   BEFORE setting the "token ready" flag or calling requestSubmit(): if
     *   validation would fail, requestSubmit() never dispatches a submit
     *   event at all, so a flag set beforehand would otherwise sit stale on
     *   the form and let a LATER, unrelated submit through without a fresh
     *   token. Calls form.reportValidity() in that case to surface the
     *   native validation errors.
     * - Captures e.submitter before the async execute() call and replays
     *   it into requestSubmit(), preserving submitter-dependent behavior
     *   (name=value, formaction, ...) as far as requestSubmit supports.
     * - The one-shot "ready" flag is a plain JS property namespaced by this
     *   widget's own instance id (not a single shared form.dataset key) —
     *   two invisible v3 widgets on the same form don't share or race on it.
     * - Guards against a double-click firing execute() twice (pending flag)
     *   and against grecaptcha.ready() never calling back (timeout resets
     *   pending so a retry is always possible — no permanently stuck UI).
     * - On timeout, a rejected execute(), or a thrown exception, dispatches
     *   a bubbling "captcha:error" CustomEvent on the form (detail:
     *   instanceId + reason) — a hook for the host app to show its own
     *   message, without this library dictating how.
     * - Falls back to form.submit() only when requestSubmit() genuinely
     *   isn't supported, logging a console.warn so the degraded (no native
     *   validation, no submit event) path is visible, not silent.
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

                                    if (typeof form.requestSubmit === 'function') {
                                        if (form.checkValidity()) {
                                            form[readyFlag] = true;
                                            form.requestSubmit(submitter || undefined);
                                        } else {
                                            form.reportValidity();
                                        }
                                    } else {
                                        if (window.console && window.console.warn) {
                                            window.console.warn(
                                                'rafalmasiarek/captcha: form.requestSubmit() is not supported in ' +
                                                'this browser; falling back to form.submit(), which skips native ' +
                                                'validation and the submit event.'
                                            );
                                        }
                                        form.submit();
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
