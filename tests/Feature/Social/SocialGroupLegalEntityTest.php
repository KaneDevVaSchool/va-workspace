<?php

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Identity\App\Models\Company;
use Modules\Identity\App\Models\Role;
use Modules\Identity\Database\Seeders\RoleSeeder;
use Modules\Social\App\Models\SocialGroup;
use Tests\TestCase;

class SocialGroupLegalEntityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function makeUser(array $attributes = [], string $role = 'member'): User
    {
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $roleIds = Role::query()->where('code', $role)->pluck('id');
        if ($roleIds->isNotEmpty()) {
            $user->roles()->sync($roleIds);
            $user->unsetRelation('roles');
        }

        return $user;
    }

    private function company(string $code, string $name): Company
    {
        return Company::query()->create([
            'hrm_uuid' => (string) Str::uuid(),
            'code' => $code,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function test_nhan_su_cung_phap_nhan_thay_nhom_va_dang_bai_khong_can_xin_vao(): void
    {
        $vm = $this->company('VM', 'Trường Việt Mỹ');
        $hv = $this->company('HV', 'Hoàng Việt');
        $admin = $this->makeUser(['company_id' => $vm->id], 'super_admin');
        $colleague = $this->makeUser(['company_id' => $vm->id]);
        $outsider = $this->makeUser(['company_id' => $hv->id]);

        $this->actingAs($admin)
            ->postJson('/api/social/groups', [
                'name' => 'Pháp nhân Trường Việt Mỹ',
                'visibility' => 'public',
                'company_id' => $vm->id,
            ])
            ->assertCreated()
            ->assertJsonPath('group.company.code', 'VM');

        $group = SocialGroup::query()->where('company_id', $vm->id)->firstOrFail();

        $this->actingAs($colleague)
            ->getJson('/api/social/groups?tab=mine')
            ->assertOk()
            ->assertJsonPath('groups.0.id', $group->id)
            ->assertJsonPath('groups.0.is_member', true);

        $this->actingAs($colleague)
            ->postJson('/api/social/posts', [
                'content' => 'Tin nội bộ VM',
                'post_scope' => 'group',
                'group_id' => $group->id,
            ])
            ->assertCreated();

        $this->actingAs($outsider)
            ->getJson('/api/social/groups/'.$group->id)
            ->assertNotFound();

        $this->actingAs($outsider)
            ->getJson('/api/social/groups?tab=discover')
            ->assertOk()
            ->assertJsonPath('groups', []);

        $this->actingAs($outsider)
            ->postJson('/api/social/groups/'.$group->id.'/join')
            ->assertStatus(422);
    }

    public function test_nguoi_khong_phai_quan_tri_khong_gan_duoc_phap_nhan(): void
    {
        $vm = $this->company('VM', 'Trường Việt Mỹ');
        $member = $this->makeUser(['company_id' => $vm->id]);

        $this->actingAs($member)
            ->postJson('/api/social/groups', [
                'name' => 'Nhóm tự gắn',
                'visibility' => 'public',
                'company_id' => $vm->id,
            ])
            ->assertStatus(422);
    }

    public function test_khoi_tao_bon_nhom_phap_nhan_va_khong_tao_trung(): void
    {
        $this->company('VM', 'Trường Việt Mỹ');
        $this->company('VID', 'Viễn Đông');
        $this->company('HV', 'Hoàng Việt');
        $this->company('CIT', 'CIT');
        $this->company('OTHER', 'Đơn vị khác');
        $this->company('SAVITECH JSC', 'Công ty Cổ phần Công nghệ Sài Gòn Viễn Đông');
        $admin = $this->makeUser([], 'super_admin');

        $this->actingAs($admin)
            ->postJson('/api/social/groups/legal-entities')
            ->assertOk()
            ->assertJsonCount(4, 'created');

        $this->actingAs($admin)
            ->postJson('/api/social/groups/legal-entities')
            ->assertOk()
            ->assertJsonCount(0, 'created');

        $this->assertSame(4, SocialGroup::query()->whereNotNull('company_id')->count());
        $this->assertDatabaseMissing('social_groups', ['name' => 'Pháp nhân Đơn vị khác']);
        $this->assertDatabaseMissing('social_groups', [
            'name' => 'Công ty Cổ phần Công nghệ Sài Gòn Viễn Đông',
        ]);
    }

    public function test_mo_danh_sach_nhom_tu_tao_nhom_phap_nhan_cua_nguoi_xem(): void
    {
        $vm = $this->company('VM', 'Công ty Cổ phần Văn hóa Giáo dục Việt Mỹ');
        $this->company('VIDON CORP', 'Công ty Cổ phần Đầu tư Phát triển Thương mại Viễn Đông');
        $this->company('HVID EDU.CORP', 'Công ty Cổ phần Đầu tư Phát triển Giáo dục Hoàng Việt');
        $this->company('CIT', 'CIT');
        $member = $this->makeUser(['company_id' => $vm->id]);

        $this->actingAs($member)
            ->getJson('/api/social/groups?tab=mine')
            ->assertOk()
            ->assertJsonPath('groups.0.company.code', 'VM')
            ->assertJsonPath('groups.0.is_member', true);

        $this->assertSame(4, SocialGroup::query()->whereNotNull('company_id')->count());

        $this->actingAs($member)
            ->deleteJson('/api/social/groups/'.SocialGroup::query()->where('company_id', $vm->id)->value('id'))
            ->assertStatus(422);
    }
}
