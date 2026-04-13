<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$users = User::allWithAdminStats();

render_app_layout('Admin Users', $user, static function () use ($users): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Manage Users</h1>
            <p class="page-subtitle">Track student progress and admin access.</p>
        </div>
    </section>
    <section class="card" style="margin-bottom:16px;">
        <div class="form-group" style="margin:0;">
            <label for="adminUserSearch">Search</label>
            <input id="adminUserSearch" type="text" placeholder="Filter by username or email">
        </div>
    </section>
    <section class="card">
        <div class="table-shell">
            <table id="adminUsersTable">
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>XP</th>
                    <th>Solved</th>
                    <th>Joined</th>
                    <th>Last Active</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr data-search="<?= e(strtolower($row['username'] . ' ' . $row['email'])) ?>" data-user-id="<?= (int) $row['id'] ?>">
                        <td class="admin-user-username"><?= e($row['username']) ?></td>
                        <td class="admin-user-email"><?= e($row['email']) ?></td>
                        <td><?= e($row['role']) ?></td>
                        <td><?= (int) $row['xp'] ?></td>
                        <td><?= (int) $row['solved_count'] ?></td>
                        <td><?= e((string) $row['created_at']) ?></td>
                        <td><?= e((string) ($row['last_active'] ?? 'Never')) ?></td>
                        <td>
                            <div class="table-actions">
                                <button class="btn-ghost js-toggle-role" type="button" data-user-id="<?= (int) $row['id'] ?>">Toggle Role</button>
                                <button class="btn-ghost js-reset-password" type="button" data-user-id="<?= (int) $row['id'] ?>">Reset Password</button>
                                <button class="btn-ghost js-delete-user" type="button" data-user-id="<?= (int) $row['id'] ?>">Delete User</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <div class="admin-modal" id="adminTempPasswordModal" hidden>
        <div class="admin-modal-card">
            <div class="page-header">
                <div>
                    <h2>Temporary Password</h2>
                    <p class="page-subtitle">Share this once with the user.</p>
                </div>
                <button type="button" class="btn-ghost" id="closeTempPasswordModal">Close</button>
            </div>
            <p id="tempPasswordValue" class="temp-password"></p>
        </div>
    </div>
    <script>
        window.SQLAB_ADMIN_USERS = {
            endpoints: {
                toggleRole: <?= json_encode(app_url('api/admin/toggle_role.php'), JSON_THROW_ON_ERROR) ?>,
                resetPassword: <?= json_encode(app_url('api/admin/reset_password.php'), JSON_THROW_ON_ERROR) ?>,
                deleteUser: <?= json_encode(app_url('api/admin/delete_user.php'), JSON_THROW_ON_ERROR) ?>
            }
        };
    </script>
    <script src="<?= e(app_url('assets/js/admin_users.js')) ?>" defer></script>
    <?php
});
