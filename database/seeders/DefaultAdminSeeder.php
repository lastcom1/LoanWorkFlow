<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        $hq = Branch::query()->where('code', 'HQ-ABJ')->first();

        $demos = [
            [
                'email' => 'admin@nnmfb.demo',
                'name' => 'DEMO System Admin',
                'staff_id' => 'DEMO-ADM-001',
                'role' => 'admin',
                'password' => 'DemoAdmin@123',
            ],
            [
                'email' => 'mdceo@nnmfb.demo',
                'name' => 'DEMO MD / CEO',
                'staff_id' => 'DEMO-MD-001',
                'role' => 'md_ceo',
                'password' => 'DemoMdCeo@123',
            ],
            [
                'email' => 'supervisor@nnmfb.demo',
                'name' => 'DEMO Operations Supervisor',
                'staff_id' => 'DEMO-SUP-001',
                'role' => 'supervisor',
                'password' => 'DemoSupervisor@123',
            ],
            [
                'email' => 'ft1@nnmfb.demo',
                'name' => 'DEMO FT Officer 1',
                'staff_id' => 'DEMO-FT-001',
                'role' => 'ft_officer',
                'password' => 'DemoFtOfficer1@123',
            ],
            [
                'email' => 'ft2@nnmfb.demo',
                'name' => 'DEMO FT Officer 2',
                'staff_id' => 'DEMO-FT-002',
                'role' => 'ft_officer',
                'password' => 'DemoFtOfficer2@123',
            ],
            [
                'email' => 'ft3@nnmfb.demo',
                'name' => 'DEMO FT Officer 3',
                'staff_id' => 'DEMO-FT-003',
                'role' => 'ft_officer',
                'password' => 'DemoFtOfficer3@123',
            ],
            [
                'email' => 'ft4@nnmfb.demo',
                'name' => 'DEMO FT Officer 4',
                'staff_id' => 'DEMO-FT-004',
                'role' => 'ft_officer',
                'password' => 'DemoFtOfficer4@123',
            ],
            [
                'email' => 'submitter@nnmfb.demo',
                'name' => 'DEMO Branch Submitter',
                'staff_id' => 'DEMO-SUB-001',
                'role' => 'submitter',
                'password' => 'DemoSubmitter@123',
                'branch' => 'BR-LAG',
            ],
            [
                'email' => 'auditor@nnmfb.demo',
                'name' => 'DEMO Auditor',
                'staff_id' => 'DEMO-AUD-001',
                'role' => 'auditor',
                'password' => 'DemoAuditor@123',
            ],
        ];

        foreach ($demos as $demo) {
            $branchId = isset($demo['branch'])
                ? Branch::query()->where('code', $demo['branch'])->value('id')
                : $hq?->id;

            $user = User::query()->updateOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'staff_id' => $demo['staff_id'],
                    'branch_id' => $branchId,
                    'password' => Hash::make($demo['password']),
                    'is_active' => true,
                    'is_demo' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$demo['role']]);
        }
    }
}
