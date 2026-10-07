<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Supplies the real client IP address for a verification call — e.g. backed
 * by rafalmasiarek/real-ip-resolver's RealIpResolver (or any equivalent),
 * which resolves the real client IP behind trusted reverse proxies instead
 * of a raw $_SERVER['REMOTE_ADDR'].
 *
 * php-captcha does not depend on any specific resolver implementation — this
 * interface is the seam a caller's own resolver can satisfy (directly, or
 * via a small adapter), as an alternative to passing a plain string to
 * verify(). Passing a raw string remains fully supported; this exists for
 * callers who'd rather hand over "the thing that knows the real IP" and let
 * it be resolved lazily, right before the request, rather than resolving it
 * themselves first.
 *
 * @package rafalmasiarek\Captcha
 */
interface RemoteIpProviderInterface
{
    /**
     * @return string|null The resolved real client IP, or null when unknown.
     */
    public function getRemoteIp(): ?string;
}
