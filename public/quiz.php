<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$quizId = (int) ($_GET['id'] ?? 0);
$quiz = Quiz::findWithSubject($quizId);

if (!$quiz || ((int) $quiz['is_active'] !== 1 && ($user['role'] ?? 'student') !== 'admin')) {
    redirect('quizathon.php');
}

set_active_subject_slug((string) ($quiz['subject_slug'] ?? 'sql'));
$questions = Quiz::questions((int) $quiz['id']);

if (!$questions) {
    redirect('quizathon.php');
}

$result = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $provided = (string) ($_POST['_csrf'] ?? '');
    $stored = (string) ($_SESSION['_csrf'] ?? '');

    if ($provided === '' || $stored === '' || !hash_equals($stored, $provided)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }

    $answers = is_array($_POST['answers'] ?? null) ? $_POST['answers'] : [];
    $result = Quiz::evaluate((int) $quiz['id'], (int) $user['id'], $answers);
}

render_app_layout($quiz['title'], $user, static function () use ($quiz, $questions, $result): void {
    ?>
    <section class="page-header">
        <div>
            <h1><?= e($quiz['title']) ?></h1>
            <p class="page-subtitle"><?= e((string) $quiz['description']) ?> · <?= e(Quiz::sectionLabel((string) $quiz['section'])) ?></p>
        </div>
        <a class="btn-ghost" href="<?= e(app_url('quizathon.php?subject=' . urlencode((string) $quiz['subject_slug']))) ?>">Back to Quizathon</a>
    </section>

    <?php if ($result): ?>
        <?php $isPass = (float) $result['percentage'] >= 60.0; ?>
        <section class="card" style="margin-bottom:16px; border-color:<?= $isPass ? 'rgba(31, 122, 67, 0.3)' : 'rgba(157, 47, 47, 0.3)' ?>;">
            <h2 style="margin-bottom:8px;">Result: <?= (int) $result['score'] ?>/<?= (int) $result['total'] ?> (<?= e((string) $result['percentage']) ?>%)</h2>
            <p class="<?= $isPass ? 'diff-easy' : 'diff-hard' ?>"><?= $isPass ? 'Nice work. You passed this quiz.' : 'Keep practicing and try again.' ?></p>
        </section>
    <?php endif; ?>

    <section class="card">
        <form method="post">
            <?= csrf_input() ?>
            <?php foreach ($questions as $index => $question): ?>
                <?php
                $questionId = (int) $question['id'];
                $checked = [];
                if ($result) {
                    foreach ($result['review'] as $reviewRow) {
                        if ((int) $reviewRow['id'] === $questionId) {
                            $checked = $reviewRow;
                            break;
                        }
                    }
                }
                ?>
                <div class="card" style="margin-bottom:12px; padding:14px;">
                    <p style="margin-bottom:10px;"><strong>Q<?= $index + 1 ?>.</strong> <?= e((string) $question['question_text']) ?></p>
                    <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                        <?php
                        $key = 'option_' . strtolower($option);
                        $isSelected = (($checked['selected'] ?? '') === $option);
                        $isCorrect = (($checked['correct'] ?? '') === $option);
                        ?>
                        <label class="quiz-option">
                            <input class="quiz-option-input" type="radio" name="answers[<?= $questionId ?>]" value="<?= $option ?>" <?= $isSelected ? 'checked' : '' ?>>
                            <span class="quiz-option-text"><strong><?= $option ?>.</strong> <?= e((string) $question[$key]) ?></span>
                            <?php if ($result && $isCorrect): ?>
                                <span class="badge badge-success">Correct</span>
                            <?php elseif ($result && $isSelected && !$isCorrect): ?>
                                <span class="badge badge-danger">Your answer</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <button class="btn-primary" type="submit">Submit Quiz</button>
        </form>
    </section>
    <?php
});
