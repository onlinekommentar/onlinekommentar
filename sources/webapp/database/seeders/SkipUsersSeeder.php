<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Statamic\Facades\User;

class SkipUsersSeeder extends Seeder
{
    public function run()
    {
        if (! app()->environment('local')) {
            echo "Refusing to run: not a local environment.\n";

            exit(1);
        }

        $users = [
            ['handle' => 'admin', 'name' => 'Skip Admin', 'roles' => ['admin']],
            ['handle' => 'contributor', 'name' => 'Skip Contributor', 'roles' => ['contributor']],
        ];

        foreach ($users as $data) {
            $email = "{$data['handle']}@example.test";

            $user = User::findByEmail($email) ?? User::make()->email($email);

            $user->set('name', $data['name']);
            $user->password('password');
            $user->setPreference('locale', 'en');
            $user->save();

            collect($user->roles())->each(fn ($role) => $user->removeRole($role));
            collect($data['roles'])->each(fn ($role) => $user->assignRole($role));
            $user->save();

            echo "Seeded skip user {$email} ({$data['roles'][0]}).\n";
        }
    }
}
