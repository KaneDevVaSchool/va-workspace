<?php

namespace Modules\Identity\App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Modules\Identity\App\Exceptions\AccountNotUsable;
use Modules\Identity\App\Exceptions\EmailDomainNotAllowed;
use Modules\Identity\App\Repositories\Contracts\UserRepositoryInterface;
use Modules\Identity\App\Services\SuperAdminBootstrap;

/**
 * Xử lý callback Google OAuth: kiểm domain → tìm/tạo user → lưu.
 *
 * Rút gọn từ va-hrm (GoogleAuthenticator) — KHÔNG có employee linking,
 * superadmin bootstrap, lockout, login activity log (chưa cần ở giai đoạn
 * này). department_id KHÔNG được gán tự động khi login vì chưa có nguồn
 * ánh xạ thật (API HRM) — giữ null, gán tay qua seeder/DB.
 *
 * TẠM THỜI: user giả lập qua UserRepositoryInterface (Eloquent). Sẽ thay
 * bằng client gọi API HRM khi HRM cung cấp — Service này không cần sửa.
 */
class GoogleAuthenticator
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SuperAdminBootstrap $superAdminBootstrap,
    ) {}

    /**
     * @throws EmailDomainNotAllowed
     * @throws AccountNotUsable
     */
    public function authenticate(SocialiteUser $googleUser): User
    {
        $email = strtolower((string) $googleUser->getEmail());

        if (! $this->domainAllowed($email)) {
            throw new EmailDomainNotAllowed($email);
        }

        $user = DB::transaction(function () use ($googleUser, $email): User {
            $user = $this->findOrCreateUser($googleUser, $email);

            $this->assertUsable($user, $email);
            $this->superAdminBootstrap->ensureRolesForUser($user);

            return $user->fresh(['roles']);
        });

        return $user;
    }

    private function domainAllowed(string $email): bool
    {
        $domains = array_map('strtolower', (array) config('services.google.allowed_domains', []));

        if ($domains === []) {
            // Chưa cấu hình domain nào → chặn tất cả (an toàn theo mặc định).
            return false;
        }

        $emailDomain = strtolower((string) substr($email, strrpos($email, '@') + 1));

        return in_array($emailDomain, $domains, true);
    }

    private function findOrCreateUser(SocialiteUser $googleUser, string $email): User
    {
        // Ưu tiên tra theo google_id (bất biến kể cả khi đổi email), sau đó
        // fallback theo email (user có thể được tạo sẵn qua seeder/DB).
        $user = $this->users->findByGoogleId((string) $googleUser->getId())
            ?? $this->users->findByEmail($email);

        $avatarUrl = $this->resolveAvatarUrl($googleUser);

        if ($user !== null) {
            return $this->users->update($user, array_filter([
                'email' => $email,
                'google_id' => (string) $googleUser->getId(),
                'name' => $googleUser->getName() ?: $user->name,
                'avatar_url' => $avatarUrl ?? $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ], fn ($value) => $value !== null));
        }

        return $this->users->create([
            'name' => $googleUser->getName() ?: $email,
            'email' => $email,
            'google_id' => (string) $googleUser->getId(),
            'avatar_url' => $avatarUrl,
            'email_verified_at' => now(),
            // Chưa có nguồn ánh xạ phòng ban thật (API HRM) → để trống,
            // gán tay qua DB cho tới khi tích hợp HRM.
            'department_id' => null,
            'status' => 'active',
        ]);
    }

    private function resolveAvatarUrl(SocialiteUser $googleUser): ?string
    {
        $raw = method_exists($googleUser, 'getRaw') ? (array) $googleUser->getRaw() : [];

        $candidates = [
            $googleUser->getAvatar(),
            $raw['picture'] ?? null,
        ];

        $avatar = null;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $avatar = trim($candidate);
                break;
            }
        }

        if ($avatar === null) {
            return null;
        }

        // Google trả =s96-c — tăng độ phân giải cho avatar UI.
        if (str_contains($avatar, 'googleusercontent.com')) {
            $upgraded = preg_replace('/=s\d+-c(\?|$)/', '=s256-c$1', $avatar);
            if (is_string($upgraded) && $upgraded !== '') {
                $avatar = $upgraded;
            }
        }

        return mb_substr($avatar, 0, 2048);
    }

    /** @throws AccountNotUsable */
    private function assertUsable(User $user, string $email): void
    {
        if ($user->status !== 'active') {
            throw new AccountNotUsable($email, $user->status);
        }
    }
}
