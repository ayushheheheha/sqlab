<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$activeSubject = get_active_subject();
$subjectSlug = strtolower((string) ($activeSubject['slug'] ?? 'sql'));
$isSqlSubject = $subjectSlug === 'sql';

$starterQuery = <<<SQL
CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  price DECIMAL(10,2) NOT NULL
);

INSERT INTO products (name, price) VALUES
('Mouse', 499.00),
('Keyboard', 1299.00);

SELECT * FROM products;
SQL;

if (!$isSqlSubject) {
    $starterQuery = $subjectSlug === 'java'
        ? "public class Main {\n    public static void main(String[] args) {\n        System.out.println(\"Practice mode\");\n    }\n}" 
        : "print('Practice mode')";
}

$editorLanguage = $isSqlSubject ? 'sql' : ($subjectSlug === 'java' ? 'java' : 'python');
$runLabel = $isSqlSubject ? 'Run SQL' : 'Run Code';
$resetLabel = $isSqlSubject ? 'Reset Query' : 'Reset Code';
$modeBadge = strtoupper($subjectSlug) . ' Runtime';
$descText = $isSqlSubject
    ? 'This is your own SQL playground. Create tables, insert/update/delete rows, and run any normal SQL statements. Your sandbox is isolated and can be reset anytime.'
    : 'Run and test code snippets in your active subject runtime. Output, compile errors, and runtime errors appear in the right panel.';
$emptyText = $isSqlSubject ? 'Run SQL to see output.' : 'Run code to see output.';
$stdinPlaceholder = $subjectSlug === 'java'
    ? "Example:\n5\n9"
    : "Example:\n5 9";

$assetVersion = (string) filemtime(__DIR__ . '/assets/js/practice.js');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>Practice Lab | GenzLAB</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css?v=' . $assetVersion)) ?>">
</head>
<body class="solve-body">
    <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
    </button>
    <main class="solve-layout">
        <section class="solve-left">
            <header class="solve-problem-bar">
                <div>
                    <h1>Practice Lab</h1>
                    <div class="solve-badges">
                        <span class="badge badge-muted"><?= e($modeBadge) ?></span>
                        <?php if ($isSqlSubject): ?>
                            <span class="badge badge-warning">Isolated per session</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a class="btn-ghost" href="<?= e(app_url('dashboard.php')) ?>">Back</a>
            </header>
            <details class="solve-description" open>
                <summary>How this works</summary>
                <p><?= e($descText) ?></p>
            </details>
            <div class="solve-toolbar">
                <button class="btn-primary" id="runPracticeQuery" type="button"><?= e($runLabel) ?> <span class="muted">(Ctrl+Enter)</span></button>
                <button class="btn-ghost" id="resetPracticeQuery" type="button"><?= e($resetLabel) ?></button>
                <?php if ($isSqlSubject): ?>
                    <button class="btn-ghost" id="resetPracticeDb" type="button">Reset Database</button>
                <?php endif; ?>
                <span class="solve-time" id="practiceExecutionTime"></span>
            </div>
            <?php if (!$isSqlSubject): ?>
                <div class="card" style="padding:10px 14px; margin-top:10px;">
                    <label for="practiceStdin" style="display:block; font-weight:600; margin-bottom:6px;">Program Input (stdin)</label>
                    <textarea id="practiceStdin" rows="3" class="code-editor" style="min-height:78px; width:100%;" placeholder="<?= e($stdinPlaceholder) ?>"></textarea>
                </div>
            <?php endif; ?>
            <?php if ($isSqlSubject): ?>
                <div class="solve-hint">Tip: Run one statement at a time for best results.</div>
            <?php endif; ?>
            <div class="monaco-shell" id="practiceEditor"></div>
        </section>
        <section class="solve-right">
            <div class="solve-tabs" role="tablist">
                <button class="active" data-tab="results" type="button"><?= e($isSqlSubject ? 'Results' : 'Output') ?></button>
                <?php if ($isSqlSubject): ?>
                    <button data-tab="logs" type="button">Logs</button>
                <?php endif; ?>
            </div>
            <div class="solve-output">
                <div class="solve-tab-panel active" id="practice-tab-results" role="status" aria-live="polite" aria-atomic="false">
                    <div class="empty-state"><?= e($emptyText) ?></div>
                </div>
                <?php if ($isSqlSubject): ?>
                    <div class="solve-tab-panel" id="practice-tab-logs" role="status" aria-live="polite" aria-atomic="false">
                        <div class="empty-state">Execution logs will appear here.</div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script>
        window.SQLAB_PRACTICE = {
            starterQuery: <?= json_encode($starterQuery, JSON_THROW_ON_ERROR) ?>,
            editorLanguage: <?= json_encode($editorLanguage, JSON_THROW_ON_ERROR) ?>,
            subjectSlug: <?= json_encode($subjectSlug, JSON_THROW_ON_ERROR) ?>,
            runLabel: <?= json_encode($runLabel, JSON_THROW_ON_ERROR) ?>,
            endpoints: {
                execute: <?= json_encode(app_url('api/practice_execute.php'), JSON_THROW_ON_ERROR) ?>,
                reset: <?= json_encode(app_url('api/practice_reset.php'), JSON_THROW_ON_ERROR) ?>
            }
        };
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.44.0/min/vs/loader.min.js"></script>
    <script src="<?= e(app_url('assets/js/app.js?v=' . $assetVersion)) ?>" defer></script>
    <script src="<?= e(app_url('assets/js/practice.js?v=' . $assetVersion)) ?>" defer></script>
</body>
</html>
