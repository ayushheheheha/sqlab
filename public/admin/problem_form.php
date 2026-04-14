<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

Auth::requireAdmin();

$problemId = (int) ($_GET['id'] ?? 0);
$problem = $problemId > 0 ? Problem::findWithDatasets($problemId) : null;
$datasets = Dataset::all();
$categories = Problem::categories();
$subjects = Subject::allActive();
$selectedDatasetId = isset($problem['dataset_records'][0]['id']) ? (int) $problem['dataset_records'][0]['id'] : 0;
$selectedSubjectId = isset($problem['subject_id']) ? (int) $problem['subject_id'] : 1;
$subjectMap = [];

foreach ($subjects as $subject) {
    $subjectMap[(int) $subject['id']] = strtolower((string) ($subject['slug'] ?? 'sql'));
}

$selectedSubjectSlug = $subjectMap[$selectedSubjectId] ?? 'sql';
$isSqlSubject = $selectedSubjectSlug === 'sql';
?>
<form id="adminProblemForm" class="admin-form">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int) ($problem['id'] ?? 0) ?>">
    <div id="problemFormFlash"></div>
    <div class="form-group">
        <label for="pf_title">Title</label>
        <input id="pf_title" name="title" type="text" required value="<?= e($problem['title'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="pf_description">Description</label>
        <textarea id="pf_description" name="description" rows="6" required><?= e($problem['description'] ?? '') ?></textarea>
    </div>
    <div class="grid grid-2">
        <div class="form-group">
            <label for="pf_subject">Subject</label>
            <select id="pf_subject" name="subject_id" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>" data-subject-slug="<?= e(strtolower((string) ($subject['slug'] ?? 'sql'))) ?>" <?= $selectedSubjectId === (int) $subject['id'] ? 'selected' : '' ?>>
                        <?= e($subject['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="pf_difficulty">Difficulty</label>
            <select id="pf_difficulty" name="difficulty">
                <?php foreach (['easy', 'medium', 'hard'] as $difficulty): ?>
                    <option value="<?= e($difficulty) ?>" <?= ($problem['difficulty'] ?? 'easy') === $difficulty ? 'selected' : '' ?>>
                        <?= e(ucfirst($difficulty)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="pf_category">Category</label>
            <input id="pf_category" name="category" type="text" required value="<?= e($problem['category'] ?? '') ?>" list="problemCategoryOptions">
            <datalist id="problemCategoryOptions">
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e((string) $category) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
    </div>
    <div class="form-group" id="pf_dataset_group" <?= $isSqlSubject ? '' : 'style="display:none;"' ?>>
        <label for="pf_dataset">Dataset</label>
        <select id="pf_dataset" name="dataset_id" <?= $isSqlSubject ? 'required' : '' ?> <?= $isSqlSubject ? '' : 'disabled' ?>>
            <option value="">Select dataset</option>
            <?php foreach ($datasets as $dataset): ?>
                <option value="<?= (int) $dataset['id'] ?>" <?= $selectedDatasetId === (int) $dataset['id'] ? 'selected' : '' ?>>
                    <?= e($dataset['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="pf_expected_query" id="pf_expected_label"><?= $isSqlSubject ? 'Expected Query' : 'Test Cases' ?></label>
        <textarea id="pf_expected_query" name="expected_query" rows="4" required placeholder="<?= e($isSqlSubject ? 'SELECT ...' : "5 9 || 14\n10 25 || 35") ?>"><?= e($problem['expected_query'] ?? '') ?></textarea>
        <p class="muted" id="pf_expected_help" style="margin-top:8px;"><?= $isSqlSubject ? 'Provide the exact query used for correctness checks.' : 'Use one test case per line: input || expected_output' ?></p>
        <div style="margin-top:8px;" id="pf_expected_test_wrap" <?= $isSqlSubject ? '' : 'hidden' ?>>
            <button class="btn-ghost" type="button" id="testExpectedQuery">Test Query</button>
        </div>
    </div>
    <div id="problemTestResult" class="table-shell" hidden style="margin-bottom:12px;"></div>
    <div class="form-group">
        <label for="pf_hint1">Hint 1</label>
        <textarea id="pf_hint1" name="hint1" rows="2"><?= e($problem['hint1'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="pf_hint2">Hint 2</label>
        <textarea id="pf_hint2" name="hint2" rows="2"><?= e($problem['hint2'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
        <label for="pf_hint3">Hint 3</label>
        <textarea id="pf_hint3" name="hint3" rows="2"><?= e($problem['hint3'] ?? '') ?></textarea>
    </div>
    <div class="form-group admin-checkbox">
        <input id="pf_active" type="checkbox" name="is_active" value="1" <?= (int) ($problem['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label for="pf_active" style="margin:0;">Is Active</label>
    </div>
    <button class="btn-primary" type="submit">Save Problem</button>
</form>
