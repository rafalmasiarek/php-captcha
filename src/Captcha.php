<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use rafalmasiarek\DnsResolver\SystemDnsResolver;
use rafalmasiarek\HttpClient\Http\CurlHttpClient;
use rafalmasiarek\HttpClient\Http\HttpClientInterface;
use rafalmasiarek\HttpClient\Http\TransportErrorKind;

/**
 * Verifies a CAPTCHA token against any provider that follows the
 * "siteverify" wire protocol (reCAPTCHA, Turnstile, hCaptcha, and anything
 * else shaped the same way) — parametrized entirely by a
 * CaptchaProviderInterface, so this class's own constructor and verify()
 * signature never change based on which provider is configured. Only the
 * CaptchaProviderInterface implementation you construct changes.
 *
 * $http and the default $remoteIp resolver are optional overrides: omit
 * either to get a zero-config default (CurlHttpClient+SystemDnsResolver;
 * SystemRemoteIpProvider's naive $_SERVER['REMOTE_ADDR']) — the same
 * "explicit override, sensible fallback" shape verify()'s own $remoteIp
 * parameter already has.
 *
 * Logs short, dot-namespaced events ("captcha.verify.ok"/".rejected"/
 * ".timeout"/".transport_error"/".invalid_response") to an optional PSR-3
 * logger — rejections and failures always log; successes only when $audit
 * is true, matching rafalmasiarek/dns-resolver's FailoverDnsClient. $container
 * identifies this instance in that log output (e.g. "contactform_main",
 * "login") when an app configures more than one Captcha, mirroring this
 * app's own CSRF module's "container" concept — never sent to the provider,
 * purely a local log/debug label.
 *
 * @package rafalmasiarek\Captcha
 */
final class Captcha implements CaptchaVerifierInterface
{
    private readonly LoggerInterface $logger;

    /**
     * @param CaptchaProviderInterface $provider
     * @param RemoteIpProviderInterface|null $defaultIpProvider Used when verify() isn't
     *        given its own $remoteIp. Falls back to SystemRemoteIpProvider when also null.
     * @param HttpClientInterface|null $http Falls back to CurlHttpClient(SystemDnsResolver()) when null.
     * @param float $timeout Request timeout in seconds.
     * @param float|null $minScore Minimum accepted score (null = do not check). No-ops for a
     *                              provider/mode that doesn't return a score.
     * @param string|null $expectedAction Expected action value (null = do not check). No-ops
     *                                    for a provider/mode that doesn't return an action.
     * @param string|null $container Local label identifying this instance in log output —
     *                                e.g. "contactform_main", "login" — when an app configures
     *                                more than one Captcha. Never sent to the provider.
     * @param LoggerInterface|null $logger Receives rejection/failure warnings/errors always,
     *                                      and (when $audit is true) a debug entry for every
     *                                      successful verification. Defaults to a no-op logger.
     * @param bool $audit Whether to also log successful verifications (debug level).
     */
    public function __construct(
        private readonly CaptchaProviderInterface $provider,
        private readonly ?RemoteIpProviderInterface $defaultIpProvider = null,
        private readonly ?HttpClientInterface $http = null,
        private readonly float $timeout = 10.0,
        private readonly ?float $minScore = null,
        private readonly ?string $expectedAction = null,
        private readonly ?string $container = null,
        ?LoggerInterface $logger = null,
        private readonly bool $audit = false,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * The provider this instance verifies against — e.g. for a caller that
     * wants to discover CaptchaProviderInterface::defaultTokenFieldName()
     * without hardcoding any vendor's convention itself.
     *
     * @return CaptchaProviderInterface
     */
    public function provider(): CaptchaProviderInterface
    {
        return $this->provider;
    }

    /**
     * {@inheritDoc}
     */
    public function verify(string $token, string|RemoteIpProviderInterface|null $remoteIp = null): CaptchaResult
    {
        $ip = $this->resolveIp($remoteIp);

        $body = ['secret' => $this->provider->secretKey(), 'response' => $token] + $this->provider->extraParams();
        if ($ip !== null) {
            $body['remoteip'] = $ip;
        }

        $http = $this->http ?? new CurlHttpClient(new SystemDnsResolver());
        $endpoint = $this->provider->endpoint();
        $response = $http->request('POST', $endpoint, ['body' => $body, 'timeout' => $this->timeout]);

        $error = $response->getError();
        if ($error !== null) {
            $transportInfo = $response->getInfo();

            if ($response->getErrorKind() === TransportErrorKind::Timeout) {
                $this->log('warning', 'captcha.verify.timeout', ['error' => $error]);
                throw new CaptchaTimeoutException("Timed out verifying against {$endpoint}: {$error}", $transportInfo);
            }
            $this->log('warning', 'captcha.verify.transport_error', ['error' => $error]);
            throw new CaptchaTransportException("Unable to reach {$endpoint}: {$error}", $transportInfo);
        }

        $data = $response->json();
        if (!\is_array($data)) {
            $this->log('error', 'captcha.verify.invalid_response', ['status_code' => $response->getStatusCode()]);
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}", $response->getStatusCode());
        }

        $rawCodes = \array_map('strval', (array) ($data['error-codes'] ?? []));

        $categories = [];
        foreach ($rawCodes as $code) {
            $category = $this->provider->classifyErrorCode($code);
            if (!\in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        $apiSuccess = (bool) ($data['success'] ?? false);
        $score = isset($data['score']) ? (float) $data['score'] : null;
        $action = isset($data['action']) ? (string) $data['action'] : null;

        $scoreOk = $this->minScore === null || $score === null || $score >= $this->minScore;
        $actionOk = $this->expectedAction === null || $action === null || $action === $this->expectedAction;

        if (!$scoreOk && !\in_array(CaptchaErrorCategory::ScoreTooLow, $categories, true)) {
            $categories[] = CaptchaErrorCategory::ScoreTooLow;
        }
        if (!$actionOk && !\in_array(CaptchaErrorCategory::ActionMismatch, $categories, true)) {
            $categories[] = CaptchaErrorCategory::ActionMismatch;
        }

        $result = new CaptchaResult(
            success: $apiSuccess && $scoreOk && $actionOk,
            score: $score,
            action: $action,
            errorCodes: $rawCodes,
            errorCategories: $categories,
            challengeTs: isset($data['challenge_ts']) ? (string) $data['challenge_ts'] : null,
            hostname: isset($data['hostname']) ? (string) $data['hostname'] : null,
            raw: $data,
        );

        if ($result->success) {
            if ($this->audit) {
                $this->log('debug', 'captcha.verify.ok', $result->toDebugArray());
            }
        } else {
            $this->log('warning', 'captcha.verify.rejected', $result->toDebugArray());
        }

        return $result;
    }

    /**
     * Resolves the IP to send, in order: an explicit per-call value, the
     * Captcha-bound default, then the naive system fallback.
     *
     * @param string|RemoteIpProviderInterface|null $remoteIp
     *
     * @return string|null
     */
    private function resolveIp(string|RemoteIpProviderInterface|null $remoteIp): ?string
    {
        if ($remoteIp instanceof RemoteIpProviderInterface) {
            return $remoteIp->getRemoteIp();
        }
        if ($remoteIp !== null) {
            return $remoteIp;
        }
        if ($this->defaultIpProvider !== null) {
            return $this->defaultIpProvider->getRemoteIp();
        }
        return (new SystemRemoteIpProvider())->getRemoteIp();
    }

    /**
     * @param string $level PSR-3 log level.
     * @param string $message Dot-namespaced event code.
     * @param array<string, mixed> $context
     *
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->container !== null) {
            $context['container'] = $this->container;
        }
        $this->logger->log($level, $message, $context);
    }
}
