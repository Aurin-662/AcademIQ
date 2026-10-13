<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        $this->command->info('=== Seeding Institutions (MongoDB) ===');
        $institutions = [
            ['code' => 'KUET', 'name' => 'Khulna University of Engineering & Technology', 'verified' => true],
            ['code' => 'BUET', 'name' => 'Bangladesh University of Engineering and Technology', 'verified' => true],
        ];

        foreach ($institutions as $inst) {
            Institution::updateOrCreate(
                ['code' => $inst['code']],
                ['name' => $inst['name'], 'verified' => $inst['verified'], 'created_at' => now()]
            );
        }

        $this->command->info('=== Seeding Users (SQLite) ===');
        // 1. System Admin
        $sysAdmin = User::updateOrCreate(
            ['email' => 'admin@academiq.edu'],
            [
                'name'             => 'System Administrator',
                'password'         => $password,
                'role'             => 'system_admin',
                'institution_code' => null,
                'organization'     => null,
                'approved'         => true,
            ]
        );

        // 2. Institution Admins
        $kuetAdmin = User::updateOrCreate(
            ['email' => 'admin@kuet.ac.bd'],
            [
                'name'             => 'KUET Registrar Office',
                'password'         => $password,
                'role'             => 'institution_admin',
                'institution_code' => 'KUET',
                'organization'     => null,
                'approved'         => true,
            ]
        );

        $buetAdmin = User::updateOrCreate(
            ['email' => 'admin@buet.ac.bd'],
            [
                'name'             => 'BUET Examination Controller',
                'password'         => $password,
                'role'             => 'institution_admin',
                'institution_code' => 'BUET',
                'organization'     => null,
                'approved'         => true,
            ]
        );

        // 3. Teachers
        $teachersData = [
            [
                'email'            => 'tariqul@cse.kuet.ac.bd',
                'name'             => 'Dr. Kazi Md. Tariqul Islam',
                'institution_code' => 'KUET',
                'department'       => 'Computer Science and Engineering',
                'designation'      => 'Professor',
                'teacher_id'       => 'TCHR-KUET-00001',
                'verified'         => true,
            ],
            [
                'email'            => 'rafiq@ece.kuet.ac.bd',
                'name'             => 'Dr. Rafiqul Hasan',
                'institution_code' => 'KUET',
                'department'       => 'Electronics and Communication Engineering',
                'designation'      => 'Assistant Professor',
                'teacher_id'       => 'TCHR-KUET-00002',
                'verified'         => false,
            ],
            [
                'email'            => 'saidur@cse.buet.ac.bd',
                'name'             => 'Dr. Md. Saidur Rahman',
                'institution_code' => 'BUET',
                'department'       => 'Computer Science and Engineering',
                'designation'      => 'Professor',
                'teacher_id'       => 'TCHR-BUET-00003',
                'verified'         => true,
            ],
        ];

        foreach ($teachersData as $tData) {
            $u = User::updateOrCreate(
                ['email' => $tData['email']],
                [
                    'name'             => $tData['name'],
                    'password'         => $password,
                    'role'             => 'teacher',
                    'institution_code' => $tData['institution_code'],
                    'organization'     => null,
                    'approved'         => true,
                ]
            );

            Teacher::updateOrCreate(
                ['teacher_id' => $tData['teacher_id']],
                [
                    'user_id'          => $u->id,
                    'name'             => $tData['name'],
                    'designation'      => $tData['designation'],
                    'department'       => $tData['department'],
                    'institution_code' => $tData['institution_code'],
                    'email'            => $tData['email'],
                    'verified'         => $tData['verified'],
                ]
            );
        }

        // 4. Employers
        $employers = [
            [
                'email'        => 'hr@brainstation23.com',
                'name'         => 'Mahmudul Hasan',
                'organization' => 'Brain Station 23 PLC',
            ],
            [
                'email'        => 'talent@optimizely.com',
                'name'         => 'Sadia Afrin',
                'organization' => 'Optimizely Dhaka',
            ],
        ];

        foreach ($employers as $emp) {
            User::updateOrCreate(
                ['email' => $emp['email']],
                [
                    'name'             => $emp['name'],
                    'password'         => $password,
                    'role'             => 'employer',
                    'institution_code' => null,
                    'organization'     => $emp['organization'],
                    'approved'         => true,
                ]
            );
        }

        $this->command->newLine();
        $this->command->info('======================================================================');
        $this->command->info('                   ACADEMIQ DEMO ACCOUNTS CREATED                     ');
        $this->command->info('======================================================================');
        $this->command->table(
            ['Role', 'Email', 'Password', 'Details / Note'],
            [
                ['System Admin', 'admin@academiq.edu', 'password123', 'Full access, approvals, analytics'],
                ['Institution Admin', 'admin@kuet.ac.bd', 'password123', 'KUET Admin (Can issue certificates)'],
                ['Institution Admin', 'admin@buet.ac.bd', 'password123', 'BUET Admin (Can issue certificates)'],
                ['Teacher (Verified)', 'tariqul@cse.kuet.ac.bd', 'password123', 'Prof. at KUET CSE (Verified faculty)'],
                ['Teacher (Unverified)', 'rafiq@ece.kuet.ac.bd', 'password123', 'Asst. Prof at KUET ECE (Unverified)'],
                ['Teacher (Verified)', 'saidur@cse.buet.ac.bd', 'password123', 'Prof. at BUET CSE (Verified faculty)'],
                ['Employer', 'hr@brainstation23.com', 'password123', 'Brain Station 23 (Bulk verify)'],
                ['Employer', 'talent@optimizely.com', 'password123', 'Optimizely (Bulk verify)'],
            ]
        );
        $this->command->info('======================================================================');
    }
}
