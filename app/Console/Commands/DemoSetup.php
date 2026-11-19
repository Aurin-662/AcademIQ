<?php

namespace App\Console\Commands;

use App\Models\AuthorizedIssuer;
use App\Models\EmployerProfile;
use App\Models\StudentProfile;
use App\Models\StudentReference;
use App\Models\User;
use App\Services\Credentials\IssuerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Fictional demo accounts only. Refuses to run outside local/testing. */
class DemoSetup extends Command
{
    protected $signature = 'academiq:demo {--fresh : Wipe ALL application data (collections, issuer keys, reports) first} {--force : Skip the --fresh confirmation}';
    protected $description = 'Create fictional demo accounts for every role (repeatable).';

    public const PASSWORD = 'demo-password-123';

    public function handle(IssuerService $issuers): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Demo data is only allowed when APP_ENV is local or testing.');
            return 1;
        }
        if ($this->option('fresh')) {
            $db = DB::connection('mongodb')->getDatabase();
            if (! $this->option('force') && ! $this->confirm("Drop EVERY collection in database '{$db->getDatabaseName()}', issuer keys and stored reports?")) {
                return 1;
            }
            $db->drop();
            File::cleanDirectory(config('academiq.issuer_key_path')) ;
            File::cleanDirectory(storage_path('app/private/reports'));
            $this->warn('Database wiped.');
        }

        $people = [
            ['admin@demo.academiq.test', 'Demo Registrar (DEMO)', 'admin'],
            ['issuer@demo.academiq.test', 'Demo Issuer (DEMO)', 'issuer'],
            ['employer@demo.academiq.test', 'Demo Employer Ltd (DEMO)', 'employer'],
            ['police@demo.academiq.test', 'Demo Police Verifier (DEMO, not official)', 'police_verifier'],
            ['referee@demo.academiq.test', 'Demo Referee (DEMO)', 'referee'],
            ['previous@demo.academiq.test', 'Demo Previous Employer (DEMO)', 'previous_employer'],
            ['student1@demo.academiq.test', 'Demo Student One', 'student'],
            ['student2@demo.academiq.test', 'Demo Student Two', 'student'],
        ];
        $users = [];
        foreach ($people as [$email, $name, $role]) {
            $u = User::where('email', $email)->first() ?? tap(new User(['name' => $name, 'email' => $email, 'password' => self::PASSWORD]), function ($u) use ($role) {
                $u->role = $role;
                $u->is_active = true;
                $u->save();
            });
            $users[$email] = $u;
        }

        foreach (['admin@demo.academiq.test' => ['Demo Registrar (DEMO)', 'Registrar'], 'issuer@demo.academiq.test' => ['Demo Issuer (DEMO)', 'Controller of Examinations']] as $email => [$name, $title]) {
            if (! AuthorizedIssuer::where('user_id', (string) $users[$email]->getKey())->exists()) {
                $issuers->authorize($users[$email], $name, $title);
            }
        }
        $emp = $users['employer@demo.academiq.test'];
        EmployerProfile::firstOrCreate(['user_id' => (string) $emp->getKey()], ['company_name' => 'Demo Employer Ltd (DEMO)']);
        foreach (['student1@demo.academiq.test' => 'KUET-DEMO-001', 'student2@demo.academiq.test' => 'KUET-DEMO-002'] as $email => $ref) {
            $id = (string) $users[$email]->getKey();
            StudentProfile::firstOrCreate(['user_id' => $id], ['student_reference' => $ref, 'department' => 'Demo Department']);
            StudentReference::firstOrCreate(['student_reference' => $ref], ['user_id' => $id, 'account_status' => 'active', 'wallet_public_key' => null]);
        }
        $this->callSilent('zkp:register', ['circuit' => 'credential', 'version' => 1]);

        $this->table(['Role', 'Email', 'Reference'], collect($people)->map(fn ($p) => [$p[2], $p[0], $p[2] === 'student' ? ($p[0] === 'student1@demo.academiq.test' ? 'KUET-DEMO-001' : 'KUET-DEMO-002') : ''])->all());
        $this->info('Password for every demo account: '.self::PASSWORD.'  (fictional demo data only)');
        $this->line('Next: follow docs/DEMO.md. Students must create their wallet in the browser before a credential can be issued.');

        return 0;
    }
}
