## Index

### Classes

- [`Captcha`](#captcha) — `rafalmasiarek\Captcha`
- [`CaptchaResponseException`](#captcharesponseexception) — `rafalmasiarek\Captcha`
- [`CaptchaResult`](#captcharesult) — `rafalmasiarek\Captcha`
- [`CaptchaTimeoutException`](#captchatimeoutexception) — `rafalmasiarek\Captcha`
- [`CaptchaTransportException`](#captchatransportexception) — `rafalmasiarek\Captcha`
- [`CaptchaTransportFailureException`](#captchatransportfailureexception) — `rafalmasiarek\Captcha`
- [`CaptchaVerificationException`](#captchaverificationexception) — `rafalmasiarek\Captcha`
- [`CaptchaBlade`](#captchablade) — `rafalmasiarek\Captcha\Helpers\Blade`
- [`HtmlHelper`](#htmlhelper) — `rafalmasiarek\Captcha\Helpers`
- [`CaptchaExtension`](#captchaextension) — `rafalmasiarek\Captcha\Helpers\Plates`
- [`CaptchaExtension`](#captchaextension-1) — `rafalmasiarek\Captcha\Helpers\Twig`
- [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) — `rafalmasiarek\Captcha\Provider`
- [`HCaptchaProvider`](#hcaptchaprovider) — `rafalmasiarek\Captcha\Provider`
- [`RecaptchaProvider`](#recaptchaprovider) — `rafalmasiarek\Captcha\Provider`
- [`TurnstileProvider`](#turnstileprovider) — `rafalmasiarek\Captcha\Provider`
- [`SystemRemoteIpProvider`](#systemremoteipprovider) — `rafalmasiarek\Captcha`

### Interfaces

- [`CaptchaProviderInterface`](#captchaproviderinterface) — `rafalmasiarek\Captcha`
- [`CaptchaVerifierInterface`](#captchaverifierinterface) — `rafalmasiarek\Captcha`
- [`RemoteIpProviderInterface`](#remoteipproviderinterface) — `rafalmasiarek\Captcha`

### Enums

- [`CaptchaErrorCategory`](#captchaerrorcategory) — `rafalmasiarek\Captcha`
- [`HCaptchaErrorCode`](#hcaptchaerrorcode) — `rafalmasiarek\Captcha\Provider`
- [`RecaptchaErrorCode`](#recaptchaerrorcode) — `rafalmasiarek\Captcha\Provider`
- [`TurnstileErrorCode`](#turnstileerrorcode) — `rafalmasiarek\Captcha\Provider`

## `Captcha`

*final class* — `rafalmasiarek\Captcha\Captcha` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L39)

Verifies a CAPTCHA token against any provider that follows the "siteverify" wire protocol (reCAPTCHA, Turnstile, hCaptcha, and anything else shaped the same way) — parametrized entirely by a CaptchaProviderInterface, so this class's own constructor and verify() signature never change based on which provider is configured. Only the CaptchaProviderInterface implementation you construct changes. $http and the default $remoteIp resolver are optional overrides: omit either to get a zero-config default (CurlHttpClient+SystemDnsResolver; SystemRemoteIpProvider's naive $_SERVER['REMOTE_ADDR']) — the same "explicit override, sensible fallback" shape verify()'s own $remoteIp parameter already has. Logs short, dot-namespaced events ("captcha.verify.ok"/".rejected"/ ".timeout"/".transport_error"/".invalid_response") to an optional PSR-3 logger — rejections and failures always log; successes only when $audit is true, matching rafalmasiarek/dns-resolver's FailoverDnsClient. $container identifies this instance in that log output (e.g. "contactform_main", "login") when an app configures more than one Captcha, mirroring this app's own CSRF module's "container" concept — never sent to the provider, purely a local log/debug label.

implements [`CaptchaVerifierInterface`](#captchaverifierinterface)

**Depends on:** [`CaptchaProviderInterface`](#captchaproviderinterface), [`RemoteIpProviderInterface`](#remoteipproviderinterface), `HttpClientInterface`, `LoggerInterface`

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$logger` | `LoggerInterface` | private readonly | — |
| `$http` | `HttpClientInterface` | private readonly | — |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#captcha-__construct) | public | — | — |
| [`provider()`](#captcha-provider) | public | [`CaptchaProviderInterface`](#captchaproviderinterface) | The provider this instance verifies against — e.g. for a caller that wants to discover CaptchaProviderInterface::defaultTokenFieldName() without hardcoding any vendor's convention itself. |
| [`verify()`](#captcha-verify) | public | [`CaptchaResult`](#captcharesult) | — |
| [`extractScore()`](#captcha-extractscore) | private | `float\|null` | — |
| [`extractStringField()`](#captcha-extractstringfield) | private | `string\|null` | — |
| [`extractErrorCodes()`](#captcha-extracterrorcodes) | private | `list<string>` | — |
| [`logInvalidResponse()`](#captcha-loginvalidresponse) | private | `void` | — |
| [`resolveIp()`](#captcha-resolveip) | private | `string\|null` | Resolves the IP to send, in order: an explicit per-call value, the Captcha-bound default, then the naive system fallback. Whatever the source, the result is validated as a real IPv4/IPv6 address — an invalid value (empty, malformed, stray whitespace) is treated as no IP at all rather than sent to the provider as-is. |
| [`log()`](#captcha-log) | private | `void` | — |

<details>
<summary><code>__construct()</code></summary>

#### `Captcha __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L71)

```php
public function __construct(CaptchaProviderInterface $provider, ?RemoteIpProviderInterface $defaultIpProvider, ?HttpClientInterface $http, float $timeout, ?float $minScore, ?string $expectedAction, ?string $expectedHostname, ?string $container, ?LoggerInterface $logger, bool $audit)
```

| Param | Type | Description |
|---|---|---|
| `$provider` | [`CaptchaProviderInterface`](#captchaproviderinterface) | — |
| `$defaultIpProvider` | `RemoteIpProviderInterface\|null` | Used when verify() isn't given its own $remoteIp. Falls back to SystemRemoteIpProvider when also null. |
| `$http` | `HttpClientInterface\|null` | Falls back to CurlHttpClient(SystemDnsResolver()) when null. |
| `$timeout` | `float` | Request timeout in seconds. |
| `$minScore` | `float\|null` | Minimum accepted score, inclusive, in [0.0, 1.0] (null = do not check). Fails closed: a response missing a score (or returning one out of range) does NOT satisfy this check once configured, regardless of what the provider/mode normally returns — a legitimate provider/mode without scores should leave this null rather than configure one that can never pass. |
| `$expectedAction` | `string\|null` | Expected action value (null = do not check). Fails closed the same way as $minScore when configured. |
| `$expectedHostname` | `string\|null` | Expected "hostname" field (null = do not check). Fails closed the same way when configured. |
| `$container` | `string\|null` | Local label identifying this instance in log output — e.g. "contactform_main", "login" — when an app configures more than one Captcha. Never sent to the provider. |
| `$logger` | `LoggerInterface\|null` | Receives rejection/failure warnings/errors always, and (when $audit is true) a debug entry for every successful verification. Defaults to a no-op logger. |
| `$audit` | `bool` | Whether to also log successful verifications (debug level). |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When $minScore is outside [0.0, 1.0], or $expectedAction/ $expectedHostname is an empty string. |

</details>

<details>
<summary><code>provider()</code></summary>

#### `Captcha provider()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L103)

```php
public function provider(): CaptchaProviderInterface
```

The provider this instance verifies against — e.g. for a caller that wants to discover CaptchaProviderInterface::defaultTokenFieldName() without hardcoding any vendor's convention itself.

**Returns:** [`CaptchaProviderInterface`](#captchaproviderinterface)

</details>

<details>
<summary><code>verify()</code></summary>

#### `Captcha verify()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L111)

```php
public function verify(string $token, string|RemoteIpProviderInterface|null $remoteIp): CaptchaResult
```

| Param | Type | Description |
|---|---|---|
| `$token` | `string` | The token the client submitted (e.g. "g-recaptcha-response", "cf-turnstile-response", or "h-captcha-response" form field). |
| `$remoteIp` | `string\|RemoteIpProviderInterface\|null` | The end user's real IP address (post-trusted-proxy-resolution — e.g. from rafalmasiarek/real-ip-resolver's RealIpResolver, not a raw $_SERVER['REMOTE_ADDR']), as a string, or an object that resolves it lazily via RemoteIpProviderInterface. Optional — omitting it uses whatever default the implementation falls back to. |

**Returns:** [`CaptchaResult`](#captcharesult)

| Throws | When |
|---|---|
| [`CaptchaTimeoutException`](#captchatimeoutexception) | When the provider doesn't respond within the timeout. |
| [`CaptchaTransportException`](#captchatransportexception) | On any other transport failure. |
| [`CaptchaResponseException`](#captcharesponseexception) | When the response body isn't valid JSON. |

</details>

<details>
<summary><code>extractScore()</code></summary>

#### `Captcha extractScore()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L223)

```php
private function extractScore(array $data, string $endpoint, int $statusCode): ?float
```

| Param | Type | Description |
|---|---|---|
| `$data` | `array<string, mixed>` | — |
| `$endpoint` | `string` | — |
| `$statusCode` | `int` | — |

**Returns:** `float\|null` — Null when absent; validated finite and within [0.0, 1.0] otherwise.

| Throws | When |
|---|---|
| [`CaptchaResponseException`](#captcharesponseexception) | When present but not a finite number within [0.0, 1.0]. |

</details>

<details>
<summary><code>extractStringField()</code></summary>

#### `Captcha extractStringField()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L251)

```php
private function extractStringField(array $data, string $field, string $endpoint, int $statusCode): ?string
```

| Param | Type | Description |
|---|---|---|
| `$data` | `array<string, mixed>` | — |
| `$field` | `string` | — |
| `$endpoint` | `string` | — |
| `$statusCode` | `int` | — |

**Returns:** `string\|null` — Null when absent.

| Throws | When |
|---|---|
| [`CaptchaResponseException`](#captcharesponseexception) | When present but not a string. |

</details>

<details>
<summary><code>extractErrorCodes()</code></summary>

#### `Captcha extractErrorCodes()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L274)

```php
private function extractErrorCodes(array $data, string $endpoint, int $statusCode): array
```

| Param | Type | Description |
|---|---|---|
| `$data` | `array<string, mixed>` | — |
| `$endpoint` | `string` | — |
| `$statusCode` | `int` | — |

**Returns:** `list<string>`

| Throws | When |
|---|---|
| [`CaptchaResponseException`](#captcharesponseexception) | When "error-codes" is present but not a list of scalars. |

</details>

<details>
<summary><code>logInvalidResponse()</code></summary>

#### `Captcha logInvalidResponse()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L304)

```php
private function logInvalidResponse(int $statusCode, string $field): void
```

| Param | Type | Description |
|---|---|---|
| `$statusCode` | `int` | — |
| `$field` | `string` | — |

</details>

<details>
<summary><code>resolveIp()</code></summary>

#### `Captcha resolveIp()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L320)

```php
private function resolveIp(string|RemoteIpProviderInterface|null $remoteIp): ?string
```

Resolves the IP to send, in order: an explicit per-call value, the Captcha-bound default, then the naive system fallback. Whatever the source, the result is validated as a real IPv4/IPv6 address — an invalid value (empty, malformed, stray whitespace) is treated as no IP at all rather than sent to the provider as-is.

| Param | Type | Description |
|---|---|---|
| `$remoteIp` | `string\|RemoteIpProviderInterface\|null` | — |

**Returns:** `string\|null`

</details>

<details>
<summary><code>log()</code></summary>

#### `Captcha log()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Captcha.php#L346)

```php
private function log(string $level, string $message, array $context): void
```

| Param | Type | Description |
|---|---|---|
| `$level` | `string` | PSR-3 log level. |
| `$message` | `string` | Dot-namespaced event code. |
| `$context` | `array<string, mixed>` | — |

</details>

## `CaptchaErrorCategory`

*enum* — `rafalmasiarek\Captcha\CaptchaErrorCategory` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaErrorCategory.php#L23)

Provider-agnostic classification of why a verification was rejected. CaptchaResult::$errorCategories always uses this enum regardless of which provider was used — a caller that reads it (e.g. for stats/debug logging) never needs to change when a different provider is configured, unlike CaptchaResult::$errorCodes, which are raw, provider-specific strings. ConfigurationError/TokenRejected/ProviderInternalError come from a provider's own CaptchaProviderInterface::classifyErrorCode() (each provider maps its own vocabulary into this shared vocabulary). ScoreTooLow/ActionMismatch are computed by Captcha itself from the $minScore/$expectedAction checks — not something any provider's API reports directly.

| Case | Value |
|---|---|
| `ConfigurationError` | `—` |
| `TokenRejected` | `—` |
| `ProviderInternalError` | `—` |
| `ScoreTooLow` | `—` |
| `ActionMismatch` | `—` |
| `HostnameMismatch` | `—` |
| `Unknown` | `—` |

## `CaptchaProviderInterface`

*interface* — `rafalmasiarek\Captcha\CaptchaProviderInterface` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaProviderInterface.php#L21)

Configuration + behavior for one provider that follows the "siteverify" wire protocol Captcha speaks — POST secret+response(+remoteip+extras) as form data to a fixed endpoint, get back {success, ...} as JSON. Knows only this protocol shape, not any specific vendor by name; concrete implementations (RecaptchaProvider, TurnstileProvider, HCaptchaProvider) live under the Provider\ namespace and are the ones that know a vendor. A pure, stateless-from-the-outside value object — no HTTP logic here; Captcha owns the actual request/response handling and reads this interface's five methods to do it.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`endpoint()`](#captchaproviderinterface-endpoint) | public | `string` | — |
| [`defaultTokenFieldName()`](#captchaproviderinterface-defaulttokenfieldname) | public | `string` | The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself. |
| [`secretKey()`](#captchaproviderinterface-secretkey) | public | `string` | — |
| [`extraParams()`](#captchaproviderinterface-extraparams) | public | `array<string, string>` | Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra. |
| [`classifyErrorCode()`](#captchaproviderinterface-classifyerrorcode) | public | [`CaptchaErrorCategory`](#captchaerrorcategory) | Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary. |

<details>
<summary><code>endpoint()</code></summary>

#### `CaptchaProviderInterface endpoint()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaProviderInterface.php#L26)

```php
public function endpoint(): string
```

**Returns:** `string` — Absolute URL of this provider's siteverify endpoint.

</details>

<details>
<summary><code>defaultTokenFieldName()</code></summary>

#### `CaptchaProviderInterface defaultTokenFieldName()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaProviderInterface.php#L38)

```php
public function defaultTokenFieldName(): string
```

The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself.

**Returns:** `string`

</details>

<details>
<summary><code>secretKey()</code></summary>

#### `CaptchaProviderInterface secretKey()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaProviderInterface.php#L43)

```php
public function secretKey(): string
```

**Returns:** `string` — This provider's secret key.

</details>

<details>
<summary><code>extraParams()</code></summary>

#### `CaptchaProviderInterface extraParams()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaProviderInterface.php#L52)

```php
public function extraParams(): array
```

Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra.

**Returns:** `array<string, string>`

</details>

<details>
<summary><code>classifyErrorCode()</code></summary>

#### `CaptchaProviderInterface classifyErrorCode()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaProviderInterface.php#L62)

```php
public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
```

Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary.

| Param | Type | Description |
|---|---|---|
| `$rawCode` | `string` | A value from the provider's "error-codes" response field. |

**Returns:** [`CaptchaErrorCategory`](#captchaerrorcategory)

</details>

## `CaptchaResponseException`

*class* — `rafalmasiarek\Captcha\CaptchaResponseException` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaResponseException.php#L15)

Thrown when the provider responded, but the response body wasn't valid JSON or wasn't a JSON object — the HTTP request itself succeeded, but its content can't be interpreted (e.g. the provider returned an HTML error page instead of JSON, often alongside a non-2xx $statusCode).

extends [`CaptchaVerificationException`](#captchaverificationexception)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#captcharesponseexception-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `CaptchaResponseException __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaResponseException.php#L21)

```php
public function __construct(string $message, int $statusCode)
```

| Param | Type | Description |
|---|---|---|
| `$message` | `string` | — |
| `$statusCode` | `int` | HTTP status code of the response, 0 when unknown. |

</details>

## `CaptchaResult`

*final readonly class* — `rafalmasiarek\Captcha\CaptchaResult` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaResult.php#L26)

Result of a CAPTCHA verification call. Provider-agnostic shape — every field is either universal ($success) or "present when the provider/mode happens to return it, null/empty otherwise". $errorCodes stays raw, provider-specific strings (never filtered — an unrecognized/future code is never dropped). $errorCategories is the provider-agnostic classification of the same facts, via CaptchaProviderInterface::classifyErrorCode() plus Captcha's own $minScore/$expectedAction checks — the field a caller's downstream error-handling/stats code should actually branch on, since it stays the same shape no matter which provider produced it. $raw always carries the full decoded response, so a provider-specific field this DTO doesn't name (Turnstile's "cdata"/"metadata", hCaptcha's "credit"/"score_reason", ...) is never lost.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#captcharesult-__construct) | public | — | — |
| [`toDebugArray()`](#captcharesult-todebugarray) | public | `array<string, mixed>` | A flat, provider-agnostic snapshot suitable for logging (e.g. as a Bugsnag/PSR-3 context array) — the same keys regardless of which provider produced this result. |

<details>
<summary><code>__construct()</code></summary>

#### `CaptchaResult __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaResult.php#L41)

```php
public function __construct(bool $success, ?float $score, ?string $action, array $errorCodes, array $errorCategories, ?string $challengeTs, ?string $hostname, array $raw)
```

| Param | Type | Description |
|---|---|---|
| `$success` | `bool` | Whether verification passed overall — the provider accepted the token AND (when configured) the score/action checks passed. |
| `$score` | `float\|null` | Confidence score (0.0-1.0), when the provider/mode returns one. |
| `$action` | `string\|null` | The action name the token was issued for, when the provider/mode returns one. |
| `$errorCodes` | `list<string>` | Raw provider error codes, e.g. "timeout-or-duplicate". |
| `$errorCategories` | `list<CaptchaErrorCategory>` | The same facts, classified into a shared, provider-agnostic vocabulary. |
| `$challengeTs` | `string\|null` | ISO 8601 timestamp of the challenge, when returned. |
| `$hostname` | `string\|null` | Hostname the challenge was solved on, when returned. |
| `$raw` | `array<string, mixed>` | The full decoded JSON response, unfiltered. |

</details>

<details>
<summary><code>toDebugArray()</code></summary>

#### `CaptchaResult toDebugArray()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaResult.php#L60)

```php
public function toDebugArray(): array
```

A flat, provider-agnostic snapshot suitable for logging (e.g. as a Bugsnag/PSR-3 context array) — the same keys regardless of which provider produced this result.

**Returns:** `array<string, mixed>`

</details>

## `CaptchaTimeoutException`

*class* — `rafalmasiarek\Captcha\CaptchaTimeoutException` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaTimeoutException.php#L18)

Thrown when the provider's siteverify endpoint did not respond within the configured timeout. Detection checks HttpResponseInterface::getErrorKind() === TransportErrorKind::Timeout — a structured classification based on curl's own error code (CURLE_OPERATION_TIMEDOUT), not a string match against the free-text getError() message. A transport failure that isn't a timeout is thrown as the less specific CaptchaTransportException instead.

extends [`CaptchaTransportFailureException`](#captchatransportfailureexception)

## `CaptchaTransportException`

*class* — `rafalmasiarek\Captcha\CaptchaTransportException` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaTransportException.php#L15)

Thrown when the provider's siteverify endpoint could not be reached at all — DNS failure, connection refused, TLS failure, a block_private_network refusal — for any transport failure not recognized as a timeout (see CaptchaTimeoutException).

extends [`CaptchaTransportFailureException`](#captchatransportfailureexception)

## `CaptchaTransportFailureException`

*abstract class* — `rafalmasiarek\Captcha\CaptchaTransportFailureException` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaTransportFailureException.php#L15)

Base type for CaptchaTimeoutException/CaptchaTransportException — both carry a snapshot of the failed call's transport diagnostics, for a caller that wants to log them (e.g. alongside a Bugsnag report) without having had access to the underlying HttpResponseInterface itself.

extends [`CaptchaVerificationException`](#captchaverificationexception)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#captchatransportfailureexception-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `CaptchaTransportFailureException __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaTransportFailureException.php#L24)

```php
public function __construct(string $message, array $transportInfo)
```

| Param | Type | Description |
|---|---|---|
| `$message` | `string` | — |
| `$transportInfo` | `array<string, mixed>` | Snapshot of HttpResponseInterface::getInfo() — e.g. namelookup_time, connect_time, appconnect_time, total_time, primary_ip, when the underlying client provides them. Empty when unavailable. |

</details>

## `CaptchaVerificationException`

*class* — `rafalmasiarek\Captcha\CaptchaVerificationException` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaVerificationException.php#L20)

Base type for every exception thrown when a verification call itself fails — never for a legitimate "the token was rejected" outcome, which is a normal CaptchaResult with success=false (see CaptchaResult::$errorCodes and CaptchaResult::$errorCategories for why it was rejected). Thrown directly only when no more specific subtype applies; prefer catching CaptchaTimeoutException / CaptchaTransportException / CaptchaResponseException when the distinction matters, or this base type to catch all of them at once.

extends `\RuntimeException`

## `CaptchaVerifierInterface`

*interface* — `rafalmasiarek\Captcha\CaptchaVerifierInterface` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaVerifierInterface.php#L24)

Verifies a CAPTCHA token server-side. One interface for every provider — reCAPTCHA, Cloudflare Turnstile, hCaptcha all speak a near-identical token-verification protocol, so a consumer (a login brute-force guard, a contact form validator, ...) can depend on this interface alone. Captcha is the one general-purpose implementation, parametrized by a CaptchaProviderInterface. A caller with an entirely custom provider that doesn't follow the siteverify protocol at all implements this interface directly instead, bypassing Captcha/CaptchaProviderInterface entirely. Deliberately does not cover widget rendering (script URL, data-* attributes, the form field name the client posts the token under) — that differs per provider and per consuming framework, so it belongs to the caller, not here.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`verify()`](#captchaverifierinterface-verify) | public | [`CaptchaResult`](#captcharesult) | — |

<details>
<summary><code>verify()</code></summary>

#### `CaptchaVerifierInterface verify()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/CaptchaVerifierInterface.php#L43)

```php
public function verify(string $token, string|RemoteIpProviderInterface|null $remoteIp): CaptchaResult
```

| Param | Type | Description |
|---|---|---|
| `$token` | `string` | The token the client submitted (e.g. "g-recaptcha-response", "cf-turnstile-response", or "h-captcha-response" form field). |
| `$remoteIp` | `string\|RemoteIpProviderInterface\|null` | The end user's real IP address (post-trusted-proxy-resolution — e.g. from rafalmasiarek/real-ip-resolver's RealIpResolver, not a raw $_SERVER['REMOTE_ADDR']), as a string, or an object that resolves it lazily via RemoteIpProviderInterface. Optional — omitting it uses whatever default the implementation falls back to. |

**Returns:** [`CaptchaResult`](#captcharesult)

| Throws | When |
|---|---|
| [`CaptchaTimeoutException`](#captchatimeoutexception) | When the provider doesn't respond within the timeout. |
| [`CaptchaTransportException`](#captchatransportexception) | On any other transport failure. |
| [`CaptchaResponseException`](#captcharesponseexception) | When the response body isn't valid JSON. |

</details>

## `CaptchaBlade`

*final class* — `rafalmasiarek\Captcha\Helpers\Blade\CaptchaBlade` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/Blade/CaptchaBlade.php#L24)

Blade helper registering @captchaWidget(...) and @captchaScripts(...) directives — each simply echoes the corresponding HtmlHelper call with whatever expression the template passed. No service resolution needed: unlike a stateful service, HtmlHelper's methods take plain arguments (a CaptchaWidgetDescriptor + strings), nothing to resolve from a container. Usage: \rafalmasiarek\Captcha\Helpers\Blade\CaptchaBlade::register($bladeCompiler); Template usage:

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`register()`](#captchablade-register) | public static | `void` | — |

<details>
<summary><code>register()</code></summary>

#### `CaptchaBlade register()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/Blade/CaptchaBlade.php#L33)

```php
public static function register(object $bladeCompiler): void
```

| Param | Type | Description |
|---|---|---|
| `$bladeCompiler` | `object` | Instance of Illuminate\View\Compilers\BladeCompiler. |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When $bladeCompiler doesn't support directive(). |

</details>

## `HtmlHelper`

*final class* — `rafalmasiarek\Captcha\Helpers\HtmlHelper` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L30)

Template-engine-agnostic HTML/JS helper for rendering a CAPTCHA widget. Holds no provider knowledge — takes a CaptchaWidgetDescriptor built by the Provider class in use (e.g. RecaptchaProvider::widgetV2()). Framework wrappers (e.g. Helpers\Twig\CaptchaExtension) delegate here; usable directly with no template engine. $instanceId correlates one widget()/scripts() pair so multiple widgets on the same page never share DOM lookups or window-global callback names — pass the SAME $instanceId to both calls for one widget; pass a distinct one per widget when rendering more than one on a page. The 'default' default covers the common single-widget-per-page case with zero config. No CSS framework assumptions: feedback is a plain `.captcha-feedback` element (role="alert", aria-live), toggled via a `.captcha-invalid` class on the widget element — style both however your app likes. Nothing here disables a submit button; it gates the form's submit event instead, so disabled-state ownership always stays with the host application.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`widget()`](#htmlhelper-widget) | public static | `string` | Widget markup: a div with a checkbox (widgetCssClass set) or a hidden input (widgetCssClass null), plus extraCssClasses/extraAttributes/ extraHiddenFields. A visible widget also gets an adjacent, initially hidden feedback element (role="alert") toggled by scripts()'s glue JS. |
| [`scripts()`](#htmlhelper-scripts) | public static | `string` | <script> tags: the base widget script (scriptUrl + scriptUrlParams) plus glue JS — submit-gating-until-solved when widgetCssClass is set, nothing baseline otherwise — followed by any extraJs entries, each joined as an independent statement (not concatenated mid-expression). |
| [`assertSafeInstanceId()`](#htmlhelper-assertsafeinstanceid) | private static | `void` | — |
| [`assertKnownTokens()`](#htmlhelper-assertknowntokens) | private static | `void` | Scans $template for every "__CAPTCHA_..._​__"-shaped token and rejects any that isn't in $allowed — catches both a typo (e.g. "__CAPTCHA_SITEKEY__") and a right-token-wrong-context mistake (a JS token in a URL field, or vice versa) without misfiring on ordinary JS object/block syntax, since the token shape doesn't collide with "{...}" or any real JS syntax. |
| [`buildScriptUrl()`](#htmlhelper-buildscripturl) | private static | `string` | — |
| [`substituteJsTokens()`](#htmlhelper-substitutejstokens) | private static | `string` | Replaces ALLOWED_JS_TOKENS with JSON-encoded, <script>-safe values. |
| [`jsonForJs()`](#htmlhelper-jsonforjs) | private static | `string` | JSON-encodes a value for direct embedding in inline <script> text — hex-escapes "<", ">", "&", "'", "\"" so an attacker-influenced value (e.g. $action from request input) can never prematurely close the surrounding <script> element or break out of the string literal. |
| [`renderExtraAttributes()`](#htmlhelper-renderextraattributes) | private static | `string` | — |
| [`renderExtraHiddenFields()`](#htmlhelper-renderextrahiddenfields) | private static | `string` | — |
| [`baseGlueJs()`](#htmlhelper-basegluejs) | private static | `string` | Submit-gating glue for a visible widget: blocks form submission and shows the adjacent feedback element until the official script's data-callback fires. Never touches any button's disabled state — disabling ownership stays entirely with the host application. |

<details>
<summary><code>widget()</code></summary>

#### `HtmlHelper widget()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L69)

```php
public static function widget(CaptchaWidgetDescriptor $widget, string $siteKey, ?string $successCallback, ?string $expiredCallback, string $instanceId, string $validationMessage): string
```

Widget markup: a div with a checkbox (widgetCssClass set) or a hidden input (widgetCssClass null), plus extraCssClasses/extraAttributes/ extraHiddenFields. A visible widget also gets an adjacent, initially hidden feedback element (role="alert") toggled by scripts()'s glue JS.

| Param | Type | Description |
|---|---|---|
| `$widget` | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | — |
| `$siteKey` | `string` | — |
| `$successCallback` | `string\|null` | JS global function name called when solved. Defaults to a name derived from $instanceId. |
| `$expiredCallback` | `string\|null` | JS global function name called when expired. Defaults to a name derived from $instanceId. |
| `$instanceId` | `string` | Correlates with the scripts() call for this same widget. Must match "/^[a-zA-Z0-9_-]+$/". Give each widget on a page a distinct value. |
| `$validationMessage` | `string` | Feedback text shown when a visible widget is submitted unsolved. Not imposed — pass your own localized string. |

**Returns:** `string`

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When $instanceId doesn't match the required pattern. |

</details>

<details>
<summary><code>scripts()</code></summary>

#### `HtmlHelper scripts()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L130)

```php
public static function scripts(CaptchaWidgetDescriptor $widget, string $siteKey, string $action, ?string $successCallback, ?string $expiredCallback, string $instanceId, ?string $nonce): string
```

<script> tags: the base widget script (scriptUrl + scriptUrlParams) plus glue JS — submit-gating-until-solved when widgetCssClass is set, nothing baseline otherwise — followed by any extraJs entries, each joined as an independent statement (not concatenated mid-expression).

| Param | Type | Description |
|---|---|---|
| `$widget` | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | — |
| `$siteKey` | `string` | — |
| `$action` | `string` | Substituted into any __CAPTCHA_ACTION__/__CAPTCHA_ACTION_JS__ token. |
| `$successCallback` | `string\|null` | Must match widget()'s $successCallback. |
| `$expiredCallback` | `string\|null` | Must match widget()'s $expiredCallback. |
| `$instanceId` | `string` | Must match widget()'s $instanceId for this same widget. |
| `$nonce` | `string\|null` | CSP nonce applied to both emitted <script> tags, when set. |

**Returns:** `string`

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When $instanceId is invalid, or a descriptor value uses an unrecognized or wrong-context placeholder token. |

</details>

<details>
<summary><code>assertSafeInstanceId()</code></summary>

#### `HtmlHelper assertSafeInstanceId()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L169)

```php
private static function assertSafeInstanceId(string $instanceId): void
```

| Param | Type | Description |
|---|---|---|
| `$instanceId` | `string` | — |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

<details>
<summary><code>assertKnownTokens()</code></summary>

#### `HtmlHelper assertKnownTokens()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L190)

```php
private static function assertKnownTokens(string $template, array $allowed): void
```

Scans $template for every "__CAPTCHA_..._​__"-shaped token and rejects any that isn't in $allowed — catches both a typo (e.g. "__CAPTCHA_SITEKEY__") and a right-token-wrong-context mistake (a JS token in a URL field, or vice versa) without misfiring on ordinary JS object/block syntax, since the token shape doesn't collide with "{...}" or any real JS syntax.

| Param | Type | Description |
|---|---|---|
| `$template` | `string` | — |
| `$allowed` | `list<string>` | — |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

<details>
<summary><code>buildScriptUrl()</code></summary>

#### `HtmlHelper buildScriptUrl()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L211)

```php
private static function buildScriptUrl(CaptchaWidgetDescriptor $widget, string $siteKey, string $action): string
```

| Param | Type | Description |
|---|---|---|
| `$widget` | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | — |
| `$siteKey` | `string` | — |
| `$action` | `string` | — |

**Returns:** `string` — $widget->scriptUrl with $widget->scriptUrlParams appended as a query string.

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

<details>
<summary><code>substituteJsTokens()</code></summary>

#### `HtmlHelper substituteJsTokens()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L239)

```php
private static function substituteJsTokens(string $template, string $siteKey, string $action, string $instanceId): string
```

Replaces ALLOWED_JS_TOKENS with JSON-encoded, <script>-safe values.

| Param | Type | Description |
|---|---|---|
| `$template` | `string` | — |
| `$siteKey` | `string` | — |
| `$action` | `string` | — |
| `$instanceId` | `string` | — |

**Returns:** `string`

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

<details>
<summary><code>jsonForJs()</code></summary>

#### `HtmlHelper jsonForJs()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L260)

```php
private static function jsonForJs(string $value): string
```

JSON-encodes a value for direct embedding in inline <script> text — hex-escapes "<", ">", "&", "'", "\"" so an attacker-influenced value (e.g. $action from request input) can never prematurely close the surrounding <script> element or break out of the string literal.

| Param | Type | Description |
|---|---|---|
| `$value` | `string` | — |

**Returns:** `string`

</details>

<details>
<summary><code>renderExtraAttributes()</code></summary>

#### `HtmlHelper renderExtraAttributes()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L273)

```php
private static function renderExtraAttributes(CaptchaWidgetDescriptor $widget): string
```

| Param | Type | Description |
|---|---|---|
| `$widget` | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | — |

**Returns:** `string` — Extra HTML attributes, each preceded by a space.

</details>

<details>
<summary><code>renderExtraHiddenFields()</code></summary>

#### `HtmlHelper renderExtraHiddenFields()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L288)

```php
private static function renderExtraHiddenFields(CaptchaWidgetDescriptor $widget): string
```

| Param | Type | Description |
|---|---|---|
| `$widget` | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | — |

**Returns:** `string` — Extra hidden <input> tags.

</details>

<details>
<summary><code>baseGlueJs()</code></summary>

#### `HtmlHelper baseGlueJs()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/HtmlHelper.php#L311)

```php
private static function baseGlueJs(string $successCallback, string $expiredCallback, string $instanceId): string
```

Submit-gating glue for a visible widget: blocks form submission and shows the adjacent feedback element until the official script's data-callback fires. Never touches any button's disabled state — disabling ownership stays entirely with the host application.

| Param | Type | Description |
|---|---|---|
| `$successCallback` | `string` | — |
| `$expiredCallback` | `string` | — |
| `$instanceId` | `string` | Already validated by the caller. |

**Returns:** `string`

</details>

## `CaptchaExtension`

*final class* — `rafalmasiarek\Captcha\Helpers\Plates\CaptchaExtension` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/Plates/CaptchaExtension.php#L24)

Plates extension exposing captcha_widget() and captcha_scripts() template functions, delegating to HtmlHelper. Usage: \rafalmasiarek\Captcha\Helpers\Plates\CaptchaExtension::register($engine); Template usage: <?= $this->captcha_widget($variant, $siteKey) ?> <?= $this->captcha_scripts($variant, $siteKey) ?>

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`register()`](#captchaextension-register) | public static | `void` | — |

<details>
<summary><code>register()</code></summary>

#### `CaptchaExtension register()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/Plates/CaptchaExtension.php#L31)

```php
public static function register(Engine $engine): void
```

| Param | Type | Description |
|---|---|---|
| `$engine` | `Engine` | — |

</details>

## `CaptchaExtension`

*final class* — `rafalmasiarek\Captcha\Helpers\Twig\CaptchaExtension` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/Twig/CaptchaExtension.php#L25)

Twig extension providing {{ captcha_widget(widget, siteKey) }} and {{ captcha_scripts(widget, siteKey, action) }} — thin wrappers around HtmlHelper, which has no Twig dependency of its own. Not autoloaded by anything in this package unless a consumer actually instantiates it, so twig/twig is never required just because this file exists — a consumer who wants it already has Twig in their own project. $widget is a rafalmasiarek\Captcha\Provider\CaptchaWidgetDescriptor, built by the Provider in use (e.g. RecaptchaProvider::widgetV2()) and passed into the template context by the caller.

extends `AbstractExtension`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`getFunctions()`](#captchaextension-getfunctions) | public | `array` | {@inheritDoc} |

<details>
<summary><code>getFunctions()</code></summary>

#### `CaptchaExtension getFunctions()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Helpers/Twig/CaptchaExtension.php#L30)

```php
public function getFunctions(): array
```

{@inheritDoc}

**Returns:** `array`

</details>

## `CaptchaWidgetDescriptor`

*final class* — `rafalmasiarek\Captcha\Provider\CaptchaWidgetDescriptor` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/CaptchaWidgetDescriptor.php#L42)

Raw client-side widget metadata for one widget shape, built by its own Provider class (e.g. RecaptchaProvider::widgetV2()). Pure data (strings and arrays, no closures) — safe to pass into a Twig context, dump, or cache. No HTML/JS generation here — see Helpers\HtmlHelper for that. $widgetCssClass === null means "no visible checkbox" (e.g. reCAPTCHA v3) — HtmlHelper renders a hidden input instead of a div in that case. $extraCssClasses/$extraAttributes/$extraHiddenFields always apply to whichever element is rendered (div or hidden input) — there is no silently ignored combination. The four extension points below let a provider add whatever it needs without any named concept (no "invisible mode", no "override") — they are generic and always additive to the base widget: - $scriptUrlParams: extra query params on the <script src>. - $extraCssClasses / $extraAttributes: extra class/attributes on the widget element. - $extraHiddenFields: extra hidden <input>s alongside the main token field. - $extraJs: extra inline JS appended after the base glue (none, when invisible). Values may contain placeholder tokens, substituted by HtmlHelper at render time once the caller's actual site key/action/instance id are known. Tokens are "__CAPTCHA_<NAME>__" (not "{name}") specifically so a validator can reject an unrecognized token as a likely typo without misfiring on ordinary JS object/block syntax — see HtmlHelper::ALLOWED_URL_TOKENS/ALLOWED_JS_TOKENS for which token is valid in which field, and why (raw vs JSON-encoded, URL vs JS context). A descriptor is trusted configuration — authored by the app developer's own Provider class, never built from end-user input — but every field is still validated at construction regardless, on the same "fail fast on an unsafe value" principle as the rest of this library.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#captchawidgetdescriptor-__construct) | public | — | — |
| [`assertSafeAttributeName()`](#captchawidgetdescriptor-assertsafeattributename) | private static | `void` | — |
| [`assertSafeFieldName()`](#captchawidgetdescriptor-assertsafefieldname) | private static | `void` | Shared shape check for anything that ends up as an HTML attribute name or a form field "name" value — rejects on* event handlers regardless of context. |
| [`assertSafeCssClass()`](#captchawidgetdescriptor-assertsafecssclass) | private static | `void` | — |

<details>
<summary><code>__construct()</code></summary>

#### `CaptchaWidgetDescriptor __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/CaptchaWidgetDescriptor.php#L68)

```php
public function __construct(string $scriptUrl, ?string $widgetCssClass, string $tokenFieldName, array $scriptUrlParams, array $extraCssClasses, array $extraAttributes, array $extraHiddenFields, array $extraJs)
```

| Param | Type | Description |
|---|---|---|
| `$scriptUrl` | `string` | Absolute https:// <script src> URL for the generic declarative flow. |
| `$widgetCssClass` | `string\|null` | CSS class the official script scans for, or null for no checkbox. |
| `$tokenFieldName` | `string` | HTML form field name this widget's token arrives under. |
| `$scriptUrlParams` | `array<string, string>` | Extra query params, merged onto $scriptUrl. May use HtmlHelper::ALLOWED_URL_TOKENS placeholders. |
| `$extraCssClasses` | `list<string>` | Extra classes added to the widget element. |
| `$extraAttributes` | `array<string, string>` | Extra HTML attributes (e.g. data-*) on the widget element. Not substituted — static values only. |
| `$extraHiddenFields` | `array<string, string>` | Extra hidden <input>s: name => value. Not substituted — static values only. |
| `$extraJs` | `list<string>` | Extra inline JS, appended after the base glue. May use HtmlHelper::ALLOWED_JS_TOKENS placeholders. |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When $scriptUrl isn't an absolute https:// URL, when $widgetCssClass/$tokenFieldName has an unsafe shape, or when an attribute/class/field name is unsafe or reserved. |

</details>

<details>
<summary><code>assertSafeAttributeName()</code></summary>

#### `CaptchaWidgetDescriptor assertSafeAttributeName()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/CaptchaWidgetDescriptor.php#L104)

```php
private static function assertSafeAttributeName(string $name): void
```

| Param | Type | Description |
|---|---|---|
| `$name` | `string` | — |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

<details>
<summary><code>assertSafeFieldName()</code></summary>

#### `CaptchaWidgetDescriptor assertSafeFieldName()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/CaptchaWidgetDescriptor.php#L124)

```php
private static function assertSafeFieldName(string $name): void
```

Shared shape check for anything that ends up as an HTML attribute name or a form field "name" value — rejects on* event handlers regardless of context.

| Param | Type | Description |
|---|---|---|
| `$name` | `string` | — |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

<details>
<summary><code>assertSafeCssClass()</code></summary>

#### `CaptchaWidgetDescriptor assertSafeCssClass()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/CaptchaWidgetDescriptor.php#L141)

```php
private static function assertSafeCssClass(string $class): void
```

| Param | Type | Description |
|---|---|---|
| `$class` | `string` | — |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | — |

</details>

## `HCaptchaErrorCode`

*enum* — `rafalmasiarek\Captcha\Provider\HCaptchaErrorCode` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaErrorCode.php#L21)

Error codes documented by hCaptcha's siteverify API. CaptchaResult::$errorCodes always keeps the raw strings hCaptcha sent — an unrecognized/future code is never dropped by using this enum. This exists only to classify the *known* ones; HCaptchaProvider uses it internally for CaptchaProviderInterface::classifyErrorCode(). A caller who wants raw-level detail beyond CaptchaResult::$errorCategories can also map $result->errorCodes through self::tryFrom() directly. Source: https://docs.hcaptcha.com/#siteverify-error-codes-table

backed by `string`

| Case | Value |
|---|---|
| `MissingInputSecret` | `'missing-input-secret'` |
| `InvalidInputSecret` | `'invalid-input-secret'` |
| `BadRequest` | `'bad-request'` |
| `SitekeySecretMismatch` | `'sitekey-secret-mismatch'` |
| `MissingInputResponse` | `'missing-input-response'` |
| `InvalidInputResponse` | `'invalid-input-response'` |
| `InvalidOrAlreadySeenResponse` | `'invalid-or-already-seen-response'` |
| `NotUsingDummyPasscode` | `'not-using-dummy-passcode'` |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`isConfigurationError()`](#hcaptchaerrorcode-isconfigurationerror) | public | `bool` | Whether this code indicates a problem with our own integration (wrong/missing secret key, malformed request) rather than a legitimately rejected token. |
| [`isTokenRejection()`](#hcaptchaerrorcode-istokenrejection) | public | `bool` | Whether this code indicates the token itself was legitimately rejected (expired, reused, malformed) rather than our integration being broken. |

<details>
<summary><code>isConfigurationError()</code></summary>

#### `HCaptchaErrorCode isConfigurationError()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaErrorCode.php#L54)

```php
public function isConfigurationError(): bool
```

Whether this code indicates a problem with our own integration (wrong/missing secret key, malformed request) rather than a legitimately rejected token.

**Returns:** `bool`

</details>

<details>
<summary><code>isTokenRejection()</code></summary>

#### `HCaptchaErrorCode isTokenRejection()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaErrorCode.php#L68)

```php
public function isTokenRejection(): bool
```

Whether this code indicates the token itself was legitimately rejected (expired, reused, malformed) rather than our integration being broken.

**Returns:** `bool`

</details>

## `HCaptchaProvider`

*final class* — `rafalmasiarek\Captcha\Provider\HCaptchaProvider` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L22)

hCaptcha configuration. Standard responses have no score; hCaptcha Enterprise additionally returns a confidence score, picked up by Captcha's generic handling with no code difference here. hCaptcha-specific response fields not named on CaptchaResult — "credit" (whether this solve counts toward hCaptcha's publisher reward program) and Enterprise's "score_reason" (an explanation array for the score) — are always available via CaptchaResult::$raw.

implements [`CaptchaProviderInterface`](#captchaproviderinterface)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#hcaptchaprovider-__construct) | public | — | — |
| [`endpoint()`](#hcaptchaprovider-endpoint) | public | `string` | — |
| [`defaultTokenFieldName()`](#hcaptchaprovider-defaulttokenfieldname) | public | `string` | The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself. |
| [`secretKey()`](#hcaptchaprovider-secretkey) | public | `string` | — |
| [`extraParams()`](#hcaptchaprovider-extraparams) | public | `array<string, string>` | Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra. |
| [`classifyErrorCode()`](#hcaptchaprovider-classifyerrorcode) | public | [`CaptchaErrorCategory`](#captchaerrorcategory) | Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary. |
| [`widget()`](#hcaptchaprovider-widget) | public static | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | Widget metadata for the visible hCaptcha checkbox. |

<details>
<summary><code>__construct()</code></summary>

#### `HCaptchaProvider __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L30)

```php
public function __construct(string $secretKey, ?string $siteKey)
```

| Param | Type | Description |
|---|---|---|
| `$secretKey` | `string` | Provider-issued secret key for this site. |
| `$siteKey` | `string\|null` | Site key to send alongside the token — hCaptcha recommends this to disambiguate shared secrets across multiple sites. Optional. |

</details>

<details>
<summary><code>endpoint()</code></summary>

#### `HCaptchaProvider endpoint()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L39)

```php
public function endpoint(): string
```

**Returns:** `string` — Absolute URL of this provider's siteverify endpoint.

</details>

<details>
<summary><code>defaultTokenFieldName()</code></summary>

#### `HCaptchaProvider defaultTokenFieldName()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L47)

```php
public function defaultTokenFieldName(): string
```

The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself.

**Returns:** `string`

</details>

<details>
<summary><code>secretKey()</code></summary>

#### `HCaptchaProvider secretKey()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L55)

```php
public function secretKey(): string
```

**Returns:** `string` — This provider's secret key.

</details>

<details>
<summary><code>extraParams()</code></summary>

#### `HCaptchaProvider extraParams()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L63)

```php
public function extraParams(): array
```

Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra.

**Returns:** `array<string, string>`

</details>

<details>
<summary><code>classifyErrorCode()</code></summary>

#### `HCaptchaProvider classifyErrorCode()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L71)

```php
public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
```

Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary.

| Param | Type | Description |
|---|---|---|
| `$rawCode` | `string` | A value from the provider's "error-codes" response field. |

**Returns:** [`CaptchaErrorCategory`](#captchaerrorcategory)

</details>

<details>
<summary><code>widget()</code></summary>

#### `HCaptchaProvider widget()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/HCaptchaProvider.php#L88)

```php
public static function widget(): CaptchaWidgetDescriptor
```

Widget metadata for the visible hCaptcha checkbox.

**Returns:** [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor)

</details>

## `RecaptchaErrorCode`

*enum* — `rafalmasiarek\Captcha\Provider\RecaptchaErrorCode` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaErrorCode.php#L21)

Error codes documented by Google reCAPTCHA's siteverify API. CaptchaResult::$errorCodes always keeps the raw strings reCAPTCHA sent — an unrecognized/future code is never dropped by using this enum. This exists only to classify the *known* ones; RecaptchaProvider uses it internally for CaptchaProviderInterface::classifyErrorCode(). A caller who wants raw-level detail beyond CaptchaResult::$errorCategories can also map $result->errorCodes through self::tryFrom() directly. Source: https://developers.google.com/recaptcha/docs/verify

backed by `string`

| Case | Value |
|---|---|
| `MissingInputSecret` | `'missing-input-secret'` |
| `InvalidInputSecret` | `'invalid-input-secret'` |
| `BadRequest` | `'bad-request'` |
| `MissingInputResponse` | `'missing-input-response'` |
| `InvalidInputResponse` | `'invalid-input-response'` |
| `TimeoutOrDuplicate` | `'timeout-or-duplicate'` |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`isConfigurationError()`](#recaptchaerrorcode-isconfigurationerror) | public | `bool` | Whether this code indicates a problem with our own integration (wrong/missing secret key, malformed request) rather than a legitimately rejected token. |
| [`isTokenRejection()`](#recaptchaerrorcode-istokenrejection) | public | `bool` | Whether this code indicates the token itself was legitimately rejected (expired, reused, malformed) rather than our integration being broken. |

<details>
<summary><code>isConfigurationError()</code></summary>

#### `RecaptchaErrorCode isConfigurationError()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaErrorCode.php#L47)

```php
public function isConfigurationError(): bool
```

Whether this code indicates a problem with our own integration (wrong/missing secret key, malformed request) rather than a legitimately rejected token.

**Returns:** `bool`

</details>

<details>
<summary><code>isTokenRejection()</code></summary>

#### `RecaptchaErrorCode isTokenRejection()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaErrorCode.php#L61)

```php
public function isTokenRejection(): bool
```

Whether this code indicates the token itself was legitimately rejected (expired, reused, malformed) rather than our integration being broken.

**Returns:** `bool`

</details>

## `RecaptchaProvider`

*final class* — `rafalmasiarek\Captcha\Provider\RecaptchaProvider` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L18)

Google reCAPTCHA (v2 or v3) configuration. v2 responses have no score/action; v3 responses populate both — Captcha's generic handling covers this without any v2/v3 distinction needed here, only in which secret key is configured.

implements [`CaptchaProviderInterface`](#captchaproviderinterface)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#recaptchaprovider-__construct) | public | — | — |
| [`endpoint()`](#recaptchaprovider-endpoint) | public | `string` | — |
| [`secretKey()`](#recaptchaprovider-secretkey) | public | `string` | — |
| [`defaultTokenFieldName()`](#recaptchaprovider-defaulttokenfieldname) | public | `string` | The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself. |
| [`extraParams()`](#recaptchaprovider-extraparams) | public | `array<string, string>` | Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra. |
| [`classifyErrorCode()`](#recaptchaprovider-classifyerrorcode) | public | [`CaptchaErrorCategory`](#captchaerrorcategory) | Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary. |
| [`widgetV2()`](#recaptchaprovider-widgetv2) | public static | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | Widget metadata for the visible v2 checkbox. |
| [`widgetV3()`](#recaptchaprovider-widgetv3) | public static | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | Widget metadata for invisible v3 — widgetCssClass null means no checkbox; extraJs carries the whole grecaptcha.ready()/execute() (Promise-based) flow since there's no generic baseline for it. Uses form.requestSubmit(), never form.submit() — the latter skips native validation and the submit event, which defeats the point. No requestSubmit() support reports "requestsubmit_unsupported" via captcha:error instead of degrading. The "ready" flag is set only right before requestSubmit() (after checkValidity() passes), so it never sits stale on the form for a later, unrelated submit to consume without a fresh token. |

<details>
<summary><code>__construct()</code></summary>

#### `RecaptchaProvider __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L23)

```php
public function __construct(string $secretKey)
```

| Param | Type | Description |
|---|---|---|
| `$secretKey` | `string` | Provider-issued secret key for this site. |

</details>

<details>
<summary><code>endpoint()</code></summary>

#### `RecaptchaProvider endpoint()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L31)

```php
public function endpoint(): string
```

**Returns:** `string` — Absolute URL of this provider's siteverify endpoint.

</details>

<details>
<summary><code>secretKey()</code></summary>

#### `RecaptchaProvider secretKey()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L39)

```php
public function secretKey(): string
```

**Returns:** `string` — This provider's secret key.

</details>

<details>
<summary><code>defaultTokenFieldName()</code></summary>

#### `RecaptchaProvider defaultTokenFieldName()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L47)

```php
public function defaultTokenFieldName(): string
```

The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself.

**Returns:** `string`

</details>

<details>
<summary><code>extraParams()</code></summary>

#### `RecaptchaProvider extraParams()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L55)

```php
public function extraParams(): array
```

Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra.

**Returns:** `array<string, string>`

</details>

<details>
<summary><code>classifyErrorCode()</code></summary>

#### `RecaptchaProvider classifyErrorCode()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L63)

```php
public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
```

Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary.

| Param | Type | Description |
|---|---|---|
| `$rawCode` | `string` | A value from the provider's "error-codes" response field. |

**Returns:** [`CaptchaErrorCategory`](#captchaerrorcategory)

</details>

<details>
<summary><code>widgetV2()</code></summary>

#### `RecaptchaProvider widgetV2()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L80)

```php
public static function widgetV2(): CaptchaWidgetDescriptor
```

Widget metadata for the visible v2 checkbox.

**Returns:** [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor)

</details>

<details>
<summary><code>widgetV3()</code></summary>

#### `RecaptchaProvider widgetV3()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/RecaptchaProvider.php#L104)

```php
public static function widgetV3(): CaptchaWidgetDescriptor
```

Widget metadata for invisible v3 — widgetCssClass null means no checkbox; extraJs carries the whole grecaptcha.ready()/execute() (Promise-based) flow since there's no generic baseline for it. Uses form.requestSubmit(), never form.submit() — the latter skips native validation and the submit event, which defeats the point. No requestSubmit() support reports "requestsubmit_unsupported" via captcha:error instead of degrading. The "ready" flag is set only right before requestSubmit() (after checkValidity() passes), so it never sits stale on the form for a later, unrelated submit to consume without a fresh token.

**Returns:** [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor)

</details>

## `TurnstileErrorCode`

*enum* — `rafalmasiarek\Captcha\Provider\TurnstileErrorCode` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileErrorCode.php#L21)

Error codes documented by Cloudflare Turnstile's siteverify API. CaptchaResult::$errorCodes always keeps the raw strings Turnstile sent — an unrecognized/future code is never dropped by using this enum. This exists only to classify the *known* ones; TurnstileProvider uses it internally for CaptchaProviderInterface::classifyErrorCode(). A caller who wants raw-level detail beyond CaptchaResult::$errorCategories can also map $result->errorCodes through self::tryFrom() directly. Source: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/

backed by `string`

| Case | Value |
|---|---|
| `MissingInputSecret` | `'missing-input-secret'` |
| `InvalidInputSecret` | `'invalid-input-secret'` |
| `BadRequest` | `'bad-request'` |
| `MissingInputResponse` | `'missing-input-response'` |
| `InvalidInputResponse` | `'invalid-input-response'` |
| `TimeoutOrDuplicate` | `'timeout-or-duplicate'` |
| `InternalError` | `'internal-error'` |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`isConfigurationError()`](#turnstileerrorcode-isconfigurationerror) | public | `bool` | Whether this code indicates a problem with our own integration (wrong/missing secret key, malformed request) rather than a legitimately rejected token. |
| [`isTokenRejection()`](#turnstileerrorcode-istokenrejection) | public | `bool` | Whether this code indicates the token itself was legitimately rejected (expired, reused, malformed) rather than our integration being broken. |

<details>
<summary><code>isConfigurationError()</code></summary>

#### `TurnstileErrorCode isConfigurationError()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileErrorCode.php#L51)

```php
public function isConfigurationError(): bool
```

Whether this code indicates a problem with our own integration (wrong/missing secret key, malformed request) rather than a legitimately rejected token.

**Returns:** `bool`

</details>

<details>
<summary><code>isTokenRejection()</code></summary>

#### `TurnstileErrorCode isTokenRejection()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileErrorCode.php#L65)

```php
public function isTokenRejection(): bool
```

Whether this code indicates the token itself was legitimately rejected (expired, reused, malformed) rather than our integration being broken.

**Returns:** `bool`

</details>

## `TurnstileProvider`

*final class* — `rafalmasiarek\Captcha\Provider\TurnstileProvider` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L20)

Cloudflare Turnstile configuration. Turnstile-specific response fields not named on CaptchaResult — "cdata" (custom data set via the widget's data-cdata attribute) and "metadata.ephemeral_id" (for Cloudflare's own fraud-detection pipeline) — are always available via CaptchaResult::$raw.

implements [`CaptchaProviderInterface`](#captchaproviderinterface)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#turnstileprovider-__construct) | public | — | — |
| [`withIdempotencyKey()`](#turnstileprovider-withidempotencykey) | public | `self` | Returns a clone carrying an idempotency key — pass the SAME key across repeated verification attempts of the same token (e.g. a retried request after a transient failure) so Cloudflare does not flag the repeat as token reuse. |
| [`endpoint()`](#turnstileprovider-endpoint) | public | `string` | — |
| [`defaultTokenFieldName()`](#turnstileprovider-defaulttokenfieldname) | public | `string` | The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself. |
| [`secretKey()`](#turnstileprovider-secretkey) | public | `string` | — |
| [`extraParams()`](#turnstileprovider-extraparams) | public | `array<string, string>` | Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra. |
| [`classifyErrorCode()`](#turnstileprovider-classifyerrorcode) | public | [`CaptchaErrorCategory`](#captchaerrorcategory) | Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary. |
| [`widget()`](#turnstileprovider-widget) | public static | [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor) | Widget metadata for the visible Turnstile checkbox. |

<details>
<summary><code>__construct()</code></summary>

#### `TurnstileProvider __construct()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L26)

```php
public function __construct(string $secretKey, ?string $idempotencyKey)
```

| Param | Type | Description |
|---|---|---|
| `$secretKey` | `string` | Provider-issued secret key for this site. |
| `$idempotencyKey` | `string\|null` | See withIdempotencyKey(). |

</details>

<details>
<summary><code>withIdempotencyKey()</code></summary>

#### `TurnstileProvider withIdempotencyKey()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L42)

```php
public function withIdempotencyKey(string $idempotencyKey): self
```

Returns a clone carrying an idempotency key — pass the SAME key across repeated verification attempts of the same token (e.g. a retried request after a transient failure) so Cloudflare does not flag the repeat as token reuse.

| Param | Type | Description |
|---|---|---|
| `$idempotencyKey` | `string` | A UUID identifying this specific verification attempt. |

**Returns:** `self`

</details>

<details>
<summary><code>endpoint()</code></summary>

#### `TurnstileProvider endpoint()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L50)

```php
public function endpoint(): string
```

**Returns:** `string` — Absolute URL of this provider's siteverify endpoint.

</details>

<details>
<summary><code>defaultTokenFieldName()</code></summary>

#### `TurnstileProvider defaultTokenFieldName()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L58)

```php
public function defaultTokenFieldName(): string
```

The conventional HTML form field name this provider's widget posts its token under (e.g. "g-recaptcha-response") — a discoverable default for a caller that reads the token out of a request body, so nothing above this interface needs to hardcode any one vendor's convention. Purely informational: Captcha::verify() always takes the token value directly and never reads a request body itself.

**Returns:** `string`

</details>

<details>
<summary><code>secretKey()</code></summary>

#### `TurnstileProvider secretKey()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L66)

```php
public function secretKey(): string
```

**Returns:** `string` — This provider's secret key.

</details>

<details>
<summary><code>extraParams()</code></summary>

#### `TurnstileProvider extraParams()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L74)

```php
public function extraParams(): array
```

Additional POST fields beyond secret/response/remoteip — e.g. hCaptcha's optional "sitekey" or Turnstile's optional "idempotency_key". Empty for a provider that needs nothing extra.

**Returns:** `array<string, string>`

</details>

<details>
<summary><code>classifyErrorCode()</code></summary>

#### `TurnstileProvider classifyErrorCode()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L82)

```php
public function classifyErrorCode(string $rawCode): CaptchaErrorCategory
```

Classifies one of this provider's own raw error codes into the shared, provider-agnostic CaptchaErrorCategory vocabulary.

| Param | Type | Description |
|---|---|---|
| `$rawCode` | `string` | A value from the provider's "error-codes" response field. |

**Returns:** [`CaptchaErrorCategory`](#captchaerrorcategory)

</details>

<details>
<summary><code>widget()</code></summary>

#### `TurnstileProvider widget()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/Provider/TurnstileProvider.php#L100)

```php
public static function widget(): CaptchaWidgetDescriptor
```

Widget metadata for the visible Turnstile checkbox.

**Returns:** [`CaptchaWidgetDescriptor`](#captchawidgetdescriptor)

</details>

## `RemoteIpProviderInterface`

*interface* — `rafalmasiarek\Captcha\RemoteIpProviderInterface` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/RemoteIpProviderInterface.php#L32)

Supplies the real client IP address for a verification call — e.g. backed by rafalmasiarek/real-ip-resolver's RealIpResolver (or any equivalent), which resolves the real client IP behind trusted reverse proxies instead of a raw $_SERVER['REMOTE_ADDR']. rafalmasiarek/real-ip-resolver's RealIpResolver doesn't implement this interface directly (different method name/return type), so bridge it with a small adapter: final class RealIpResolverAdapter implements RemoteIpProviderInterface { public function __construct(private readonly RealIpResolver $resolver) {} public function getRemoteIp(): ?string { $ip = $this->resolver->getIp(); return $ip !== '' ? $ip : null; } } Can be passed per-call to CaptchaVerifierInterface::verify(), or bound once as Captcha's default via its constructor.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`getRemoteIp()`](#remoteipproviderinterface-getremoteip) | public | `string\|null` | — |

<details>
<summary><code>getRemoteIp()</code></summary>

#### `RemoteIpProviderInterface getRemoteIp()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/RemoteIpProviderInterface.php#L37)

```php
public function getRemoteIp(): ?string
```

**Returns:** `string\|null` — The resolved real client IP, or null when unknown.

</details>

## `SystemRemoteIpProvider`

*final class* — `rafalmasiarek\Captcha\SystemRemoteIpProvider` · [source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/SystemRemoteIpProvider.php#L19)

Naive, zero-dependency default: reads $_SERVER['REMOTE_ADDR'] directly, with no trusted-proxy awareness — the same role SystemDnsResolver plays for DNS. Used automatically when neither a per-call nor a Captcha-bound RemoteIpProviderInterface is configured. A caller behind a reverse proxy (Cloudflare, a load balancer, ...) should supply their own resolver instead — e.g. an adapter over rafalmasiarek/real-ip-resolver's RealIpResolver (see RemoteIpProviderInterface).

implements [`RemoteIpProviderInterface`](#remoteipproviderinterface)

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`getRemoteIp()`](#systemremoteipprovider-getremoteip) | public | `string\|null` | — |

<details>
<summary><code>getRemoteIp()</code></summary>

#### `SystemRemoteIpProvider getRemoteIp()`

[source](https://github.com/rafalmasiarek/php-captcha/blob/main/src/SystemRemoteIpProvider.php#L24)

```php
public function getRemoteIp(): ?string
```

**Returns:** `string\|null` — The resolved real client IP, or null when unknown.

</details>

