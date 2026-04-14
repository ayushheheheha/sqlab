<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$subjects = Subject::allActive();

$subjectId = (int) ($_GET['subject_id'] ?? 0);
if ($subjectId <= 0 && $subjects) {
    $subjectId = (int) $subjects[0]['id'];
}

$section = trim((string) ($_GET['section'] ?? ''));
$section = $section === '' ? '' : Quiz::normalizeSection($section);
$quizzes = Quiz::allForAdmin($subjectId > 0 ? $subjectId : null, $section !== '' ? $section : null);

render_app_layout('Admin Quizzes', $user, static function () use ($subjects, $subjectId, $section, $quizzes): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Manage Quizathon</h1>
            <p class="page-subtitle">Create quizzes under Quiz 1, Quiz 2, and Endterm for each subject.</p>
        </div>
        <a class="btn-primary" href="<?= e(app_url('admin/quiz_form.php')) ?>">Add Quiz</a>
    </section>

    <section class="card" style="margin-bottom:16px;">
        <form method="get" class="grid grid-2" style="gap:12px;">
            <div class="form-group" style="margin-bottom:0;">
                <label for="subject_id">Subject</label>
                <select name="subject_id" id="subject_id">
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>" <?= (int) $subject['id'] === $subjectId ? 'selected' : '' ?>>
                            <?= e($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label for="section">Section</label>
                <select name="section" id="section">
                    <option value="">All</option>
                    <?php foreach (Quiz::sections() as $sectionKey => $sectionLabel): ?>
                        <option value="<?= e($sectionKey) ?>" <?= $section === $sectionKey ? 'selected' : '' ?>><?= e($sectionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button class="btn-primary" type="submit">Apply Filter</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Section</th>
                    <th>Questions</th>
                    <th>Active</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($quizzes as $quiz): ?>
                    <tr>
                        <td><?= e($quiz['title']) ?></td>
                        <td><?= e($quiz['subject_name']) ?></td>
                        <td><?= e(Quiz::sectionLabel((string) $quiz['section'])) ?></td>
                        <td><?= (int) $quiz['question_count'] ?></td>
                        <td><?= (int) $quiz['is_active'] === 1 ? 'Yes' : 'No' ?></td>
                        <td>
                            <a class="btn-ghost" href="<?= e(app_url('admin/quiz_form.php?id=' . (int) $quiz['id'])) ?>">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$quizzes): ?>
                    <tr><td colspan="6">No quizzes found for this filter.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
});
