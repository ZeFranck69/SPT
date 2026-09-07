<?php

declare(strict_types=1);

namespace SptVouchers\Security;

use RuntimeException;

defined('ABSPATH') || exit;

final class Cipher
{
    public function isConfigured(): bool
    {
        return defined('SPT_VOUCHERS_ENCRYPTION_KEY')
            && strlen((string) constant('SPT_VOUCHERS_ENCRYPTION_KEY')) >= 32;
    }

    public function encrypt(string $plainText): string
    {
        $this->assertConfigured();
        $key = $this->key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox($plainText, $nonce, $key);

            return 'sodium:' . base64_encode($nonce . $encrypted);
        }

        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $encrypted = openssl_encrypt($plainText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

            if ($encrypted === false) {
                throw new RuntimeException('Le chiffrement OpenSSL a echoue.');
            }

            return 'openssl:' . base64_encode($iv . $tag . $encrypted);
        }

        throw new RuntimeException('Aucun moteur de chiffrement compatible n est disponible.');
    }

    public function decrypt(string $payload): string
    {
        $this->assertConfigured();
        [$driver, $encoded] = array_pad(explode(':', $payload, 2), 2, '');
        $data = base64_decode($encoded, true);

        if ($data === false) {
            throw new RuntimeException('Le voucher chiffre est invalide.');
        }

        if ($driver === 'sodium' && function_exists('sodium_crypto_secretbox_open')) {
            $nonceLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $plainText = sodium_crypto_secretbox_open(
                substr($data, $nonceLength),
                substr($data, 0, $nonceLength),
                $this->key()
            );

            if ($plainText === false) {
                throw new RuntimeException('Le dechiffrement du voucher a echoue.');
            }

            return $plainText;
        }

        if ($driver === 'openssl' && function_exists('openssl_decrypt')) {
            $plainText = openssl_decrypt(
                substr($data, 28),
                'aes-256-gcm',
                $this->key(),
                OPENSSL_RAW_DATA,
                substr($data, 0, 12),
                substr($data, 12, 16)
            );

            if ($plainText === false) {
                throw new RuntimeException('Le dechiffrement du voucher a echoue.');
            }

            return $plainText;
        }

        throw new RuntimeException('Le moteur de chiffrement du voucher est indisponible.');
    }

    public function fingerprint(string $code): string
    {
        $this->assertConfigured();

        return hash_hmac('sha256', $this->normalize($code), $this->key());
    }

    public function lastFour(string $code): string
    {
        return substr($this->normalize($code), -4);
    }

    private function key(): string
    {
        return hash('sha256', (string) constant('SPT_VOUCHERS_ENCRYPTION_KEY'), true);
    }

    private function normalize(string $code): string
    {
        return trim($code);
    }

    private function assertConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException(
                'Definissez SPT_VOUCHERS_ENCRYPTION_KEY avec une valeur aleatoire d au moins 32 caracteres dans wp-config.php.'
            );
        }
    }
}
