<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPhoneRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_phone_user_does_not_require_otp_on_registration(): void
    {
        User::factory()->create([
            'name' => 'Jane Doe',
            'phone' => '+628123456789',
            'role' => 'siswa',
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'phone' => '+628123456789',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login berhasil.')
            ->assertJsonPath('requires_verification', false)
            ->assertJsonStructure(['token', 'user']);
    }
}
