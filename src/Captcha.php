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
    private readonly HttpClientInterface $http;

    /**
     * @param CaptchaProviderInterface $provider
     * @param RemoteIpProviderInterface|null $defaultIpProvider Used when verify() isn't
     *        given its own $remoteIp. Falls back to SystemRemoteIpProvider when also null.
     * @param HttpClientInterface|null $http Falls back to CurlHttpClient(SystemDnsResolver()) when null.
     * @param float $timeout Request timeout in seconds.
     * @param float|null $minScore Minimum accepted score, inclusive, in [0.0, 1.0] (null = do
     *                              not check). Fails closed: a response missing a score (or
     *                              returning one out of range) does NOT satisfy this check once
     *                              configured, regardless of what the provider/mode normally
     *                              returns — a legitimate provider/mode without scores should
     *                              leave this null rather than configure one that can never pass.
     * @param string|null $expectedAction Expected action value (null = do not check). Fails
     *                                     closed the same way as $minScore when configured.
     * @param string|null $expectedHostname Expected "hostname" field (null = do not check).
     *                                       Fails closed the same way when configured.
     * @param string|null $container Local label identifying this instance in log output —
     *                                e.g. "contactform_main", "login" — when an app configures
     *                                more than one Captcha. Never sent to the provider.
     * @param LoggerInterface|null $logger Receives rejection/failure warnings/errors always,
     *                                      and (when $audit is true) a debug entry for every
     *                                      successful verification. Defaults to a no-op logger.
     * @param bool $audit Whether to also log successful verifications (debug level).
     *
     * @throws \InvalidArgumentException When $minScore is outside [0.0, 1.0], or $expectedAction/
     *                                    $expectedHostname is an empty string.
     */
    public function __construct(
        private readonly CaptchaProviderInterface $provider,
        private readonly ?RemoteIpProviderInterface $defaultIpProvider = null,
        ?HttpClientInterface $http = null,
        private readonly float $timeout = 10.0,
        private readonly ?float $minScore = null,
        private readonly ?string $expectedAction = null,
        private readonly ?string $expectedHostname = null,
        private readonly ?string $container = null,
        ?LoggerInterface $logger = null,
        private readonly bool $audit = false,
    ) {
        if ($minScore !== null && (!\is_finite($minScore) || $minScore < 0.0 || $minScore > 1.0)) {
            throw new \InvalidArgumentException('minScore must be finite and within [0.0, 1.0].');
        }
        if ($expectedAction !== null && \trim($expectedAction) === '') {
            throw new \InvalidArgumentException('expectedAction cannot be an empty string.');
        }
        if ($expectedHostname !== null && \trim($expectedHostname) === '') {
            throw new \InvalidArgumentException('expectedHostname cannot be an empty string.');
        }
        $this->logger = $logger ?? new NullLogger();
        $this->http = $http ?? new CurlHttpClient(new SystemDnsResolver());
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
        if (\trim($token) === '') {
            $this->log('warning', 'captcha.verify.empty_token');

            return new CaptchaResult(success: false, errorCategories: [CaptchaErrorCategory::TokenRejected]);
        }

        $ip = $this->resolveIp($remoteIp);

        $body = ['secret' => $this->provider->secretKey(), 'response' => $token] + $this->provider->extraParams();
        if ($ip !== null) {
            $body['remoteip'] = $ip;
        }

        $endpoint = $this->provider->endpoint();
        $response = $this->http->request('POST', $endpoint, ['body' => $body, 'timeout' => $this->timeout]);

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

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logInvalidResponse($statusCode, 'http_status');
            throw new CaptchaResponseException("CAPTCHA provider returned HTTP {$statusCode} from {$endpoint}", $statusCode);
        }

        $data = $response->json();
        if (!\is_array($data)) {
            $this->logInvalidResponse($statusCode, 'body');
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}", $statusCode);
        }

        $rawCodes = $this->extractErrorCodes($data, $endpoint, $statusCode);

        $categories = [];
        foreach ($rawCodes as $code) {
            $category = $this->provider->classifyErrorCode($code);
            if (!\in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }

        if (!\array_key_exists('success', $data) || !\is_bool($data['success'])) {
            $this->logInvalidResponse($statusCode, 'success');
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}: \"success\" must be a boolean", $statusCode);
        }
        $apiSuccess = $data['success'];

        $score = $this->extractScore($data, $endpoint, $statusCode);
        $action = $this->extractStringField($data, 'action', $endpoint, $statusCode);
        $hostname = $this->extractStringField($data, 'hostname', $endpoint, $statusCode);

        // challenge_ts is purely informational (never used in a security decision below) —
        // coerced leniently rather than rejecting the whole response over a cosmetic field.
        $challengeTsRaw = $data['challenge_ts'] ?? null;
        $challengeTs = \is_scalar($challengeTsRaw) ? (string) $challengeTsRaw : null;

        $scoreOk = $this->minScore === null || ($score !== null && $score >= $this->minScore);
        $actionOk = $this->expectedAction === null || ($action !== null && $action === $this->expectedAction);
        $hostnameOk = $this->expectedHostname === null || ($hostname !== null && $hostname === $this->expectedHostname);

        if (!$scoreOk && !\in_array(CaptchaErrorCategory::ScoreTooLow, $categories, true)) {
            $categories[] = CaptchaErrorCategory::ScoreTooLow;
        }
        if (!$actionOk && !\in_array(CaptchaErrorCategory::ActionMismatch, $categories, true)) {
            $categories[] = CaptchaErrorCategory::ActionMismatch;
        }
        if (!$hostnameOk && !\in_array(CaptchaErrorCategory::HostnameMismatch, $categories, true)) {
            $categories[] = CaptchaErrorCategory::HostnameMismatch;
        }

        $result = new CaptchaResult(
            success: $apiSuccess && $scoreOk && $actionOk && $hostnameOk,
            score: $score,
            action: $action,
            errorCodes: $rawCodes,
            errorCategories: $categories,
            challengeTs: $challengeTs,
            hostname: $hostname,
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
     * @param array<string, mixed> $data
     * @param string $endpoint
     * @param int $statusCode
     *
     * @return float|null Null when absent; validated finite and within [0.0, 1.0] otherwise.
     *
     * @throws CaptchaResponseException When present but not a finite number within [0.0, 1.0].
     */
    private function extractScore(array $data, string $endpoint, int $statusCode): ?float
    {
        if (!\array_key_exists('score', $data) || $data['score'] === null) {
            return null;
        }

        $raw = $data['score'];
        $isNumeric = \is_int($raw) || \is_float($raw) || (\is_string($raw) && \is_numeric($raw));
        $score = $isNumeric ? (float) $raw : \NAN;

        if (!$isNumeric || !\is_finite($score) || $score < 0.0 || $score > 1.0) {
            $this->logInvalidResponse($statusCode, 'score');
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}: \"score\" is not a finite number within [0.0, 1.0]", $statusCode);
        }

        return $score;
    }

    /**
     * @param array<string, mixed> $data
     * @param string $field
     * @param string $endpoint
     * @param int $statusCode
     *
     * @return string|null Null when absent.
     *
     * @throws CaptchaResponseException When present but not a string.
     */
    private function extractStringField(array $data, string $field, string $endpoint, int $statusCode): ?string
    {
        if (!\array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }

        if (!\is_string($data[$field])) {
            $this->logInvalidResponse($statusCode, $field);
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}: \"{$field}\" is not a string", $statusCode);
        }

        return $data[$field];
    }

    /**
     * @param array<string, mixed> $data
     * @param string $endpoint
     * @param int $statusCode
     *
     * @return list<string>
     *
     * @throws CaptchaResponseException When "error-codes" is present but not a list of scalars.
     */
    private function extractErrorCodes(array $data, string $endpoint, int $statusCode): array
    {
        if (!\array_key_exists('error-codes', $data) || $data['error-codes'] === null) {
            return [];
        }

        $raw = $data['error-codes'];
        if (!\is_array($raw) || !\array_is_list($raw)) {
            $this->logInvalidResponse($statusCode, 'error-codes');
            throw new CaptchaResponseException("Malformed JSON response from {$endpoint}: \"error-codes\" is not a list", $statusCode);
        }

        $codes = [];
        foreach ($raw as $code) {
            if (!\is_scalar($code)) {
                $this->logInvalidResponse($statusCode, 'error-codes');
                throw new CaptchaResponseException("Malformed JSON response from {$endpoint}: \"error-codes\" contains a non-scalar entry", $statusCode);
            }
            $codes[] = (string) $code;
        }

        return $codes;
    }

    /**
     * @param int $statusCode
     * @param string $field
     *
     * @return void
     */
    private function logInvalidResponse(int $statusCode, string $field): void
    {
        $this->log('error', 'captcha.verify.invalid_response', ['status_code' => $statusCode, 'field' => $field]);
    }

    /**
     * Resolves the IP to send, in order: an explicit per-call value, the
     * Captcha-bound default, then the naive system fallback. Whatever the
     * source, the result is validated as a real IPv4/IPv6 address — an
     * invalid value (empty, malformed, stray whitespace) is treated as no
     * IP at all rather than sent to the provider as-is.
     *
     * @param string|RemoteIpProviderInterface|null $remoteIp
     *
     * @return string|null
     */
    private function resolveIp(string|RemoteIpProviderInterface|null $remoteIp): ?string
    {
        if ($remoteIp instanceof RemoteIpProviderInterface) {
            $resolved = $remoteIp->getRemoteIp();
        } elseif ($remoteIp !== null) {
            $resolved = $remoteIp;
        } elseif ($this->defaultIpProvider !== null) {
            $resolved = $this->defaultIpProvider->getRemoteIp();
        } else {
            $resolved = (new SystemRemoteIpProvider())->getRemoteIp();
        }

        if ($resolved === null || \filter_var($resolved, \FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $resolved;
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
