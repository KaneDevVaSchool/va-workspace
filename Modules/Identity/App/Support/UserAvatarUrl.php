<?php

namespace Modules\Identity\App\Support;

/**
 * Phân loại avatar_url trên users — Google chỉ seed lần đầu; ảnh tải lên
 * /storage/users/... không bị ghi đè bởi OAuth hay đồng bộ HRM.
 */
final class UserAvatarUrl
{
    public static function isUserUploaded(?string $avatarUrl): bool
    {
        if (! filled($avatarUrl)) {
            return false;
        }

        $path = parse_url((string) $avatarUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        return str_contains($path, '/storage/users/')
            || str_starts_with($path, '/storage/users/');
    }

    public static function isGoogleHosted(?string $avatarUrl): bool
    {
        if (! filled($avatarUrl)) {
            return false;
        }

        return str_contains(strtolower((string) $avatarUrl), 'googleusercontent.com');
    }

    /** Chỉ seed ảnh Google lần đầu (users.avatar_url còn trống). */
    public static function shouldApplyGoogleOnLogin(?string $currentAvatar, ?string $googleAvatar): bool
    {
        if ($googleAvatar === null || trim($googleAvatar) === '') {
            return false;
        }

        return ! filled($currentAvatar);
    }
}
