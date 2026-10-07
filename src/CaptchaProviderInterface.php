<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Configuration + behavior for one provider that follows the "siteverify"
 * wire protocol Captcha speaks — POST secret+response(+remoteip+extras) as
 * form data to a fixed endpoint, get back {success, ...} as JSON. Knows
 * only this protocol shape, not any specific vendor by name; concrete
 * implementations (RecaptchaProvider, TurnstileProvider, HCaptchaProvider)
 * live under the Provider\ namespace and are the ones that know a vendor.
 *
 * A pure, stateless-from-the-outside value object — no HTTP logic here;
 * Captcha owns the actual request/response handling and reads this
 * interface's five methods to do it.
 *
 * @package rafalmasiarek\Captcha
 */
interface CaptchaProviderInterface
{
    /**
     * @return string Absolute URL of this provider's siteverify endpoint.
     */
    public function endpoint(): string;

    /**
     * The conventional HTML form field name this provider's widget posts its
     * token under (e.g. "g-recaptcha-response") — a discoverable default for
     * a caller that reads the token out of a request body, so nothing above
     * this interface needs to hardcode any one vendor's convention. Purely
     * informational: Captcha::verify() always takes the token value directly
     * and never reads a request body itself.
     *
     * @return string
     */
    public function defaultTokenFieldName(): string;

    /**
     * @return string This provider's secret key.
     */
    public function secretKey(): string;

    /**
     * Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's
     * optional "sitekey" or Turnstile's optional "idempotency_key". Empty for
     * a provider that needs nothing extra.
     *
     * @return array<string, string>
     */
    public function extraParams(): array;

    /**
     * Classifies one of this provider's own raw error codes into the shared,
     * provider-agnostic CaptchaErrorCategory vocabulary.
     *
     * @param string $rawCode A value from the provider's "error-codes" response field.
     *
     * @return CaptchaErrorCategory
     */
    public function classifyErrorCode(string $rawCode): CaptchaErrorCategory;
}
