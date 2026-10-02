<?php

/**
 * Wandelt das JUnit-Protokoll der Testsuite in resources/data/test-report.json
 * für die Seite /roadmap/tests um. Läuft im Deploy-Workflow nach den Tests
 * (dort sind die Testklassen samt Beschreibungen verfügbar; auf dem Server
 * liegt nur das Ergebnis).
 *
 * Aufruf: php .github/scripts/test-report.php <junit.xml> <ziel.json>
 */

use PHPUnit\Framework\Attributes\TestDox;

require __DIR__.'/../../vendor/autoload.php';

[, $junitFile, $target] = $argv + [null, null, null];
if (! $junitFile || ! $target || ! is_file($junitFile)) {
    fwrite(STDERR, "Aufruf: php test-report.php <junit.xml> <ziel.json>\n");
    exit(1);
}

$xml = simplexml_load_file($junitFile);
$root = $xml->testsuite;

/** Erster Absatz eines Docblocks als Klartext. */
$docSummary = function (string|false $doc): string {
    if (! $doc) {
        return '';
    }
    $lines = array_map(fn ($l) => trim(preg_replace('#^\s*/?\*+/?#', '', $l)), explode("\n", $doc));
    $text = trim(implode("\n", $lines));
    $paragraph = preg_split('/\n\s*\n/', $text)[0] ?? '';

    return trim(preg_replace('/\s+/', ' ', $paragraph));
};

$areas = [];
foreach ($root->xpath('//testsuite[testcase]') as $suite) {
    $class = (string) $suite['name'];
    $ref = class_exists($class) ? new ReflectionClass($class) : null;
    $area = preg_match('#^Tests\\\\Feature\\\\([^\\\\]+)\\\\#', $class, $m) ? $m[1] : 'Sonstiges';

    $tests = [];
    foreach ($suite->testcase as $case) {
        $method = (string) $case['name'];
        $dox = null;
        if ($ref?->hasMethod($method)) {
            foreach ($ref->getMethod($method)->getAttributes(TestDox::class) as $attr) {
                $dox = $attr->newInstance()->text();
            }
        }
        $status = 'passed';
        $message = null;
        foreach (['failure' => 'failed', 'error' => 'failed', 'skipped' => 'skipped'] as $tag => $state) {
            if (isset($case->{$tag})) {
                $status = $state;
                // Nur die erste Zeile – keine Stacktraces/Pfade veröffentlichen.
                $message = strtok(trim((string) ($case->{$tag}['message'] ?? $case->{$tag})), "\n") ?: null;
            }
        }
        $tests[] = [
            'method' => $method,
            'name' => $dox ?? ucfirst(str_replace('_', ' ', preg_replace('/^test_/', '', $method))),
            'status' => $status,
            'message' => $message,
            'assertions' => (int) $case['assertions'],
            'time' => round((float) $case['time'], 3),
        ];
    }

    $areas[$area][] = [
        'class' => $ref?->getShortName() ?? $class,
        'description' => $docSummary($ref?->getDocComment() ?? false),
        'tests' => $tests,
    ];
}
ksort($areas);

$sha = getenv('GITHUB_SHA') ?: trim((string) @shell_exec('git rev-parse HEAD'));
$repo = getenv('GITHUB_REPOSITORY');
$server = getenv('GITHUB_SERVER_URL') ?: 'https://github.com';

$report = [
    'generated_at' => gmdate('c'),
    'commit' => $sha ? substr($sha, 0, 7) : null,
    'commit_message' => strtok(trim((string) @shell_exec('git log -1 --format=%s')), "\n") ?: null,
    'commit_url' => $repo && $sha ? "{$server}/{$repo}/commit/{$sha}" : null,
    'run_url' => $repo && getenv('GITHUB_RUN_ID') ? "{$server}/{$repo}/actions/runs/".getenv('GITHUB_RUN_ID') : null,
    'totals' => [
        'tests' => (int) $root['tests'],
        'assertions' => (int) $root['assertions'],
        'failed' => (int) $root['failures'] + (int) $root['errors'],
        'skipped' => (int) $root['skipped'],
        'time' => round((float) $root['time'], 1),
    ],
    'areas' => $areas,
];

file_put_contents($target, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
echo "Testbericht geschrieben: {$target} ({$report['totals']['tests']} Tests)\n";
