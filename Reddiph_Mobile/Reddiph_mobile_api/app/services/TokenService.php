<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

/**
 * Signed, expiring session tokens ("<user_id>.<expires_at>.<signature>").
 * The signature uses a server-only key, so a client cannot forge a token for another user.
 */
final class TokenService
{
    private const LIFETIME_SECONDS = 604800; // 7 days
    private const ALGORITHM = 'sha256';

    public static function issue(int $userId): string
    {
        $payload = $userId . '.' . (time() + self::LIFETIME_SECONDS);

        return $payload . '.' . self::sign($payload);
    }

    /** Returns the signed user id before checking its current account status. */
    public static function verifySignature(mixed $token): ?int
    {
        $parts = is_string($token) ? explode('.', $token) : [];
        if (count($parts) !== 3) {
            return null;
        }

        [$userId, $expiresAt, $signature] = $parts;
        if (!ctype_digit($userId) || !ctype_digit($expiresAt)) {
            return null;
        }
        if (!hash_equals(self::sign($userId . '.' . $expiresAt), $signature)) {
            return null;
        }

        return (int) $expiresAt >= time() ? (int) $userId : null;
    }

    /** Returns the user id only while the signed-in account is not banned. */
    public static function verify(mixed $token): ?int
    {
        $userId = self::verifySignature($token);
        if ($userId === null) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT banned_until FROM user_type WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $bannedUntil = $stmt->fetchColumn();
        if ($bannedUntil === false) {
            return null;
        }
        if (is_string($bannedUntil) && strtotime($bannedUntil) > time()) {
            return null;
        }

        return $userId;
    }

    /** Returns a suspension notice only for a valid, unexpired signed-in token. */
    public static function disabledMessage(mixed $token): ?string
    {
        $userId = self::verifySignature($token);
        if ($userId === null) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT banned_until, login_disabled_reason FROM user_type WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (
            !$account ||
            !is_string($account['banned_until']) ||
            strtotime($account['banned_until']) <= time()
        ) {
            return null;
        }

        $reason = trim((string) ($account['login_disabled_reason'] ?? ''));
        return $reason !== ''
            ? 'Your login has been disabled. Reason: ' . $reason
            : 'Your session is no longer active. Please sign in again.';
    }

    private static function sign(string $payload): string
    {
        return hash_hmac(self::ALGORITHM, $payload, Database::tokenSecret());
    }
}
