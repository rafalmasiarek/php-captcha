# rafalmasiarek/captcha

Universal CAPTCHA verification for PHP: Google reCAPTCHA (v2/v3), Cloudflare Turnstile, and hCaptcha behind one Strategy-pattern facade. Built on [`rafalmasiarek/http-client`](https://github.com/rafalmasiarek/php-http-client) — no provider-specific SDK, no cURL/Guzzle dependency of its own.

## Why

reCAPTCHA, Turnstile, and hCaptcha all speak a near-identical "siteverify" protocol: POST a secret key + the client-submitted token (+ optionally the user's IP) to a fixed endpoint, get back `{success, ...}` as JSON. Turnstile and hCaptcha deliberately mirror reCAPTCHA's wire format for drop-in compatibility.

`Captcha` is the one class that speaks this protocol — its constructor and `verify()` signature never change based on which provider is configured. Switching providers means swapping one constructor argument (a `CaptchaProviderInterface`); nothing else in your code changes, including how you read errors — see "Provider-agnostic error categories" below.

Deliberately out of scope: widget rendering (script URLs, `data-*` attributes, the client-side form field name). That differs per provider *and* per consuming framework/template engine, so it belongs to the caller — this library only covers server-side verification.

## Namespace layout

- `rafalmasiarek\Captcha\*` — `Captcha` itself, `CaptchaProviderInterface`, `CaptchaResult`, `CaptchaErrorCategory`, `RemoteIpProviderInterface`/`SystemRemoteIpProvider`, and the exception hierarchy. Knows the *siteverify protocol shape*, but no specific vendor by name.
- `rafalmasiarek\Captcha\Provider\*` — the three built-in providers (`RecaptchaProvider`, `TurnstileProvider`, `HCaptchaProvider`), each one's own fully self-contained error-code enum (`RecaptchaErrorCode`, `TurnstileErrorCode`, `HCaptchaErrorCode`), and `CaptchaWidgetDescriptor` (raw widget metadata — see below). These are the only classes that know a vendor's name, endpoint, or exact error-code vocabulary. Each provider builds its own widget descriptor(s) directly — nothing central to edit when adding a new one.
- `rafalmasiarek\Captcha\Helpers\*` — entirely optional widget-rendering helpers (see below). Verification itself (`endpoint()`/`secretKey()`/`extraParams()`/`classifyErrorCode()`) never depends on this namespace.
- An entirely custom provider that doesn't follow the siteverify protocol at all implements `CaptchaVerifierInterface` directly, bypassing `Captcha`/`CaptchaProviderInterface` entirely.

## Install

```bash
composer require rafalmasiarek/captcha
```

## Usage

```php
use rafalmasiarek\Captcha\Captcha;
use rafalmasiarek\Captcha\Provider\RecaptchaProvider;
use rafalmasiarek\Captcha\Provider\TurnstileProvider;
use rafalmasiarek\Captcha\Provider\HCaptchaProvider;
use rafalmasiarek\Captcha\CaptchaTimeoutException;
use rafalmasiarek\Captcha\CaptchaTransportException;
use rafalmasiarek\Captcha\CaptchaResponseException;

// Provider-specific arguments live ONLY on the provider object.
$provider = new RecaptchaProvider($secretKey);
// or: new TurnstileProvider($secretKey);
// or: new HCaptchaProvider($secretKey, siteKey: $siteKey);

// Captcha's constructor is IDENTICAL regardless of which provider you pass.
$captcha = new Captcha($provider);

try {
    $result = $captcha->verify($tokenFromClient, $remoteIp);
} catch (CaptchaTimeoutException $e) {
    // the provider didn't respond within the timeout — $e->transportInfo has dns/connect/tls/total timing
} catch (CaptchaTransportException $e) {
    // couldn't reach the provider at all (DNS, connection refused, TLS, ...) — $e->transportInfo too
} catch (CaptchaResponseException $e) {
    // the provider responded, but the body wasn't valid JSON — $e->statusCode
}

if ($result->success) {
    // accepted
}
```

### $http and $remoteIp: override what you need, sensible defaults otherwise

Both follow the same "explicit override, zero-config fallback" shape:

```php
// $http: omit it to get CurlHttpClient(SystemDnsResolver()) automatically.
$captcha = new Captcha($provider, http: $myConfiguredHttpClient);

// $remoteIp: omit it on verify() to get SystemRemoteIpProvider's naive
// $_SERVER['REMOTE_ADDR'] automatically. Three levels of precedence:
$captcha->verify($token, $remoteIp);              // 1. explicit per-call value wins
$captcha = new Captcha($provider, defaultIpProvider: $resolver);  // 2. bound once, used when verify() doesn't override
// 3. SystemRemoteIpProvider — used when neither of the above is set
```

`rafalmasiarek/real-ip-resolver`'s `RealIpResolver` resolves the real client IP behind trusted reverse proxies, but doesn't implement `RemoteIpProviderInterface` directly (different method name/return type) — bridge it with a one-line adapter:

```php
use rafalmasiarek\Captcha\RemoteIpProviderInterface;

final class RealIpResolverAdapter implements RemoteIpProviderInterface
{
    public function __construct(private readonly RealIpResolver $resolver) {}

    public function getRemoteIp(): ?string
    {
        $ip = $this->resolver->getIp();
        return $ip !== '' ? $ip : null;
    }
}

$captcha = new Captcha($provider, defaultIpProvider: new RealIpResolverAdapter($realIpResolver));
```

### Provider-specific extras live on the provider, not on Captcha

A capability only one provider has is a method on *its* provider class — `Captcha::verify()` never changes shape to accommodate it:

```php
// Turnstile: re-verify the same token (e.g. after a retried request) without
// Cloudflare flagging it as reuse. withIdempotencyKey() returns a new provider
// instance; Captcha itself doesn't need to know this capability exists.
$provider = (new TurnstileProvider($secretKey))->withIdempotencyKey($idempotencyKey);
$captcha = new Captcha($provider);
$captcha->verify($token, $remoteIp);
```

### reCAPTCHA v3 / hCaptcha Enterprise score and action thresholds

```php
$captcha = new Captcha($provider, minScore: 0.5, expectedAction: 'login', expectedHostname: 'example.com');
```

`$result->success` folds in every configured threshold check alongside the provider's own verdict, so a caller only ever needs to check one field. **Fails closed**: once `minScore`/`expectedAction`/`expectedHostname` is configured, a response that omits that field (or returns it as the wrong type) does **not** satisfy the check — it never silently passes just because the provider/mode didn't return it. Leave the corresponding parameter `null` for a provider/mode that genuinely never returns it (reCAPTCHA v2, Turnstile, standard hCaptcha) rather than configuring a check that can never pass.

`minScore` is validated at construction time (must be finite, within `[0.0, 1.0]`); `expectedAction`/`expectedHostname` must not be empty strings. A malformed response — `score`/`action`/`hostname` present but the wrong type, or `score` outside `[0.0, 1.0]`, or `success` present but not a boolean — throws `CaptchaResponseException` rather than being silently coerced; `challenge_ts` is the one exception, coerced leniently since it's purely informational and never factors into `$result->success`.

### Provider-agnostic error categories

This is the point of the whole design: `CaptchaResult::$errorCategories` uses the **same enum** no matter which provider produced the result, so your error-handling/stats/debug code never changes when you swap providers.

```php
use rafalmasiarek\Captcha\CaptchaErrorCategory;

if (\in_array(CaptchaErrorCategory::ConfigurationError, $result->errorCategories, true)) {
    // our secret key / request is wrong — fix the integration, not a user retry
}
if (\in_array(CaptchaErrorCategory::TokenRejected, $result->errorCategories, true)) {
    // the token was legitimately rejected (expired, reused, malformed)
}
if (\in_array(CaptchaErrorCategory::ScoreTooLow, $result->errorCategories, true)) {
    // passed the provider's own check, but didn't meet $minScore (or the response omitted a score at all)
}
if (\in_array(CaptchaErrorCategory::HostnameMismatch, $result->errorCategories, true)) {
    // didn't match $expectedHostname
}
```

`CaptchaResult::$errorCodes` still carries the raw, provider-specific strings (e.g. `"timeout-or-duplicate"`) for anyone who wants that level of detail — map them through the concrete provider's own enum (`RecaptchaErrorCode::tryFrom()`, etc., under `Provider\`) when you know which provider you're using.

`CaptchaResult::toDebugArray()` returns a flat, stable-shaped array (`success`, `score`, `action`, `error_codes`, `error_categories`, `challenge_ts`, `hostname`) ready for a log context (Bugsnag, PSR-3) — identical keys regardless of provider.

### $raw: nothing is ever lost

`CaptchaResult::$raw` is the full decoded JSON response — reach into it for a provider-specific field the DTO doesn't name, e.g. Turnstile's `cdata`/`metadata` or hCaptcha's `credit`/`score_reason`.

## Error handling

Two independent axes:

- **Did the call itself fail?** (`CaptchaVerificationException` and its subtypes `CaptchaTimeoutException` / `CaptchaTransportException` — both carry `$transportInfo`, a snapshot of `HttpResponseInterface::getInfo()` for diagnostics — and `CaptchaResponseException`, which carries `$statusCode`.) A network/parsing problem, never thrown for a legitimately rejected token.
- **Why was a token rejected?** `CaptchaResult::$errorCategories` (provider-agnostic) and `$errorCodes` (raw) — a normal, successfully-completed call that reports `success: false`.

Timeout detection checks `HttpResponseInterface::getErrorKind() === TransportErrorKind::Timeout` — a structured classification `rafalmasiarek/http-client` computes from curl's own error code, not a string match against `getError()`'s free-text message.

## Optional widget-rendering helpers

Verification stays provider-agnostic and renders nothing — but a small, genuinely optional `Helpers\` layer ships alongside it for the common case of actually drawing the widget, mirroring the pattern used by [`rafalmasiarek/csrf-token`](https://github.com/rafalmasiarek/php-csrf)'s own `Helpers\`. `HtmlHelper` holds no provider knowledge at all — it only ever reads generic data off `CaptchaWidgetDescriptor`.

- `Provider\CaptchaWidgetDescriptor` — pure data (strings/arrays, no closures): script URL, CSS class, token field name, plus four generic, always-additive extension points any provider can fill in as needed — `scriptUrlParams` (extra query params on the script URL), `extraCssClasses`/`extraAttributes` (on the widget element — always applied, visible or invisible, no silently-ignored combination), `extraHiddenFields` (extra hidden inputs), `extraJs` (extra inline JS, appended after the base glue, each entry a complete, independent statement). Attribute/field names and CSS classes are validated at construction time — an unsafe name (e.g. an `on*` handler, or one of `HtmlHelper`'s own reserved attributes) throws `\InvalidArgumentException` immediately rather than silently rendering. Each provider builds its own: `RecaptchaProvider::widgetV2()`/`::widgetV3()`, `TurnstileProvider::widget()`, `HCaptchaProvider::widget()`. Adding a new provider needs no change outside its own file — not even here.
- `Helpers\HtmlHelper` — template-engine-agnostic: `widget()`/`scripts()` return plain HTML strings. Zero dependency on any template engine, any CSS framework, or any JS minifier; call it directly from raw PHP.
- `Helpers\Twig\CaptchaExtension` — Twig extension exposing `captcha_widget()`/`captcha_scripts()` Twig functions.
- `Helpers\Blade\CaptchaBlade::register($bladeCompiler)` — registers `@captchaWidget(...)`/`@captchaScripts(...)` Blade directives.
- `Helpers\Plates\CaptchaExtension::register($engine)` — registers `captcha_widget()`/`captcha_scripts()` Plates template functions.

None of Twig/Laravel/Plates is declared anywhere in `composer.json` — not even in `suggest` — matching `rafalmasiarek/csrf-token`'s own convention exactly. These classes are never autoloaded unless a consumer actually references them, so the corresponding package is never required just because the file exists; a consumer who wants one of these helpers already has that template engine in their own project.

### Placeholder tokens in `scriptUrlParams`/`extraJs`

A provider's `widget()` method can reference the caller's eventual site key/action/instance id without knowing them yet, via tokens substituted by `HtmlHelper` at render time:

| Token | Context | Form |
|---|---|---|
| `__CAPTCHA_SITE_KEY__` | `scriptUrlParams` only | raw, URL-encoded |
| `__CAPTCHA_ACTION__` | `scriptUrlParams` only | raw, URL-encoded |
| `__CAPTCHA_SITE_KEY_JS__` | `extraJs` only | JSON-encoded, `<script>`-safe |
| `__CAPTCHA_ACTION_JS__` | `extraJs` only | JSON-encoded, `<script>`-safe |
| `__CAPTCHA_INSTANCE_ID_JS__` | `extraJs` only | JSON-encoded, `<script>`-safe |

The `__CAPTCHA_..._​__` shape (not `{name}`) is deliberate: it can't collide with ordinary JS object/block syntax, so an unrecognized or wrong-context token (a typo, or a JS token used inside `scriptUrlParams`) throws `\InvalidArgumentException` instead of silently passing through as garbage. JSON encoding for the `_JS` forms uses `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT` — a value containing `</script>` or `<script>` can never prematurely terminate the surrounding `<script>` element.

### Multiple widgets on one page

Pass the **same** `$instanceId` to both the `widget()` and `scripts()` call for one widget, and a **distinct** one per widget when rendering more than one:

```php
<?= HtmlHelper::widget($widgetA, $siteKeyA, instanceId: 'login-form') ?>
<?= HtmlHelper::scripts($widgetA, $siteKeyA, instanceId: 'login-form') ?>

<?= HtmlHelper::widget($widgetB, $siteKeyB, instanceId: 'newsletter-form') ?>
<?= HtmlHelper::scripts($widgetB, $siteKeyB, instanceId: 'newsletter-form') ?>
```

Every DOM lookup is scoped by `[data-captcha-instance="..."]`, and the default `successCallback`/`expiredCallback` window-global names are derived from `$instanceId` (`captchaSuccess_<id>`/`captchaExpired_<id>`) — two widgets never collide, even with every other parameter left at its default. `$instanceId` must match `/^[a-zA-Z0-9_-]+$/`; the single-widget-per-page case needs no `$instanceId` at all (defaults to `'default'`).

### Styling feedback — no CSS framework assumed

A visible widget's `scripts()` output never touches a submit button's `disabled` state (that stays entirely the host application's call) — instead it gates the form's `submit` event directly, and toggles a plain `.captcha-invalid` class on the widget element plus `hidden` on an adjacent `.captcha-feedback` element (`role="alert"`, `aria-live="assertive"`, linked via `aria-describedby`). Style both however you like; nothing here assumes Bootstrap or any other framework. The feedback text is a plain parameter, not hardcoded English:

```php
HtmlHelper::widget($widget, $siteKey, validationMessage: 'Proszę potwierdzić, że nie jesteś robotem.');
```

### Content Security Policy

`scripts()` takes an optional `$nonce`, applied to both the vendor `<script src="...">` tag and the inline glue `<script>` tag:

```php
HtmlHelper::scripts($widget, $siteKey, nonce: $cspNonceForThisRequest);
```

You still need to allow the provider's own script domain (`www.google.com`, `challenges.cloudflare.com`, `js.hcaptcha.com`) in your `script-src` directive — a nonce on your own tags doesn't relax that. Different providers' widgets may make further same-origin/frame-ancestors demands of their own (e.g. `frame-src`); consult each vendor's own CSP guidance. `'unsafe-inline'` is never the answer this library suggests.

### Reacting to a client-side failure: the `captcha:error` event

A provider's own glue JS can fail client-side in ways the server never sees — a timeout waiting for the widget's own `.execute()`-style call, that call rejecting, or an unexpected exception (currently emitted by `RecaptchaProvider::widgetV3()`'s invisible-widget flow; any provider's `extraJs` can dispatch the same event the same way). Rather than this library deciding how your app should surface that, it dispatches one generic, bubbling `CustomEvent`:

```js
document.addEventListener('captcha:error', function (e) {
    // e.detail.instanceId — the widget's instanceId (see "Multiple widgets" above)
    // e.detail.reason     — 'timeout' | 'execute_failed' | 'exception'
    console.warn('CAPTCHA failed:', e.detail.instanceId, e.detail.reason);
});
```

It's dispatched on the `<form>` element with `bubbles: true`, so one listener on `document` catches it for every widget on the page — no per-widget wiring needed. This is the one, generic hook; bridging it into your own error-reporting system (Bugsnag, Sentry, a homegrown one) is a few lines in your own JS, not something this library needs to know about:

```js
document.addEventListener('captcha:error', function (e) {
    MyErrorReporter.report('CAPTCHA verification failed: ' + e.detail.reason, {
        component: 'captcha',
        metadata: { instanceId: e.detail.instanceId, reason: e.detail.reason }
    });
});
```

### Adding a new provider

A new provider is self-contained — nothing outside its own file(s) needs editing:

1. `Provider\MyProvider implements CaptchaProviderInterface` — verification (`endpoint()`/`secretKey()`/`extraParams()`/`classifyErrorCode()`/`defaultTokenFieldName()`), plus a `widget()` (or `widgetX()` per variant, like `RecaptchaProvider`) static method returning a `CaptchaWidgetDescriptor`.
2. `Provider\MyProviderErrorCode` — that provider's own raw error-code enum, consumed only by `MyProvider::classifyErrorCode()`.

That's it — always just those 2 files. A provider needing more than the generic declarative flow (official script + data-sitekey div) doesn't need a 3rd file or any new concept: it fills in `CaptchaWidgetDescriptor`'s `extraJs`/`scriptUrlParams`/etc. directly in its own `widget()` method, using the placeholder tokens above. `RecaptchaProvider::widgetV3()` is the one built-in example — reCAPTCHA v3 has no checkbox (`widgetCssClass: null`) and must call `grecaptcha.execute()` itself, so its entire glue lives in `extraJs`, using `__CAPTCHA_SITE_KEY_JS__`/`__CAPTCHA_ACTION_JS__`/`__CAPTCHA_INSTANCE_ID_JS__`.

`HtmlHelper` and every template-engine wrapper consume `CaptchaWidgetDescriptor` generically — they never enumerate providers, so none of them needs touching.

### Plain PHP — no template engine at all

`HtmlHelper` is just two static methods returning strings; nothing about it requires Twig/Blade/Plates or any rendering framework. This is the entire integration in a bare `.php` view, or even built inline in a controller:

```php
<?php
use rafalmasiarek\Captcha\Helpers\HtmlHelper;
use rafalmasiarek\Captcha\Provider\TurnstileProvider;

$widget = TurnstileProvider::widget();
?>
<form method="post" action="/login">
    <input type="text" name="username">
    <input type="password" name="password">

    <?= HtmlHelper::widget($widget, $siteKey) ?>

    <button type="submit">Log in</button>
</form>
<?= HtmlHelper::scripts($widget, $siteKey) ?>
```

On the receiving end, read the token back out under the field name the widget itself reports — no hardcoded `'g-recaptcha-response'`/`'cf-turnstile-response'`/`'h-captcha-response'` anywhere in your code:

```php
$token = $_POST[$widget->tokenFieldName] ?? '';
$result = $captcha->verify($token, $remoteIp);
```

### With a template engine

```twig
{# with Helpers\Twig\CaptchaExtension registered #}
{{ captcha_widget(widget, siteKey) }}
{{ captcha_scripts(widget, siteKey) }}
```

```blade
{{-- with Helpers\Blade\CaptchaBlade::register($bladeCompiler) called --}}
@captchaWidget($widget, $siteKey)
@captchaScripts($widget, $siteKey)
```

```php
<?php // with Helpers\Plates\CaptchaExtension::register($engine) called ?>
<?= $this->captcha_widget($widget, $siteKey) ?>
<?= $this->captcha_scripts($widget, $siteKey) ?>
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
