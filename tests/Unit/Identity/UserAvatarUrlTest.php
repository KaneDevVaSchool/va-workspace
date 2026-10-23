<?php

namespace Tests\Unit\Identity;

use Modules\Identity\App\Support\UserAvatarUrl;
use PHPUnit\Framework\TestCase;

class UserAvatarUrlTest extends TestCase
{
    public function test_detects_user_uploaded_storage_path(): void
    {
        $this->assertTrue(UserAvatarUrl::isUserUploaded('https://workspace.test/storage/users/1/avatar/foo.jpg'));
    }

    public function test_google_not_counted_as_user_upload(): void
    {
        $this->assertFalse(UserAvatarUrl::isUserUploaded('https://lh3.googleusercontent.com/a/abc=s256-c'));
    }

    public function test_apply_google_when_avatar_empty(): void
    {
        $google = 'https://lh3.googleusercontent.com/a/x=s256-c';
        $this->assertTrue(UserAvatarUrl::shouldApplyGoogleOnLogin(null, $google));
    }

    public function test_skip_google_when_user_uploaded(): void
    {
        $google = 'https://lh3.googleusercontent.com/a/x=s256-c';
        $custom = 'https://workspace.test/storage/users/2/avatar/a.png';
        $this->assertFalse(UserAvatarUrl::shouldApplyGoogleOnLogin($custom, $google));
    }

    public function test_skip_google_when_avatar_already_set_even_if_google_hosted(): void
    {
        $google = 'https://lh3.googleusercontent.com/a/new=s256-c';
        $old = 'https://lh3.googleusercontent.com/a/old=s96-c';
        $this->assertFalse(UserAvatarUrl::shouldApplyGoogleOnLogin($old, $google));
    }
}
