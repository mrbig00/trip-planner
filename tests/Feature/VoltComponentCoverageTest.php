<?php

use Illuminate\Support\Facades\File;

function voltCoverageStripComments(array $tokens): array
{
    return array_values(array_filter(
        $tokens,
        fn ($token) => ! is_array($token) || ! in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
    ));
}

function voltCoverageTokenText(mixed $token): string
{
    return is_array($token) ? $token[1] : $token;
}

function voltCoverageSkipWhitespace(array $tokens, int $cursor): int
{
    while (is_array($tokens[$cursor] ?? null) && $tokens[$cursor][0] === T_WHITESPACE) {
        $cursor++;
    }

    return $cursor;
}

/**
 * Detect an actual `new class extends Component { ... }` declaration by walking
 * the token stream for that literal word sequence, so a comment, docblock, or
 * string literal containing the same words can't produce a false positive.
 */
function voltCoverageDeclaresComponent(array $tokens): bool
{
    $sequence = ['new', 'class', 'extends', 'Component'];
    $position = 0;

    foreach ($tokens as $token) {
        $text = trim(voltCoverageTokenText($token));

        if ($text === '') {
            continue;
        }

        if (strcasecmp($text, $sequence[$position]) === 0) {
            $position++;

            if ($position === count($sequence)) {
                return true;
            }
        } else {
            $position = strcasecmp($text, $sequence[0]) === 0 ? 1 : 0;
        }
    }

    return false;
}

/**
 * Detect an actual `Volt::test('<dotName>' ...)` call node by walking the token
 * stream for that call shape, so a reference inside a comment or unrelated
 * string can't satisfy the completeness guard.
 */
function voltCoverageReferencesTest(array $tokens, string $dotName): bool
{
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'Volt') {
            continue;
        }

        $cursor = voltCoverageSkipWhitespace($tokens, $i + 1);

        if (! is_array($tokens[$cursor] ?? null) || $tokens[$cursor][0] !== T_DOUBLE_COLON) {
            continue;
        }

        $cursor = voltCoverageSkipWhitespace($tokens, $cursor + 1);

        if (! is_array($tokens[$cursor] ?? null) || $tokens[$cursor][0] !== T_STRING || $tokens[$cursor][1] !== 'test') {
            continue;
        }

        $cursor = voltCoverageSkipWhitespace($tokens, $cursor + 1);

        if (($tokens[$cursor] ?? null) !== '(') {
            continue;
        }

        $cursor = voltCoverageSkipWhitespace($tokens, $cursor + 1);

        $argument = $tokens[$cursor] ?? null;

        if (is_array($argument) && $argument[0] === T_CONSTANT_ENCAPSED_STRING && substr($argument[1], 1, -1) === $dotName) {
            return true;
        }
    }

    return false;
}

test('every Volt component has at least one Volt::test reference somewhere in the test suite', function () {
    $componentFiles = collect(File::allFiles(resource_path('views/livewire')))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->filter(fn ($file) => voltCoverageDeclaresComponent(voltCoverageStripComments(token_get_all(File::get($file->getPathname())))));

    expect($componentFiles)->not->toBeEmpty();

    $testFileTokens = collect(File::allFiles(base_path('tests')))
        ->map(fn ($file) => voltCoverageStripComments(token_get_all(File::get($file->getPathname()))));

    $untested = $componentFiles
        ->map(function ($file) {
            $relative = str($file->getPathname())
                ->after('views/livewire/')
                ->beforeLast('.blade.php')
                ->replace('/', '.');

            return (string) $relative;
        })
        ->reject(fn ($dotName) => $testFileTokens->contains(fn ($tokens) => voltCoverageReferencesTest($tokens, $dotName)));

    expect($untested)->toBeEmpty("The following Volt components have no Volt::test() coverage: {$untested->implode(', ')}");
});
