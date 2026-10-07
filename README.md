# rafalmasiarek/captcha

Universal CAPTCHA verification interface for PHP: Google reCAPTCHA (v2/v3), Cloudflare Turnstile, and hCaptcha all behind one interface. Built on [`rafalmasiarek/http-client`](https://github.com/rafalmasiarek/php-http-client) — no provider-specific SDK, no cURL/Guzzle dependency of its own.

## Why

reCAPTCHA, Turnstile, and hCaptcha all speak a near-identical "siteverify" protocol: POST a secret key + the client-submitted token (+ optionally the user's IP) to a fixed endpoint, get back `{success, ...}` as JSON. Turnstile and hCaptcha deliberately mirror reCAPTCHA's wire format for drop-in compatibility. This library exploits that: one shared implementation, three thin subclasses differing only in endpoint URL and the rare provider-specific extra field.

Deliberately out of scope: widget rendering (script URLs, `data-*` attributes, the client-side form field name). That differs per provider *and* per consuming framework/template engine, so it belongs to the caller — this library only covers server-side verification.

## Install

```bash
composer require rafalmasiarek/captcha
```

## Usage

```php
use rafalmasiarek\Captcha\RecaptchaVerifier;
use rafalmasiarek\Captcha\TurnstileVerifier;
use rafalmasiarek\Captcha\HCaptchaVerifier;
use rafalmasiarek\Captcha\CaptchaVerificationException;
use rafalmasiarek\HttpClient\Http\CurlHttpClient;
use rafalmasiarek\DnsResolver\SystemDnsResolver;

$http = new CurlHttpClient(new SystemDnsResolver());

$verifier = new RecaptchaVerifier($http, $secretKey);
// or: new TurnstileVerifier($http, $secretKey);
// or: new HCaptchaVerifier($http, $secretKey, siteKey: $siteKey); // siteKey optional

try {
    $result = $verifier->verify($tokenFromClient, $remoteIp);
} catch (CaptchaVerificationException $e) {
    // transport failure (network, malformed response) — not a rejected token
}

if ($result->success) {
    // accepted
}

// reCAPTCHA v3 / hCaptcha Enterprise only — null for v2/Turnstile/standard hCaptcha
$result->score;
$result->action;

// provider-specific error codes, e.g. "timeout-or-duplicate"
$result->errorCodes;
```

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

## Testing with official test keys

Every provider publishes a secret/site key pair that always verifies successfully, meant exactly for integration testing like this:

| Provider | Secret key |
|---|---|
| reCAPTCHA v2 | `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe` |
| hCaptcha | `0x0000000000000000000000000000000000000000` |
| Turnstile | `1x0000000000000000000000000000000AA` |

## License

MIT
