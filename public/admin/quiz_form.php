<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$subjects = Subject::allActive();
$quizId = (int) ($_GET['id'] ?? 0);
$quiz = $quizId > 0 ? Quiz::findWithSubject($quizId) : null;
$questionsText = $quiz ? Quiz::questionsToText(Quiz::questions((int) $quiz['id'])) : '';

$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf_request();

    try {
        $savedId = Quiz::save([
            'id' => (int) ($_POST['id'] ?? 0),
            'subject_id' => (int) ($_POST['subject_id'] ?? 0),
            'section' => (string) ($_POST['section'] ?? 'quiz1'),
            'title' => (string) ($_POST['title'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'questions_raw' => (string) ($_POST['questions_raw'] ?? ''),
            'is_active' => (int) ($_POST['is_active'] ?? 0),
        ], (int) $user['id']);

        redirect('admin/quiz_form.php?id=' . $savedId . '&saved=1');
    } catch (Throwable $throwable) {
        $flash = ['type' => 'error', 'message' => $throwable->getMessage()];
        $quiz = [
            'id' => (int) ($_POST['id'] ?? 0),
            'subject_id' => (int) ($_POST['subject_id'] ?? 0),
            'section' => (string) ($_POST['section'] ?? 'quiz1'),
            'title' => (string) ($_POST['title'] ?? ''),
            'description' => (string) ($_POST['description'] ?? ''),
            'is_active' => (int) ($_POST['is_active'] ?? 0),
            'subject_name' => '',
            'subject_slug' => 'sql',
        ];
        $questionsText = (string) ($_POST['questions_raw'] ?? '');
    }
}

if (isset($_GET['saved'])) {
    $flash = ['type' => 'success', 'message' => 'Quiz saved successfully.'];
    $quizId = (int) ($_GET['id'] ?? 0);
    $quiz = $quizId > 0 ? Quiz::findWithSubject($quizId) : $quiz;
    $questionsText = $quiz ? Quiz::questionsToText(Quiz::questions((int) $quiz['id'])) : $questionsText;
}

$selectedSubjectId = (int) ($quiz['subject_id'] ?? ($subjects[0]['id'] ?? 0));
$selectedSection = (string) ($quiz['section'] ?? 'quiz1');

render_app_layout('Quiz Form', $user, static function () use ($flash, $quiz, $subjects, $selectedSubjectId, $selectedSection, $questionsText): void {
    ?>
    <section class="page-header">
        <div>
            <h1><?= $quiz ? 'Edit Quiz' : 'Create Quiz' ?></h1>
            <p class="page-subtitle">Minimal quiz builder for Quizathon sections.</p>
        </div>
        <a class="btn-ghost" href="<?= e(app_url('admin/quizzes.php')) ?>">Back to Quizzes</a>
    </section>

    <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>" style="margin-bottom:16px;">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <form method="post" class="admin-form">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int) ($quiz['id'] ?? 0) ?>">

            <div class="grid grid-2">
                <div class="form-group">
                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id" required>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= (int) $subject['id'] ?>" <?= (int) $subject['id'] === $selectedSubjectId ? 'selected' : '' ?>>
                                <?= e($subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="section">Section</label>
                    <select id="section" name="section" required>
                        <?php foreach (Quiz::sections() as $sectionKey => $sectionLabel): ?>
                            <option value="<?= e($sectionKey) ?>" <?= $selectedSection === $sectionKey ? 'selected' : '' ?>>
                                <?= e($sectionLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Quiz Title</label>
                <input id="title" name="title" type="text" required value="<?= e((string) ($quiz['title'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3" required><?= e((string) ($quiz['description'] ?? '')) ?></textarea>
            </div>

            <div class="form-group">
                <label for="questions_raw">Questions</label>
                <textarea id="questions_raw" name="questions_raw" rows="8" required><?= e($questionsText) ?></textarea>
                <p class="muted" style="margin-top:8px;">Format each line as: Question || Option A || Option B || Option C || Option D || CorrectOption(A/B/C/D)</p>
                <p class="muted">Example: Which clause filters rows? || WHERE || HAVING || GROUP BY || ORDER BY || A</p>
            </div>

            <div class="form-group admin-checkbox">
                <input id="is_active" type="checkbox" name="is_active" value="1" <?= (int) ($quiz['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label for="is_active" style="margin:0;">Is Active</label>
            </div>

            <button class="btn-primary" type="submit">Save Quiz</button>
        </form>
    </section>
    <?php
});
