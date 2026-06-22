<?php
declare(strict_types=1);

namespace AutoBusiness\Security;

use AutoBusiness\Core\Database;
use AutoBusiness\Core\Env;

/**
 * CredentialVault — authenticated encryption for stored secrets.
 *
 * Global Security Rule (non-negotiable): AES-256-GCM (authenticated), a random
 * IV per record, and the master key from an environment variable stored OUTSIDE
 * public_html. NOT AES-256-CBC.
 *
 * Storage layout (maps to the `credentials` table from Module 1):
 *   - iv             : the 12-byte random GCM nonce (raw bytes, VARBINARY)
 *   - encrypted_data : 16-byte GCM auth tag  ||  ciphertext   (raw bytes, BLOB)
 *
 * The auth tag is verified on every decrypt, so any tampering with the stored
 * ciphertext is detected and rejected (openssl_decrypt returns false).
 */
final class CredentialVault
{
    private const CIPHER  = 'aes-256-gcm';
    private const IV_LEN  = 12; // 96-bit nonce — the recommended size for GCM
    private const TAG_LEN = 16; // 128-bit authentication tag

    /** 32 raw bytes derived from CREDENTIAL_MASTER_KEY (base64 in .env). */
    private string $key;

    public function __construct(?string $base64Key = null)
    {
        $raw = $base64Key ?? Env::require('CREDENTIAL_MASTER_KEY');
        $decoded = base64_decode($raw, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new \RuntimeException(
                'CREDENTIAL_MASTER_KEY must be 32 bytes, base64-encoded.'
            );
        }
        $this->key = $decoded;
    }

    /**
     * Encrypt a plaintext string.
     *
     * @return array{iv: string, data: string} raw binary IV and (tag || ciphertext)
     */
    public function encrypt(string $plaintext): array
    {
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return ['iv' => $iv, 'data' => $tag . $ciphertext];
    }

    /**
     * Decrypt a record produced by encrypt(). Returns the plaintext or throws
     * if authentication fails (tampering / wrong key).
     */
    public function decrypt(string $iv, string $data): string
    {
        if (strlen($data) < self::TAG_LEN) {
            throw new \RuntimeException('Corrupt ciphertext: too short for auth tag.');
        }
        $tag        = substr($data, 0, self::TAG_LEN);
        $ciphertext = substr($data, self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: authentication mismatch.');
        }
        return $plaintext;
    }

    // --- Convenience persistence helpers (credentials table) ----------------

    /**
     * Encrypt and store a credential's secret payload, returning the new id.
     *
     * @param array<string,mixed> $secret arbitrary structured secret (JSON-encoded)
     */
    public function store(int $userId, string $name, string $type, array $secret): int
    {
        $enc = $this->encrypt((string) json_encode($secret, JSON_THROW_ON_ERROR));

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO credentials (user_id, name, type, iv, encrypted_data)
             VALUES (:user_id, :name, :type, :iv, :data)'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':iv', $enc['iv'], \PDO::PARAM_LOB);
        $stmt->bindValue(':data', $enc['data'], \PDO::PARAM_LOB);
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    /**
     * Load and decrypt a credential's secret payload by id.
     *
     * @return array<string,mixed>|null
     */
    public function reveal(int $credentialId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT iv, encrypted_data FROM credentials WHERE id = :id'
        );
        $stmt->bindValue(':id', $credentialId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $json = $this->decrypt((string) $row['iv'], (string) $row['encrypted_data']);
        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }
}
