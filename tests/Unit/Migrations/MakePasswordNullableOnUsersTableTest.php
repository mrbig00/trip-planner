<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('rolling back does not fail when a social-only user has a null password', function () {
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['password' => null]);

    $migration = require database_path('migrations/2026_08_01_000000_make_password_nullable_on_users_table.php');

    $migration->down();

    expect(Schema::hasColumn('users', 'password'))->toBeTrue();
    expect(DB::table('users')->where('id', $user->id)->value('password'))->not->toBeNull();
});
