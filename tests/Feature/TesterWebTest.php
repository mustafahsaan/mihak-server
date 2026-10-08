<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** تسجيل المختبِر ولوحته في الموقع، وربط الجهاز عند أول دخول من التطبيق */
class TesterWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_tester_registers_on_web_then_links_phone_on_first_app_login(): void
    {
        $this->post('/join', [
            'name' => 'مريم', 'phone' => '07844444444', 'email' => 'Maryam@Gmail.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('tester.dashboard'));

        $user = User::query()->where('phone', '07844444444')->firstOrFail();
        $this->assertSame(User::TESTER, $user->role);
        $this->assertSame('maryam@gmail.com', $user->email);
        $this->assertNull($user->device_id);

        $this->get('/me')->assertOk()->assertSee('مريم')->assertSee('حمّل تطبيق');

        // السحب قبل الحد الأدنى مرفوض
        $this->post('/me/withdraw', ['amount' => 500, 'method' => 'zaincash', 'account' => '07844444444'])
            ->assertSessionHas('error');

        // أول دخول من التطبيق يربط الحساب بهذا الهاتف
        $this->postJson('/api/auth/login', ['phone' => '07844444444', 'password' => 'secret123', 'device_id' => 'phone-maryam'])
            ->assertOk()->assertJsonStructure(['token']);
        $this->assertSame('phone-maryam', $user->fresh()->device_id);
    }

    public function test_tester_web_registration_requires_gmail(): void
    {
        $this->post('/join', [
            'name' => 'علي', 'phone' => '07855555555', 'email' => 'ali@yahoo.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_can_change_site_font(): void
    {
        $admin = User::query()->create(['name' => 'admin', 'phone' => '07700000009', 'password' => 'x', 'role' => User::ADMIN, 'phone_verified_at' => now()]);

        $this->actingAs($admin)->put('/admin/settings/font', ['site_font' => 'Tajawal'])->assertSessionHas('success');
        $this->get('/')->assertSee('family=Tajawal', false);

        $this->put('/admin/settings/font', ['site_font' => 'Comic Sans'])->assertSessionHasErrors('site_font');
    }
}
