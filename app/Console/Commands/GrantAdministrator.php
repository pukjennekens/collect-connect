<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantAdministrator extends Command
{
    protected $signature = 'app:grant-administrator {email : Existing user email} {--revoke : Remove administrator access}';

    protected $description = 'Grant or revoke administrator access for an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No existing user has this email address.');

            return self::FAILURE;
        }
        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info('Administrator access updated.');

        return self::SUCCESS;
    }
}
