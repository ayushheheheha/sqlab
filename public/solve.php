<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$problemId = (int) ($_GET['id'] ?? 0);
$problem = Problem::findWithDatasets($problemId);

if (!$problem || (int) $problem['is_active'] !== 1) {
    redirect('problems.php');
}

if (!empty($problem['subject_slug'])) {
    set_active_subject_slug((string) $problem['subject_slug']);
}

$subjectSlug = strtolower((string) ($problem['subject_slug'] ?? 'sql'));
$isSqlSubject = $subjectSlug === 'sql';
$editorLanguage = $isSqlSubject ? 'sql' : ($subjectSlug === 'java' ? 'java' : 'python');

$parseNonSqlCases = static function (string $raw): array {
    $raw = trim($raw);

    if ($raw === '') {
        return [];
    }

    if (str_starts_with($raw, '[')) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $cases = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $expected = trim((string) ($row['expected_output'] ?? ''));
                if ($expected === '') {
                    continue;
                }
                $cases[] = [
                    'input' => (string) ($row['input'] ?? ''),
                    'expected_output' => $expected,
                ];
            }
            return $cases;
        }
    }

    $cases = [];
    foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('||', $line, 2));
        $input = count($parts) === 2 ? $parts[0] : '';
        $expected = count($parts) === 2 ? $parts[1] : ($parts[0] ?? '');
        if ($expected === '') {
            continue;
        }
        $cases[] = ['input' => $input, 'expected_output' => $expected];
    }

    return $cases;
};

$schemaSql = implode("\n\n", array_filter(array_map(
    static fn (array $dataset): string => trim((string) $dataset['schema_sql']),
    $problem['dataset_records'] ?? []
)));
preg_match('/CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $schemaSql, $tableMatch);
$starterQuery = 'SELECT * FROM ' . ($tableMatch[1] ?? 'users') . ' LIMIT 10';

if (!$isSqlSubject) {
    $starterQuery = $subjectSlug === 'java'
        ? "public class Main {\n    public static void main(String[] args) {\n        System.out.println(\"hello world\");\n    }\n}" 
        : "print('hello world')";
}

$runLabel = $isSqlSubject ? 'Run Query' : 'Run Code';
$editorShortcutHint = $isSqlSubject ? '(Ctrl+Enter)' : '(Ctrl+Enter)';
$starterText = $isSqlSubject ? 'Run your query to see results.' : 'Run your code to see results.';
$stdinPlaceholder = $subjectSlug === 'java'
    ? "Example:\n7 8"
    : "Example:\n5 9";
$sampleCases = $isSqlSubject
    ? []
    : $parseNonSqlCases((string) ($problem['expected_query'] ?? ''));

if (!$isSqlSubject && $sampleCases === []) {
    $sampleCases = [
        ['input' => '', 'expected_output' => trim((string) ($problem['expected_query'] ?? ''))],
    ];
}
$difficultyBadge = $problem['difficulty'] === 'easy' ? 'success' : ($problem['difficulty'] === 'medium' ? 'warning' : 'danger');
$datasetId = isset($problem['dataset_records'][0]['id']) ? (int) $problem['dataset_records'][0]['id'] : 0;
$assetVersion = (string) filemtime(__DIR__ . '/assets/js/solve.js');
$chartAssetPath = __DIR__ . '/assets/js/chart.umd.min.js';
$chartAssetVersion = (string) (file_exists($chartAssetPath) ? filemtime($chartAssetPath) : time());
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($problem['title']) ?> | GenzLAB</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css?v=' . $assetVersion)) ?>">
</head>
<body class="solve-body">
    <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
    </button>
    <main class="solve-layout" data-problem-id="<?= (int) $problem['id'] ?>">
        <section class="solve-left">
            <header class="solve-problem-bar">
                <div>
                    <h1><?= e($problem['title']) ?></h1>
                    <div class="solve-badges">
                        <span class="badge badge-<?= e($difficultyBadge) ?>"><?= e(ucfirst($problem['difficulty'])) ?></span>
                        <span class="badge badge-muted"><?= e($problem['category']) ?></span>
                    </div>
                </div>
                <a class="btn-ghost" href="<?= e(app_url('problems.php')) ?>">Back</a>
            </header>
            <details class="solve-description" open>
                <summary>Description</summary>
                <p><?= nl2br(e($problem['description'])) ?></p>
            </details>
            <div class="solve-toolbar">
                <button class="btn-primary" id="runQuery" type="button"><?= e($runLabel) ?> <span class="muted"><?= e($editorShortcutHint) ?></span></button>
                <button class="btn-ghost" id="resetQuery" type="button">Reset</button>
                <?php if ($isSqlSubject): ?>
                    <button class="btn-ghost" id="openSchema" type="button">DB Schema</button>
                <?php endif; ?>
                <button class="btn-ghost" id="hintButton" type="button">Hint 1</button>
                <span class="solve-time" id="executionTime"></span>
            </div>
            <?php if (!$isSqlSubject): ?>
                <div class="card" style="padding:10px 14px; margin-top:10px;">
                    <label for="solveStdin" style="display:block; font-weight:600; margin-bottom:6px;">Program Input (stdin)</label>
                    <textarea id="solveStdin" rows="3" class="code-editor" style="min-height:78px; width:100%;" placeholder="<?= e($stdinPlaceholder) ?>"></textarea>
                </div>
            <?php endif; ?>
            <div class="solve-hint" id="hintBox" hidden></div>
            <div class="monaco-shell" id="editor"></div>
        </section>
        <section class="solve-right">
            <?php if (!$isSqlSubject): ?>
                <div class="solve-cases">
                    <strong>Sample Test Cases</strong>
                    <div class="solve-cases-list">
                        <?php foreach ($sampleCases as $index => $case): ?>
                            <div class="solve-case-row">
                                <span class="badge badge-muted">Case <?= (int) ($index + 1) ?></span>
                                <div class="solve-case-meta">
                                    <span><strong>Input:</strong> <?= e($case['input']) ?></span>
                                    <span><strong>Expected:</strong> <?= e($case['expected_output']) ?></span>
                                </div>
                                <button class="btn-ghost sample-stdin-btn" type="button" data-sample-input="<?= e($case['input']) ?>">Use Input</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="solve-tabs" role="tablist">
                <button class="active" data-tab="results" type="button"><?= e($isSqlSubject ? 'Results' : 'Output') ?></button>
                <?php if ($isSqlSubject): ?>
                    <button data-tab="chart" type="button" id="chartTabButton" disabled>Chart</button>
                    <button data-tab="expected" type="button">Expected</button>
                    <button data-tab="submissions" type="button">Submissions</button>
                <?php endif; ?>
            </div>
            <div class="solve-output">
                <div class="solve-tab-panel active" id="tab-results" role="status" aria-live="polite" aria-atomic="false">
                    <div class="empty-state"><?= e($starterText) ?></div>
                </div>
                <?php if ($isSqlSubject): ?>
                    <div class="solve-tab-panel" id="tab-chart">
                        <div class="chart-toolbar">
                            <label for="chartType">Chart type</label>
                            <select id="chartType">
                                <option value="bar">Bar</option>
                                <option value="line">Line</option>
                                <option value="pie">Pie</option>
                            </select>
                        </div>
                        <div class="chart-wrap">
                            <canvas id="resultChart"></canvas>
                        </div>
                        <p class="muted" id="chartMessage">Run a query to get a chart suggestion.</p>
                    </div>
                    <div class="solve-tab-panel" id="tab-expected">
                        <div class="empty-state">Open this tab to fetch the expected output.</div>
                    </div>
                    <div class="solve-tab-panel" id="tab-submissions" role="status" aria-live="polite" aria-atomic="false">
                        <div class="empty-state">Open this tab to fetch your recent submissions.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <div class="schema-modal" id="schemaModal" hidden>
        <div class="schema-modal-card">
            <div class="page-header">
                <div>
                    <h2>Dataset Schema</h2>
                    <p class="page-subtitle"><?= e($problem['datasets'] ?? 'Dataset') ?></p>
                </div>
                <button class="btn-ghost" id="closeSchema" type="button">Close</button>
            </div>
            <div class="schema-tabs" id="schemaTabs">
                <button class="active" type="button" data-schema-tab="sql">SQL</button>
                <button type="button" data-schema-tab="visual">Visual</button>
            </div>
            <div class="schema-tab-panel active" id="schema-tab-sql">
                <pre><code><?= e($schemaSql) ?></code></pre>
            </div>
            <div class="schema-tab-panel" id="schema-tab-visual">
                <div class="schema-visual-wrap" id="schemaVisualWrap">
                    <div class="empty-state">Open Visual tab to load table diagram.</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        window.SQLAB_SOLVE = {
            problemId: <?= (int) $problem['id'] ?>,
            starterQuery: <?= json_encode($starterQuery, JSON_THROW_ON_ERROR) ?>,
            editorLanguage: <?= json_encode($editorLanguage, JSON_THROW_ON_ERROR) ?>,
            subjectSlug: <?= json_encode($subjectSlug, JSON_THROW_ON_ERROR) ?>,
            runLabel: <?= json_encode($runLabel, JSON_THROW_ON_ERROR) ?>,
            resultEmptyText: <?= json_encode($starterText, JSON_THROW_ON_ERROR) ?>,
            sampleCases: <?= json_encode($sampleCases, JSON_THROW_ON_ERROR) ?>,
            features: {
                schema: <?= $isSqlSubject ? 'true' : 'false' ?>,
                chart: <?= $isSqlSubject ? 'true' : 'false' ?>
            },
            endpoints: {
                execute: <?= json_encode(app_url('api/execute.php'), JSON_THROW_ON_ERROR) ?>,
                hint: <?= json_encode(app_url('api/hint.php'), JSON_THROW_ON_ERROR) ?>,
                expected: <?= json_encode(app_url('api/expected.php?problem_id=' . (int) $problem['id']), JSON_THROW_ON_ERROR) ?>,
                submissions: <?= json_encode(app_url('api/submissions.php?problem_id=' . (int) $problem['id']), JSON_THROW_ON_ERROR) ?>,
                schemaVisual: <?= json_encode(app_url('api/schema_visual.php?dataset_id=' . $datasetId), JSON_THROW_ON_ERROR) ?>
            },
            datasetId: <?= $datasetId ?>,
            chartLocalUrl: <?= json_encode(app_url('assets/js/chart.umd.min.js?v=' . $chartAssetVersion), JSON_THROW_ON_ERROR) ?>
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="<?= e(app_url('assets/js/app.js?v=' . $assetVersion)) ?>" defer></script>
    <script src="<?= e(app_url('assets/js/solve.js?v=' . $assetVersion)) ?>" defer></script>
</body>
</html>
<?php
exit;

$result = null;
$query = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = (string) ($_POST['query'] ?? '');
    $hintsUsed = min(3, max(0, (int) ($_POST['hints_used'] ?? 0)));
    $result = ['success' => false, 'message' => 'The legacy form runner is disabled.'];

    if (($result['success'] ?? false) === true) {
        Submission::record(
            (int) $user['id'],
            $problemId,
            $query,
            (bool) $result['is_correct'],
            (int) $result['execution_time_ms'],
            (int) $result['row_count'],
            $hintsUsed
        );
    }
}

render_app_layout('Solve', $user, static function () use ($problem, $query, $result): void {
    ?>
    <section class="page-header">
        <div>
            <h1><?= e($problem['title']) ?></h1>
            <div class="problem-meta">
                <span><?= e(ucfirst($problem['difficulty'])) ?></span>
                <span><?= e($problem['category']) ?></span>
                <span><?= e($problem['datasets'] ?? 'Custom dataset') ?></span>
            </div>
            <p class="page-subtitle"><?= nl2br(e($problem['description'])) ?></p>
        </div>
    </section>
    <section class="editor-shell">
        <div class="editor-pane">
            <div class="editor-toolbar">
                <strong>SQL Editor</strong>
            </div>
            <form method="post" class="editor-form">
                <input type="hidden" name="hints_used" id="hintsUsed" value="0">
                <textarea name="query" class="code-editor" spellcheck="false" placeholder="SELECT ..."><?= e($query) ?></textarea>
                <div class="editor-actions">
                    <button class="btn-primary" type="submit">Run Query</button>
                </div>
            </form>
            <div class="card" style="margin:16px;">
                <h3>Hints</h3>
                <div class="grid" style="margin-top:12px;">
                    <?php foreach ([1, 2, 3] as $index): ?>
                        <?php $hint = trim((string) ($problem['hint' . $index] ?? '')); ?>
                        <?php if ($hint !== ''): ?>
                            <button class="btn-ghost hint-toggle" data-hint-target="hint<?= $index ?>" type="button">Reveal Hint <?= $index ?></button>
                            <p id="hint<?= $index ?>" hidden class="muted"><?= e($hint) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="result-pane">
            <div class="editor-toolbar">
                <strong>Results</strong>
                <?php if ($result): ?>
                    <span class="<?= $result['is_correct'] ? 'diff-easy' : 'diff-hard' ?>"><?= e($result['message']) ?></span>
                <?php endif; ?>
            </div>
            <div class="result-table-wrap">
                <?php if ($result && ($result['success'] ?? false)): ?>
                    <div class="card">
                        <p class="muted">Execution time: <?= (int) $result['execution_time_ms'] ?> ms • Rows: <?= (int) $result['row_count'] ?></p>
                    </div>
                    <div class="table-shell">
                        <table>
                            <thead>
                            <tr>
                                <?php foreach ($result['columns'] as $column): ?>
                                    <th><?= e((string) $column) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($result['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($row as $value): ?>
                                        <td><?= e((string) $value) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$result['rows']): ?>
                                <tr><td colspan="<?= max(1, count($result['columns'])) ?>">Query returned no rows.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($result): ?>
                    <div class="card">
                        <p class="diff-hard"><?= e($result['message']) ?></p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <p class="muted">Run your query to see results here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
});
