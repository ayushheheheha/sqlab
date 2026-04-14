<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$problems = Problem::allWithMeta();

render_app_layout('Admin Problems', $user, static function () use ($problems): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Manage Problems</h1>
            <p class="page-subtitle">Create, edit, activate/deactivate, and remove problems.</p>
        </div>
        <button class="btn-primary" type="button" id="openProblemCreate">Add Problem</button>
    </section>

    <section class="card">
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>Title</th>
                    <th>Subject</th>
                    <th>Difficulty</th>
                    <th>Category</th>
                    <th>Dataset</th>
                    <th>Active</th>
                    <th>Solves</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody id="adminProblemsTable">
                <?php foreach ($problems as $problem): ?>
                    <tr data-problem-id="<?= (int) $problem['id'] ?>">
                        <td><?= e($problem['title']) ?></td>
                        <td><?= e((string) ($problem['subject_name'] ?? 'SQL')) ?></td>
                        <td>
                            <span class="badge <?= $problem['difficulty'] === 'easy' ? 'badge-success' : ($problem['difficulty'] === 'medium' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= e(ucfirst($problem['difficulty'])) ?>
                            </span>
                        </td>
                        <td><?= e($problem['category']) ?></td>
                        <td><?= e((string) ($problem['datasets'] ?: '-')) ?></td>
                        <td><?= (int) $problem['is_active'] === 1 ? 'Yes' : 'No' ?></td>
                        <td><?= (int) $problem['solves'] ?></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-ghost js-edit-problem" type="button" data-problem-id="<?= (int) $problem['id'] ?>">Edit</button>
                                <button class="btn-ghost js-toggle-problem" type="button" data-problem-id="<?= (int) $problem['id'] ?>">Toggle Active</button>
                                <button class="btn-ghost js-delete-problem" type="button" data-problem-id="<?= (int) $problem['id'] ?>">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$problems): ?>
                    <tr><td colspan="8">No problems found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="admin-modal" id="problemModal" hidden>
        <div class="admin-modal-card">
            <div class="page-header">
                <div>
                    <h2>Problem Form</h2>
                    <p class="page-subtitle">Create or edit problem details.</p>
                </div>
                <button class="btn-ghost" type="button" id="closeProblemModal">Close</button>
            </div>
            <div id="problemFormContainer"></div>
        </div>
    </div>

    <script>
        window.SQLAB_ADMIN_PROBLEMS = {
            endpoints: {
                form: <?= json_encode(app_url('admin/problem_form.php'), JSON_THROW_ON_ERROR) ?>,
                save: <?= json_encode(app_url('api/admin/save_problem.php'), JSON_THROW_ON_ERROR) ?>,
                delete: <?= json_encode(app_url('api/admin/delete_problem.php'), JSON_THROW_ON_ERROR) ?>,
                toggle: <?= json_encode(app_url('api/admin/toggle_problem.php'), JSON_THROW_ON_ERROR) ?>,
                executeAdmin: <?= json_encode(app_url('api/execute_admin.php'), JSON_THROW_ON_ERROR) ?>
            }
        };
    </script>
    <script src="<?= e(app_url('assets/js/admin_problems.js')) ?>" defer></script>
    <?php
});
