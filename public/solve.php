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

$schemaSql = implode("\n\n", array_filter(array_map(
    static fn (array $dataset): string => trim((string) $dataset['schema_sql']),
    $problem['dataset_records'] ?? []
)));
preg_match('/CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $schemaSql, $tableMatch);
$starterQuery = 'SELECT * FROM ' . ($tableMatch[1] ?? 'users') . ' LIMIT 10';
$difficultyBadge = $problem['difficulty'] === 'easy' ? 'success' : ($problem['difficulty'] === 'medium' ? 'warning' : 'danger');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($problem['title']) ?> | SQLab</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body class="solve-body">
    <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">Theme</button>
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
                <button class="btn-primary" id="runQuery" type="button">Run Query <span class="muted">(Ctrl+Enter)</span></button>
                <button class="btn-ghost" id="resetQuery" type="button">Reset</button>
                <button class="btn-ghost" id="openSchema" type="button">DB Schema</button>
                <button class="btn-ghost" id="hintButton" type="button">Hint 1</button>
                <span class="solve-time" id="executionTime"></span>
            </div>
            <div class="solve-hint" id="hintBox" hidden></div>
            <div class="monaco-shell" id="editor"></div>
        </section>
        <section class="solve-right">
            <div class="solve-tabs" role="tablist">
                <button class="active" data-tab="results" type="button">Results</button>
                <button data-tab="expected" type="button">Expected</button>
                <button data-tab="submissions" type="button">Submissions</button>
            </div>
            <div class="solve-output">
                <div class="solve-tab-panel active" id="tab-results">
                    <div class="empty-state">Run your query to see results.</div>
                </div>
                <div class="solve-tab-panel" id="tab-expected">
                    <div class="empty-state">Open this tab to fetch the expected output.</div>
                </div>
                <div class="solve-tab-panel" id="tab-submissions">
                    <div class="empty-state">Open this tab to fetch your recent submissions.</div>
                </div>
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
            <pre><code><?= e($schemaSql) ?></code></pre>
        </div>
    </div>
    <script>
        window.SQLAB_SOLVE = {
            problemId: <?= (int) $problem['id'] ?>,
            starterQuery: <?= json_encode($starterQuery, JSON_THROW_ON_ERROR) ?>,
            endpoints: {
                execute: <?= json_encode(app_url('api/execute.php'), JSON_THROW_ON_ERROR) ?>,
                hint: <?= json_encode(app_url('api/hint.php'), JSON_THROW_ON_ERROR) ?>,
                expected: <?= json_encode(app_url('api/expected.php?problem_id=' . (int) $problem['id']), JSON_THROW_ON_ERROR) ?>,
                submissions: <?= json_encode(app_url('api/submissions.php?problem_id=' . (int) $problem['id']), JSON_THROW_ON_ERROR) ?>
            }
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
    <script src="<?= e(app_url('assets/js/solve.js')) ?>" defer></script>
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
