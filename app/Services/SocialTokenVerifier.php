<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Verifies ID tokens produced by the Google Sign-In and Sign in with Apple SDKs on the device.
 */
class SocialTokenVerifier
{
    /**
     * @return array{id: string, email: ?string, email_verified: bool, name: ?string}
     *
     * @throws ValidationException when the token is invalid or issued for another app.
     */
    public function verify(string $provider, string $idToken): array
    {
        try {
            $profile = match ($provider) {
                'google' => $this->verifyGoogle($idToken),
                'apple' => $this->verifyApple($idToken),
            };
        } catch (Throwable $exception) {
            report($exception);
            $profile = null;
        }

        if ($profile === null) {
            throw ValidationException::withMessages(['id_token' => __('messages.social_token_invalid')]);
        }

        return $profile;
    }

    /**
     * @return array{id: string, email: ?string, email_verified: bool, name: ?string}|null
     */
    private function verifyGoogle(string $idToken): ?array
    {
        $response = Http::connectTimeout(5)
            ->timeout(10)
            ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        $claims = $response->json();

        if (! $response->successful()
            || ! in_array($claims['aud'] ?? null, config('services.google.client_ids'), true)
            || ! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            Log::warning('Google ID token rejected', [
                'status' => $response->status(),
                'error' => $claims['error_description'] ?? $claims['error'] ?? null,
                'aud' => $claims['aud'] ?? null,
                'allowed_aud' => config('services.google.client_ids'),
            ]);

            return null;
        }

        return [
            'id' => (string) $claims['sub'],
            'email' => $claims['email'] ?? null,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => $claims['name'] ?? null,
        ];
    }

    /**
     * @return array{id: string, email: ?string, email_verified: bool, name: ?string}|null
     */
    private function verifyApple(string $idToken): ?array
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;
        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);

        if (($header['alg'] ?? null) !== 'RS256' || ! is_array($claims)) {
            return null;
        }

        $publicKey = $this->applePublicKey($header['kid'] ?? '');

        if ($publicKey === null || openssl_verify(
            $encodedHeader.'.'.$encodedPayload,
            $this->base64UrlDecode($encodedSignature),
            $publicKey,
            OPENSSL_ALGO_SHA256,
        ) !== 1) {
            return null;
        }

        if (($claims['iss'] ?? null) !== 'https://appleid.apple.com'
            || ! in_array($claims['aud'] ?? null, config('services.apple.client_ids'), true)
            || ($claims['exp'] ?? 0) < time()) {
            return null;
        }

        return [
            'id' => (string) $claims['sub'],
            'email' => $claims['email'] ?? null,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => null, // Apple only sends the name to the app, on the first sign in.
        ];
    }

    private function applePublicKey(string $keyId): ?string
    {
        $keys = Cache::remember('apple_sign_in_keys', now()->addHours(6), fn () => Http::connectTimeout(5)
            ->timeout(10)
            ->get('https://appleid.apple.com/auth/keys')
            ->throw()
            ->json('keys'));

        $jwk = collect($keys)->firstWhere('kid', $keyId);

        return $jwk ? $this->rsaJwkToPem($jwk['n'], $jwk['e']) : null;
    }

    /**
     * Build a PEM encoded RSA public key (SubjectPublicKeyInfo) from JWK modulus and exponent.
     */
    private function rsaJwkToPem(string $modulus, string $exponent): string
    {
        $rsaPublicKey = $this->derSequence(
            $this->derInteger($this->base64UrlDecode($modulus)).$this->derInteger($this->base64UrlDecode($exponent))
        );

        $algorithm = $this->derSequence("\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00"); // rsaEncryption OID + NULL
        $subjectPublicKeyInfo = $this->derSequence($algorithm."\x03".$this->derLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function derInteger(string $bytes): string
    {
        if (ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLength(strlen($bytes)).$bytes;
    }

    private function derSequence(string $contents): string
    {
        return "\x30".$this->derLength(strlen($contents)).$contents;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
