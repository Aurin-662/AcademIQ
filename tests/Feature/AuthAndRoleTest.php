<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_protected_create_document_route(): void
    {
        $response = $this->get('/documents/create');
        $response->assertRedirect('/login');
    }

    public function test_unapproved_institution_admin_cannot_access_issue_route(): void
    {
        $unapproved = User::create([
            'name'             => 'Unapproved Admin',
            'email'            => 'unapproved@test.com',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => false,
        ]);

        $response = $this->actingAs($unapproved)->get('/documents/create');
        $response->assertRedirect('/');
        $response->assertSessionHas('warning');
    }

    public function test_approved_institution_admin_can_access_issue_route(): void
    {
        $approved = User::create([
            'name'             => 'Approved Admin',
            'email'            => 'approved@test.com',
            'password'         => bcrypt('password123'),
            'role'             => 'institution_admin',
            'institution_code' => 'KUET',
            'approved'         => true,
        ]);

        $response = $this->actingAs($approved)->get('/documents/create');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::create([
            'name'     => 'Demo User',
            'email'    => 'demo@example.com',
            'password' => bcrypt('secret123'),
            'role'     => 'employer',
            'approved' => true,
        ]);

        $response = $this->post('/login', [
            'email'    => 'demo@example.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }
}
