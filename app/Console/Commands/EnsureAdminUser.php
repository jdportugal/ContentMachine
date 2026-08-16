<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates the single admin account if the install has none, so a fresh deploy is
 * never sitting there with an empty users table.
 *
 * This exists instead of a "create the first account" screen on purpose: such a
 * screen is open to whoever reaches it first, and this app is on a public URL.
 * Seeding at boot means there is no window to race.
 *
 * The generated password is written to a file on the storage volume — never to
 * stdout, because container logs get shipped and shared. Change it from Settings
 * once you are in.
 */
class EnsureAdminUser extends Command
{
    protected $signature = 'app:ensure-admin
                            {--email= : Address for the admin account (default: ADMIN_EMAIL or admin@brand-machine.local)}';

    protected $description = 'Create the admin account on a fresh install (no-op if any user exists)';

    /** Where the generated password is left for the operator to read once. */
    public const FICHEIRO_SENHA = 'app/admin_password';

    public function handle(): int
    {
        try {
            if (User::query()->exists()) {
                $this->info('A user already exists — nothing to do.');

                return self::SUCCESS;
            }
        } catch (Throwable $e) {
            // Migrations may not have run yet on a very first boot.
            $this->warn('Could not read the users table: '.$e->getMessage());

            return self::FAILURE;
        }

        $email = (string) ($this->option('email') ?: env('ADMIN_EMAIL', 'admin@brand-machine.local'));
        $senha = (string) (env('ADMIN_PASSWORD') ?: Str::password(20, symbols: false));

        User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make($senha),
        ]);

        // Only persist a password we generated: one supplied by the operator is
        // already theirs to know, and writing it down adds a copy for no reason.
        if (! env('ADMIN_PASSWORD')) {
            $ficheiro = storage_path(self::FICHEIRO_SENHA);
            @mkdir(dirname($ficheiro), 0775, true);
            file_put_contents($ficheiro, $senha);
            @chmod($ficheiro, 0600);
        }

        $this->info("Admin account created for {$email}.");

        return self::SUCCESS;
    }
}
