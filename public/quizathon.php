<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

if (isset($_GET['subject'])) {
    set_active_subject_slug((string) $_GET['subject']);
}

$subject = get_active_subject();
$subjectId = (int) ($subject['id'] ?? 0);
$quizReady = Quiz::isReady();
$grouped = $quizReady ? Quiz::allForSubjectGrouped($subjectId) : ['quiz1' => [], 'quiz2' => [], 'endterm' => []];
$bestAttempts = $quizReady ? Quiz::bestAttemptsForUserSubject((int) $user['id'], $subjectId) : [];

render_app_layout('Quizathon', $user, static function () use ($subject, $quizReady, $grouped, $bestAttempts): void {
    ?>
    <section class="page-header">
        <div>
            <h1><?= e($subject['name']) ?> Quizathon</h1>
            <p class="page-subtitle">Three checkpoints per subject: Quiz 1, Quiz 2, and Endterm.</p>
        </div>
    </section>

    <?php if (!$quizReady): ?>
        <section class="card">
            <p class="diff-hard">Quizathon tables are not ready yet. Run migration <strong>migrations/006_quizathon.sql</strong>.</p>
        </section>
    <?php else: ?>
        <section class="grid grid-3 quizathon-sections">
            <?php foreach (Quiz::sections() as $sectionKey => $sectionLabel): ?>
                <article class="card quizathon-section-card" id="<?= e($sectionKey) ?>">
                    <h2 style="margin-bottom:6px;"><?= e($sectionLabel) ?></h2>
                    <p class="muted" style="margin-bottom:10px;"><?= count($grouped[$sectionKey] ?? []) ?> quiz(es) available</p>
                    <div class="grid quizathon-section-list" style="gap:10px;">
                        <?php foreach ($grouped[$sectionKey] ?? [] as $quiz): ?>
                            <?php $attempt = $bestAttempts[(int) $quiz['id']] ?? null; ?>
                            <div class="mini-problem-card">
                                <strong><?= e($quiz['title']) ?></strong>
                                <p class="muted"><?= e((string) $quiz['description']) ?></p>
                                <p class="muted">Questions: <?= (int) ($quiz['question_count'] ?? 0) ?></p>
                                <?php if ($attempt): ?>
                                    <p class="muted">Best: <?= (int) $attempt['best_score'] ?>/<?= (int) $attempt['total_questions'] ?> (<?= e((string) $attempt['best_percentage']) ?>%)</p>
                                <?php endif; ?>
                                <a class="btn-ghost" href="<?= e(app_url('quiz.php?id=' . (int) $quiz['id'])) ?>">Start Quiz</a>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($grouped[$sectionKey])): ?>
                            <p class="muted">No quizzes available in this section yet.</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php
});
