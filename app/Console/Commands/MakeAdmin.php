<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'academiq:make-admin {email} {name} {--password=}';
    protected $description = 'Provision the first KUET admin (privileged accounts cannot self-register).';

    public function handle(): int
    {
        $pw = $this->option('password') ?: $this->secret('Password (min 10 chars)');
        if (strlen((string) $pw) < 10) {
            $this->error('Password too short.');
            return 1;
        }
        if (User::where('email', $this->argument('email'))->exists()) {
            $this->error('Email already exists.');
            return 1;
        }

        $u = new User(['name' => $this->argument('name'), 'email' => strtolower($this->argument('email')), 'password' => $pw]);
        $u->role = 'admin';
        $u->is_active = true;
        $u->save();
        AuditLogger::log('admin.bootstrap_admin_created', 'user', (string) $u->getKey(), [], $u);
        $this->info('Admin created.');

        return 0;
    }
}
