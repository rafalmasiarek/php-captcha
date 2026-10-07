<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Naive, zero-dependency default: reads $_SERVER['REMOTE_ADDR'] directly,
 * with no trusted-proxy awareness — the same role SystemDnsResolver plays
 * for DNS. Used automatically when neither a per-call nor a Captcha-bound
 * RemoteIpProviderInterface is configured.
 *
 * A caller behind a reverse proxy (Cloudflare, a load balancer, ...) should
 * supply their own resolver instead — e.g. an adapter over
 * rafalmasiarek/real-ip-resolver's RealIpResolver (see RemoteIpProviderInterface).
 *
 * @package rafalmasiarek\Captcha
 */
final class SystemRemoteIpProvider implements RemoteIpProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function getRemoteIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return \is_string($ip) && $ip !== '' ? $ip : null;
    }
}
