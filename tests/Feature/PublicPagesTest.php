<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['/', '/about', '/how-it-works', '/privacy', '/students', '/employers', '/contact'] as $u) {
            $this->get($u)->assertOk();
        }
    }

    public function test_contact_validates_and_persists(): void
    {
        $this->post('/contact', ['name' => ''])->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->post('/contact', ['name' => 'T', 'email' => 't@example.com', 'subject' => 'Hi', 'message' => 'Hello there, test.'])
            ->assertSessionHas('status');
        $this->assertTrue(ContactMessage::where('email', 't@example.com')->exists());
        ContactMessage::where('email', 't@example.com')->delete();
    }
}
