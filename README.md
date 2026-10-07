# rafalmasiarek/captcha

Universal CAPTCHA verification interface for PHP: Google reCAPTCHA (v2/v3), Cloudflare Turnstile, and hCaptcha all behind one interface. Built on [`rafalmasiarek/http-client`](https://github.com/rafalmasiarek/php-http-client) — no provider-specific SDK, no cURL/Guzzle dependency of its own.

## Why

reCAPTCHA, Turnstile, and hCaptcha all speak a near-identical "siteverify" protocol: POST a secret key + the client-submitted token (+ optionally the user's IP) to a fixed endpoint, get back `{success, ...}` as JSON. Turnstile and hCaptcha deliberately mirror reCAPTCHA's wire format for drop-in compatibility. This library exploits that: one shared implementation, three thin subclasses differing only in endpoint URL and the rare provider-specific extra field.

Deliberately out of scope: widget rendering (script URLs, `data-*` attributes, the client-side form field name). That differs per provider *and* per consuming framework/template engine, so it belongs to the caller — this library only covers server-side verification.

## Namespace layout

- `rafalmasiarek\Captcha\*` — the core contract: `CaptchaVerifierInterface`, `CaptchaResult`, `CaptchaErrorCode`, `RemoteIpProviderInterface`, and the exception hierarchy. Stable, provider-agnostic.
- `rafalmasiarek\Captcha\Provider\*` — the three built-in providers (`RecaptchaVerifier`, `TurnstileVerifier`, `HCaptchaVerifier`) plus the shared `AbstractSiteVerifyVerifier` they're built on. A consumer plugging in an entirely custom provider only ever needs to implement `CaptchaVerifierInterface` directly — nothing in this sub-namespace is required.

## Install

```bash
composer require rafalmasiarek/captcha
```

## Usage

```php
use rafalmasiarek\Captcha\Provider\RecaptchaVerifier;
use rafalmasiarek\Captcha\Provider\TurnstileVerifier;
use rafalmasiarek\Captcha\Provider\HCaptchaVerifier;
use rafalmasiarek\Captcha\CaptchaTimeoutException;
use rafalmasiarek\Captcha\CaptchaTransportException;
use rafalmasiarek\Captcha\CaptchaResponseException;
use rafalmasiarek\HttpClient\Http\CurlHttpClient;
use rafalmasiarek\DnsResolver\SystemDnsResolver;

$http = new CurlHttpClient(new SystemDnsResolver());

$verifier = new RecaptchaVerifier($http, $secretKey);
// or: new TurnstileVerifier($http, $secretKey);
// or: new HCaptchaVerifier($http, $secretKey, siteKey: $siteKey); // siteKey optional

try {
    // $remoteIp should be the real client IP, resolved behind any trusted reverse
    // proxy — e.g. via rafalmasiarek/real-ip-resolver — not a raw $_SERVER['REMOTE_ADDR'].
    $result = $verifier->verify($tokenFromClient, $remoteIp);
} catch (CaptchaTimeoutException $e) {
    // the provider didn't respond within the timeout
} catch (CaptchaTransportException $e) {
    // couldn't reach the provider at all (DNS, connection refused, TLS, ...)
} catch (CaptchaResponseException $e) {
    // the provider responded, but the body wasn't valid JSON
}

if ($result->success) {
    // accepted
}

// reCAPTCHA v3 / hCaptcha Enterprise only — null for v2/Turnstile/standard hCaptcha
$result->score;
$result->action;

// raw provider error codes (never filtered — an unrecognized/future code is still here)
$result->errorCodes;

// the same codes, mapped to CaptchaErrorCode where recognized
$result->knownErrorCodes();

// true when a known code means OUR integration is misconfigured (wrong/missing
// secret, malformed request) rather than the token being legitimately rejected
$result->isConfigurationError();

// the full decoded JSON response — reach in here for a provider-specific field
// this DTO doesn't name, e.g. Turnstile's "cdata"/"metadata" or hCaptcha's "credit"
$result->raw;
```

### Passing the remote IP via an object instead of a string

`verify()` accepts `string|RemoteIpProviderInterface|null` for `$remoteIp`. Passing a plain string is the common case; `RemoteIpProviderInterface` is a zero-dependency seam for a caller's own IP resolver (e.g. a thin adapter over `rafalmasiarek/real-ip-resolver`'s `RealIpResolver`) to satisfy, resolving the IP lazily right before the request instead of the caller resolving it upfront:

```php
use rafalmasiarek\Captcha\RemoteIpProviderInterface;

final class RealIpResolverAdapter implements RemoteIpProviderInterface
{
    public function __construct(private readonly RealIpResolver $resolver) {}

    public function getRemoteIp(): ?string
    {
        return $this->resolver->resolve();
    }
}

$verifier->verify($token, new RealIpResolverAdapter($realIpResolver));
```

### Depending on the interface, not a concrete provider

Every concrete verifier implements `CaptchaVerifierInterface` — consumers should depend on that, and pick the concrete class at the wiring/config layer:

```php
function makeVerifier(string $provider, HttpClientInterface $http, array $config): CaptchaVerifierInterface
{
    return match ($provider) {
        'recaptcha' => new RecaptchaVerifier($http, $config['secret']),
        'turnstile' => new TurnstileVerifier($http, $config['secret']),
        'hcaptcha'  => new HCaptchaVerifier($http, $config['secret'], $config['site_key'] ?? null),
    };
}
```

### Provider-specific extras

A provider-specific capability that doesn't belong on the universal interface is an extra public method on the concrete class instead — callers who need it type-hint the concrete provider, not the interface:

```php
// Turnstile: re-verify the same token (e.g. after a retried request) without
// Cloudflare flagging it as reuse.
$turnstile->verifyWithIdempotencyKey($token, $idempotencyKey, $remoteIp);
```

## Error handling

Two independent axes:

- **Did the call itself fail?** (`CaptchaVerificationException` and its subtypes `CaptchaTimeoutException` / `CaptchaTransportException` / `CaptchaResponseException`) — a network/parsing problem, never thrown for a legitimately rejected token.
- **Why was a token rejected?** (`CaptchaResult::$errorCodes`, `knownErrorCodes()`, `isConfigurationError()`) — a normal, successfully-completed call that reports `success: false`.

Timeout detection matches `HttpResponseInterface::getError()`'s free-text message against curl's own stable English error strings — `rafalmasiarek/http-client` doesn't expose a structured transport-error code today, so this is a best-effort heuristic, not a guarantee.

`CaptchaErrorCode` catalogs the error codes documented by all three providers (`missing-input-secret`, `invalid-input-secret`, `bad-request`, `sitekey-secret-mismatch` → `isConfigurationError()`; `missing-input-response`, `invalid-input-response`, `timeout-or-duplicate`, `invalid-or-already-seen-response` → `isTokenRejection()`) — see the enum's own docblock for sources. `CaptchaResult::$errorCodes` always keeps every raw string the provider sent, so an unrecognized/future code is never dropped even though it won't appear in `knownErrorCodes()`.

## Testing with official test keys

Every provider publishes a secret/site key pair that always verifies successfully, meant exactly for integration testing like this:

| Provider | Secret key |
|---|---|
| reCAPTCHA v2 | `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe` |
| hCaptcha | `0x0000000000000000000000000000000000000000` |
| Turnstile | `1x0000000000000000000000000000000AA` |

## License

MIT
