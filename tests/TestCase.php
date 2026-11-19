<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection('mongodb')->getDatabase();
        // Safety: only ever wipe a *_test database.
        if (! str_ends_with($db->getDatabaseName(), '_test')) {
            $this->fail('Refusing to wipe non-test database '.$db->getDatabaseName());
        }
        $db->drop();
    }

    protected function makeUser(string $role, array $attrs = []): User
    {
        $u = new User(array_diff_key($attrs, ['is_active' => 1]) + ['name' => ucfirst($role), 'email' => $role.'@example.test', 'password' => 'correct-horse-battery']);
        $u->role = $role;
        $u->is_active = $attrs['is_active'] ?? true;
        $u->save();

        return $u;
    }
}
