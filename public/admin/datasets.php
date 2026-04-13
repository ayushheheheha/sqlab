<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$datasets = Dataset::allWithStats();

render_app_layout('Admin Datasets', $user, static function () use ($datasets): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Manage Datasets</h1>
            <p class="page-subtitle">Create and maintain schema/seed sources for problems.</p>
        </div>
        <button class="btn-primary" type="button" id="openDatasetCreate">Add Dataset</button>
    </section>
    <section class="card">
        <div class="table-shell">
            <table id="adminDatasetsTable">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Tables Count</th>
                    <th>Problems Count</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($datasets as $dataset): ?>
                    <tr
                        data-dataset-id="<?= (int) $dataset['id'] ?>"
                        data-schema="<?= e($dataset['schema_sql']) ?>"
                        data-name="<?= e($dataset['name']) ?>"
                        data-description="<?= e($dataset['description']) ?>"
                        data-seed="<?= e($dataset['seed_sql']) ?>"
                    >
                        <td><?= e($dataset['name']) ?></td>
                        <td><?= (int) $dataset['tables_count'] ?></td>
                        <td><?= (int) $dataset['problems_count'] ?></td>
                        <td><?= e($dataset['created_at']) ?></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-ghost js-view-schema" type="button">View Schema</button>
                                <button class="btn-ghost js-edit-dataset" type="button">Edit</button>
                                <button class="btn-ghost js-delete-dataset" type="button">Delete</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$datasets): ?>
                    <tr><td colspan="5">No datasets found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-top:16px;">
        <h2 id="datasetFormTitle" style="margin-bottom:12px;">Add Dataset</h2>
        <form id="datasetForm" class="admin-form">
            <?= csrf_input() ?>
            <input type="hidden" name="id" id="datasetId" value="0">
            <div id="datasetFormFlash"></div>
            <div class="form-group">
                <label for="datasetName">Name</label>
                <input id="datasetName" name="name" type="text" required>
            </div>
            <div class="form-group">
                <label for="datasetDescription">Description</label>
                <textarea id="datasetDescription" name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="datasetSchemaSql">Schema SQL</label>
                <textarea id="datasetSchemaSql" name="schema_sql" rows="10" required></textarea>
            </div>
            <div class="form-group">
                <label for="datasetSeedSql">Seed SQL</label>
                <textarea id="datasetSeedSql" name="seed_sql" rows="8" required></textarea>
            </div>
            <div class="table-actions">
                <button class="btn-primary" type="submit">Save Dataset</button>
                <button class="btn-ghost" type="button" id="resetDatasetForm">Reset</button>
            </div>
        </form>
    </section>

    <div class="admin-modal" id="datasetSchemaModal" hidden>
        <div class="admin-modal-card">
            <div class="page-header">
                <div>
                    <h2 id="datasetSchemaTitle">Dataset Schema</h2>
                    <p class="page-subtitle">Schema SQL</p>
                </div>
                <button type="button" class="btn-ghost" id="closeDatasetSchemaModal">Close</button>
            </div>
            <pre><code id="datasetSchemaCode"></code></pre>
        </div>
    </div>

    <script>
        window.SQLAB_ADMIN_DATASETS = {
            endpoints: {
                save: <?= json_encode(app_url('api/admin/save_dataset.php'), JSON_THROW_ON_ERROR) ?>,
                delete: <?= json_encode(app_url('api/admin/delete_dataset.php'), JSON_THROW_ON_ERROR) ?>
            }
        };
    </script>
    <script src="<?= e(app_url('assets/js/admin_datasets.js')) ?>" defer></script>
    <?php
});
