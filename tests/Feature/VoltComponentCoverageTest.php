<?php

use Illuminate\Support\Facades\File;

test('every Volt component has at least one Volt::test reference somewhere in the test suite', function () {
    $componentFiles = collect(File::allFiles(resource_path('views/livewire')))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->filter(fn ($file) => str_contains(File::get($file->getPathname()), 'new class extends Component'));

    expect($componentFiles)->not->toBeEmpty();

    $testSource = collect(File::allFiles(base_path('tests')))
        ->map(fn ($file) => File::get($file->getPathname()))
        ->implode("\n");

    $untested = $componentFiles
        ->map(function ($file) {
            $relative = str($file->getPathname())
                ->after('views/livewire/')
                ->beforeLast('.blade.php')
                ->replace('/', '.');

            return (string) $relative;
        })
        ->reject(fn ($dotName) => str_contains($testSource, "Volt::test('{$dotName}'") || str_contains($testSource, "Volt::test(\"{$dotName}\""));

    expect($untested)->toBeEmpty("The following Volt components have no Volt::test() coverage: {$untested->implode(', ')}");
});
