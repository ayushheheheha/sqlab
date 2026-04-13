<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$editingId = (int) ($_GET['edit'] ?? 0);
$editingProblem = $editingId > 0 ? Problem::find($editingId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'difficulty' => $_POST['difficulty'] ?? 'easy',
        'category' => $_POST['category'] ?? '',
        'expected_query' => $_POST['expected_query'] ?? '',
        'dataset_sql' => $_POST['dataset_sql'] ?? '',
        'hint1' => $_POST['hint1'] ?? '',
        'hint2' => $_POST['hint2'] ?? '',
        'hint3' => $_POST['hint3'] ?? '',
        'is_active' => $_POST['is_active'] ?? '',
        'created_by' => (int) $user['id'],
    ];

    if (!empty($_POST['problem_id'])) {
        Problem::update((int) $_POST['problem_id'], $payload);
    } else {
        Problem::create($payload);
    }

    redirect('admin/problems.php');
}

$problems = Problem::allWithMeta();

render_app_layout('Admin Problems', $user, static function () use ($problems, $editingProblem): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Manage Problems</h1>
            <p class="page-subtitle">Create new problems or tune seeded content.</p>
        </div>
    </section>
    <section class="grid grid-2">
        <article class="card">
            <h2 style="margin-bottom:16px;"><?= $editingProblem ? 'Edit Problem' : 'New Problem' ?></h2>
            <form method="post">
                <input type="hidden" name="problem_id" value="<?= (int) ($editingProblem['id'] ?? 0) ?>">
                <div class="form-group"><label for="title">Title</label><input id="title" type="text" name="title" value="<?= e($editingProblem['title'] ?? '') ?>" required></div>
                <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" rows="5" required><?= e($editingProblem['description'] ?? '') ?></textarea></div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="difficulty">Difficulty</label>
                        <select id="difficulty" name="difficulty">
                            <?php foreach (['easy', 'medium', 'hard'] as $difficulty): ?>
                                <option value="<?= $difficulty ?>" <?= ($editingProblem['difficulty'] ?? '') === $difficulty ? 'selected' : '' ?>><?= ucfirst($difficulty) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="category">Category</label><input id="category" type="text" name="category" value="<?= e($editingProblem['category'] ?? '') ?>" required></div>
                </div>
                <div class="form-group"><label for="expected_query">Expected Query</label><textarea id="expected_query" name="expected_query" rows="4" required><?= e($editingProblem['expected_query'] ?? '') ?></textarea></div>
                <div class="form-group"><label for="dataset_sql">Dataset SQL Override</label><textarea id="dataset_sql" name="dataset_sql" rows="4"><?= e($editingProblem['dataset_sql'] ?? '') ?></textarea></div>
                <div class="form-group"><label for="hint1">Hint 1</label><input id="hint1" type="text" name="hint1" value="<?= e($editingProblem['hint1'] ?? '') ?>"></div>
                <div class="form-group"><label for="hint2">Hint 2</label><input id="hint2" type="text" name="hint2" value="<?= e($editingProblem['hint2'] ?? '') ?>"></div>
                <div class="form-group"><label for="hint3">Hint 3</label><input id="hint3" type="text" name="hint3" value="<?= e($editingProblem['hint3'] ?? '') ?>"></div>
                <div class="form-group"><label for="is_active">Active</label><select id="is_active" name="is_active"><option value="1" <?= ((int) ($editingProblem['is_active'] ?? 1) === 1) ? 'selected' : '' ?>>Yes</option><option value="0" <?= ((int) ($editingProblem['is_active'] ?? 1) === 0) ? 'selected' : '' ?>>No</option></select></div>
                <button class="btn-primary" type="submit"><?= $editingProblem ? 'Update Problem' : 'Create Problem' ?></button>
            </form>
        </article>
        <article class="card">
            <h2 style="margin-bottom:16px;">Existing Problems</h2>
            <div class="table-shell">
                <table>
                    <thead>
                    <tr>
                        <th>Title</th>
                        <th>Difficulty</th>
                        <th>Datasets</th>
                        <th>Author</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($problems as $problem): ?>
                        <tr>
                            <td><?= e($problem['title']) ?></td>
                            <td><?= e($problem['difficulty']) ?></td>
                            <td><?= e($problem['datasets'] ?? 'Custom') ?></td>
                            <td><?= e($problem['author'] ?? 'Unknown') ?></td>
                            <td><a href="<?= e(app_url('admin/problems.php?edit=' . (int) $problem['id'])) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
    <?php
});
