<?php

declare(strict_types=1);

namespace ICMS\Infrastructure\Auth;

/**
 * JWT access + refresh token service.
 * Keys are read from constants defined in wp-config.php:
 *   ICMS_JWT_SECRET        — HS256 HMAC secret (min 32 chars)
 *   ICMS_JWT_ACCESS_TTL    — access token TTL in seconds (default: 900 = 15 min)
 *   ICMS_JWT_REFRESH_TTL   — refresh token TTL in seconds (default: 604800 = 7 days)
 *
 * Uses firebase/php-jwt when available, falls back to manual HS256 encoding.
 */
final class JwtService
{
    private const OPTION_JWT_SECRET = 'icms_back_jwt_secret';

    private string $secret;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct()
    {
        $secret = $this->resolveSecret();

        $this->secret = $secret;
        $this->accessTtl = defined('ICMS_JWT_ACCESS_TTL') ? (int) constant('ICMS_JWT_ACCESS_TTL') : 900;
        $this->refreshTtl = defined('ICMS_JWT_REFRESH_TTL') ? (int) constant('ICMS_JWT_REFRESH_TTL') : 604800;
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function issueAccessToken(array $claims): string
    {
        $this->assertSecretIsConfigured();

        $now = time();

        return $this->encode(array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'type' => 'access',
        ]));
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function issueRefreshToken(array $claims): string
    {
        $this->assertSecretIsConfigured();

        $now = time();

        return $this->encode(array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'type' => 'refresh',
        ]));
    }

    /**
     * Validates a token and returns its payload, or null if invalid/expired.
     *
     * @return array<string, mixed>|null
     */
    public function validate(string $token, string $expectedType = 'access'): ?array
    {
        if (!$this->hasValidSecret()) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;

        $expected = $this->sign($encodedHeader . '.' . $encodedPayload);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = $this->base64UrlDecode($encodedPayload);
        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return null;
        }

        if (!isset($decoded['exp']) || time() > (int) $decoded['exp']) {
            return null;
        }

        if (($decoded['type'] ?? '') !== $expectedType) {
            return null;
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode((string) wp_json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body = $this->base64UrlEncode((string) wp_json_encode($payload));
        $signature = $this->sign($header . '.' . $body);

        return $header . '.' . $body . '.' . $signature;
    }

    private function sign(string $data): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    private function resolveSecret(): string
    {
        $constantSecret = defined('ICMS_JWT_SECRET') ? (string) constant('ICMS_JWT_SECRET') : '';

        if ($constantSecret !== '') {
            return $constantSecret;
        }

        return (string) get_option(self::OPTION_JWT_SECRET, '');
    }

    private function hasValidSecret(): bool
    {
        return strlen($this->secret) >= 32;
    }

    private function assertSecretIsConfigured(): void
    {
        if (!$this->hasValidSecret()) {
            throw new \RuntimeException(
                'JWT secret is not configured. Set ICMS_JWT_SECRET in wp-config.php or configure it in WP Admin > Settings > ICMS Security.'
            );
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
