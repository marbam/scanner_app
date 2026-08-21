<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateOwnerUser extends Command
{
    protected $signature = 'app:create-owner-user';

    protected $description = 'Create or update the single owner account from OWNER_NAME, OWNER_EMAIL, and OWNER_PASSWORD in .env';

    public function handle(): int
    {
        $data = [
            'name' => config('owner.name'),
            'email' => config('owner.email'),
            'password' => config('owner.password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', Password::default()],
        ], attributes: [
            'name' => 'OWNER_NAME',
            'email' => 'OWNER_EMAIL',
            'password' => 'OWNER_PASSWORD',
        ]);

        if ($validator->fails()) {
            $this->components->error('Cannot create owner user — check these .env values:');

            foreach ($validator->errors()->all() as $error) {
                $this->line("  - {$error}");
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => $data['password'],
            ]
        );

        // Not mass-assignable (see User::$fillable), so set it directly.
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->components->info("Owner user ready: {$user->email}");

        return self::SUCCESS;
    }
}
