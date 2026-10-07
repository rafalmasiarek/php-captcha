<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha\Provider;

use rafalmasiarek\Captcha\CaptchaResponseException;
use rafalmasiarek\Captcha\CaptchaResult;
use rafalmasiarek\Captcha\CaptchaTimeoutException;
use rafalmasiarek\Captcha\CaptchaTransportException;
use rafalmasiarek\Captcha\CaptchaVerifierInterface;
use rafalmasiarek\Captcha\RemoteIpProviderInterface;
use rafalmasiarek\HttpClient\Http\HttpClientInterface;

/**
 * Shared implementation for every built-in provider that follows Google
 * reCAPTCHA's "siteverify" protocol — POST secret+response(+remoteip) as
 * form data to a fixed endpoint, get back {success, ...} as JSON. Cloudflare
 * Turnstile and hCaptcha deliberately mirror this wire format for drop-in
 * compatibility, so only the endpoint URL and any provider-specific extra
 * POST field (see extraParams()) differ between concrete subclasses.
 *
 * Lives under the Provider\ namespace, separate from the core contract in
 * rafalmasiarek\Captcha — a consumer plugging in an entirely custom provider
 * (one that doesn't follow this shared protocol at all) only ever needs to
 * implement CaptchaVerifierInterface directly, not anything in this namespace.
 *
 * @package rafalmasiarek\Captcha\Provider
 */
abstract class AbstractSiteVerifyVerifier implements CaptchaVerifierInterface
{
    /**
     * @param HttpClientInterface $http
     * @param string $secretKey Provider-issued secret key for this site.
     * @param float $timeout Request timeout in seconds.
     */
    public function __construct(
        protected readonly HttpClientInterface $http,
        protected readonly string $secretKey,
        protected readonly float $timeout = 10.0,
    ) {
    }

    /**
     * @return string Absolute URL of the provider's siteverify endpoint.
     */
    abstract protected function endpoint(): string;

    /**
     * Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's
     * optional "sitekey". Empty for providers that need nothing extra.
     *
     * @return array<string, string>
     */
    protected function extraParams(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $token, string|RemoteIpProviderInterface|null $remoteIp = null): CaptchaResult
    {
        return $this->sendVerification($token, $remoteIp, $this->extraParams());
    }

    /**
     * Sends the actual siteverify request. Exposed to subclasses (protected,
     * not part of CaptchaVerifierInterface) so a provider-specific extra
     * method — e.g. Turnstile's idempotency_key — can reuse the request/
     * response handling without duplicating it, while verify() itself stays
     * exactly the universal, provider-agnostic contract every consumer
     * depends on.
     *
     * @param string $token
     * @param string|RemoteIpProviderInterface|null $remoteIp
     * @param array<string, string> $extraParams Additional POST fields for this one call,
     *                                           merged over (and able to override) extraParams().
     *
     * @throws CaptchaTimeoutException When the provider doesn't respond within the timeout.
     * @throws CaptchaTransportException On any other transport failure.
     * @throws CaptchaResponseException When the response body isn't valid JSON.
     *
     * @return CaptchaResult
     */
    protected function sendVerification(
        string $token,
        string|RemoteIpProviderInterface|null $remoteIp,
        array $extraParams,
    ): CaptchaResult {
        $ip = $remoteIp instanceof RemoteIpProviderInterface ? $remoteIp->getRemoteIp() : $remoteIp;

        $body = ['secret' => $this->secretKey, 'response' => $token] + $extraParams;
        if ($ip !== null) {
            $body['remoteip'] = $ip;
        }

        $endpoint = $this->endpoint();
        $response = $this->http->request('POST', $endpoint, ['body' => $body, 'timeout' => $this->timeout]);

        $error = $response->getError();
        if ($error !== null) {
            $transportInfo = $response->getInfo();

            // HttpResponseInterface::getError() is free text (e.g. curl_strerror()'s
            // output), not a structured error code — rafalmasiarek/http-client doesn't
            // expose one today. Matching "timed out" against curl's own stable English
            // error strings is a best-effort heuristic, not a guarantee.
            if (\stripos($error, 'timed out') !== false || \stripos($error, 'timeout') !== false) {
                throw new CaptchaTimeoutException("Timed out verifying against {$endpoint}: {$error}", $transportInfo);
            }
            throw new CaptchaTransportException("Unable to reach {$endpoint}: {$error}", $transportInfo);
        }

        $data = $response->json();
        if (!\is_array($data)) {
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}", $response->getStatusCode());
        }

        return new CaptchaResult(
            success: (bool) ($data['success'] ?? false),
            score: isset($data['score']) ? (float) $data['score'] : null,
            action: isset($data['action']) ? (string) $data['action'] : null,
            errorCodes: \array_map('strval', (array) ($data['error-codes'] ?? [])),
            challengeTs: isset($data['challenge_ts']) ? (string) $data['challenge_ts'] : null,
            hostname: isset($data['hostname']) ? (string) $data['hostname'] : null,
            raw: $data,
        );
    }
}
