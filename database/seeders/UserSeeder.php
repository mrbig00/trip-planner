<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    protected static array $users = [
        [
            'first_name' => 'Zoltan',
            'last_name' => 'Szanto',
            'email' => 'mrbig00@gmail.com',
        ], [
            'first_name' => 'Dali',
            'last_name' => 'Anna Bernadett',
            'email' => 'dali-anna-bernadett@gmail.com',
        ],
        [
            'first_name' => 'Gaal',
            'last_name' => 'Csaba',
            'email' => 'gaal-csaba@gmail.com',
        ],
        [
            'first_name' => 'Giovanni',
            'last_name' => 'Olar',
            'email' => 'giovanni-olar@gmail.com',
        ],
        [
            'first_name' => 'Horváth',
            'last_name' => 'Andrea',
            'email' => 'horvath-andrea@gmail.com',
        ],
        [
            'first_name' => 'Iusan',
            'last_name' => 'Cristina Maria',
            'email' => 'iusan-cristina-maria@gmail.com',
        ],
        [
            'first_name' => 'Kovács',
            'last_name' => 'Ferenc Róbert',
            'email' => 'kovacs-ferenc-robert@gmail.com',
        ],
        [
            'first_name' => 'Márkos',
            'last_name' => 'Ildikó',
            'email' => 'markos-ildiko@gmail.com',
        ],
        [
            'first_name' => 'Nagy',
            'last_name' => 'Évi',
            'email' => 'nagy-evi@gmail.com',
        ],
        [
            'first_name' => 'Nagy',
            'last_name' => 'Noémi',
            'email' => 'nagy-noemi@gmail.com',
        ],
        [
            'first_name' => 'Nagy',
            'last_name' => 'Tamás',
            'email' => 'nagy-tamas@gmail.com',
        ],
        [
            'first_name' => 'Puskás',
            'last_name' => 'István',
            'email' => 'puskas-istvan@gmail.com',
        ],
        [
            'first_name' => 'Raymond',
            'last_name' => 'Gal',
            'email' => 'raymond-gal@gmail.com',
        ],
        [
            'first_name' => 'Réka',
            'last_name' => 'Láv',
            'email' => 'reka-lav@gmail.com',
        ],
        [
            'first_name' => 'Szántó',
            'last_name' => 'Leila',
            'email' => 'szanto-leila@gmail.com',
        ],
        [
            'first_name' => 'Szidónia',
            'last_name' => 'Szőke',
            'email' => 'szidonia-szoke@gmail.com',
        ],
        [
            'first_name' => 'Szilárd',
            'last_name' => 'Dali',
            'email' => 'szilard-dali@gmail.com',
        ],
        [
            'first_name' => 'Voloncs',
            'last_name' => 'Béla',
            'email' => 'voloncs-bela@gmail.com',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::$users as $user) {
            User::create(
                [
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
