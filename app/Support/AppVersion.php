<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

final class AppVersion
{
    public static function version(): string
    {
        return once(function () {
            if ($version = env('APP_VERSION')) {
                return $version;
            }

            $composer = json_decode(file_get_contents(base_path('composer.json')), true);

            return $composer['version'] ?? 'dev';
        });
    }

    public static function commit(): ?string
    {
        return once(function () {
            if ($commit = env('APP_COMMIT')) {
                return substr($commit, 0, 7);
            }

            if (! is_dir(base_path('.git'))) {
                return null;
            }

            $result = Process::path(base_path())->run('git rev-parse --short HEAD');

            return $result->successful() ? trim($result->output()) : null;
        });
    }
}
