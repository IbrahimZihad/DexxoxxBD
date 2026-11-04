<?php

declare(strict_types=1);

use App\Helpers\Seed;
use App\Models\User;
use Phinx\Seed\AbstractSeed;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The first staff account, taken from the ADMIN_* keys in .env.
 *
 * A password configured in .env is never printed. When ADMIN_PASSWORD is empty,
 * one is generated for this run, printed once, and the account is flagged so the
 * panel demands a change on first login.
 */
final class AdminUserSeeder extends AbstractSeed
{
    public function run(): void
    {
        Seed::configure();
        Seed::ensure($this, RoleSeeder::class);
        Seed::ensure($this, PermissionSeeder::class);

        $email = trim((string) env('ADMIN_EMAIL', ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->notify(
                'Skipped: put a valid ADMIN_EMAIL in .env to create the first admin account '
                . '(optionally with ADMIN_PASSWORD).'
            );

            return;
        }

        $configured = trim((string) env('ADMIN_PASSWORD', ''));
        $generated = $configured === '';
        $password = $generated ? $this->randomPassword() : $configured;
        $username = trim((string) env('ADMIN_USERNAME', 'admin')) ?: 'admin';

        $userId = Seed::upsert($this, 'users', ['email' => $email], [
            'name' => trim((string) env('ADMIN_NAME', 'Site Administrator')) ?: 'Site Administrator',
            'username' => Seed::uniqueUsername($this, $username, $email),
            'password' => Seed::hashPassword($password),
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'locale' => (string) env('DEFAULT_LOCALE', 'en'),
            'must_change_password' => $generated,
        ]);

        $roleId = Seed::id($this, 'roles', ['slug' => 'super-admin']);
        Seed::link($this, 'user_roles', ['user_id' => $userId, 'role_id' => $roleId]);

        $this->notify('Super admin ' . $email . ' is ready.');
        if ($generated) {
            $this->notify('Generated password (shown once - change it on first login): ' . $password);
        }
    }

    /**
     * One character from every class the password policy asks for, then filled
     * and shuffled with the CSPRNG so the result cannot be guessed from the
     * character mix.
     */
    private function randomPassword(int $length = 20): string
    {
        $classes = [
            'abcdefghijkmnopqrstuvwxyz',
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            '23456789',
            '!@#$%^&*-_=+',
        ];

        $characters = implode('', $classes);
        $password = '';
        foreach ($classes as $class) {
            $password .= $class[random_int(0, strlen($class) - 1)];
        }

        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $characters = str_split($password);
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            $swap = $characters[$i];
            $characters[$i] = $characters[$j];
            $characters[$j] = $swap;
        }

        return implode('', $characters);
    }

    private function notify(string $message): void
    {
        $this->getOutput()->writeln('<comment>' . $message . '</comment>', OutputInterface::VERBOSITY_NORMAL);
    }
}
