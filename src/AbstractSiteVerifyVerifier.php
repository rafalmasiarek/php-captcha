<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

use rafalmasiarek\HttpClient\Http\HttpClientInterface;

/**
 * Shared implementation for every provider that follows Google reCAPTCHA's
 * "siteverify" protocol — POST secret+response(+remoteip) as form data to a
 * fixed endpoint, get back {success, ...} as JSON. Cloudflare Turnstile and
 * hCaptcha deliberately mirror this wire format for drop-in compatibility,
 * so only the endpoint URL and any provider-specific extra POST field
 * (see extraParams()) differ between concrete subclasses.
 *
 * @package rafalmasiarek\Captcha
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
    public function verify(string $token, ?string $remoteIp = null): CaptchaResult
    {
        $body = ['secret' => $this->secretKey, 'response' => $token] + $this->extraParams();
        if ($remoteIp !== null) {
            $body['remoteip'] = $remoteIp;
        }

        $endpoint = $this->endpoint();
        $response = $this->http->request('POST', $endpoint, ['body' => $body, 'timeout' => $this->timeout]);

        if ($response->getError() !== null) {
            throw new CaptchaVerificationException("Unable to reach {$endpoint}: {$response->getError()}");
        }

        $data = $response->json();
        if (!\is_array($data)) {
            throw new CaptchaVerificationException("Malformed JSON response from {$endpoint}");
        }

        return new CaptchaResult(
            success: (bool) ($data['success'] ?? false),
            score: isset($data['score']) ? (float) $data['score'] : null,
            action: isset($data['action']) ? (string) $data['action'] : null,
            errorCodes: \array_map('strval', (array) ($data['error-codes'] ?? [])),
            challengeTs: isset($data['challenge_ts']) ? (string) $data['challenge_ts'] : null,
            hostname: isset($data['hostname']) ? (string) $data['hostname'] : null,
        );
    }
}
