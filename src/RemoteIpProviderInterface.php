<?php

declare(strict_types=1);

namespace rafalmasiarek\Captcha;

/**
 * Supplies the real client IP address for a verification call — e.g. backed
 * by rafalmasiarek/real-ip-resolver's RealIpResolver (or any equivalent),
 * which resolves the real client IP behind trusted reverse proxies instead
 * of a raw $_SERVER['REMOTE_ADDR'].
 *
 * rafalmasiarek/real-ip-resolver's RealIpResolver doesn't implement this
 * interface directly (different method name/return type), so bridge it
 * with a small adapter:
 *
 *     final class RealIpResolverAdapter implements RemoteIpProviderInterface
 *     {
 *         public function __construct(private readonly RealIpResolver $resolver) {}
 *         public function getRemoteIp(): ?string
 *         {
 *             $ip = $this->resolver->getIp();
 *             return $ip !== '' ? $ip : null;
 *         }
 *     }
 *
 * Can be passed per-call to CaptchaVerifierInterface::verify(), or bound
 * once as Captcha's default via its constructor.
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
