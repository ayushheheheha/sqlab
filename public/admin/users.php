<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$users = User::all();

render_app_layout('Admin Users', $user, static function () use ($users): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Manage Users</h1>
            <p class="page-subtitle">Track student progress and admin access.</p>
        </div>
    </section>
    <section class="card">
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>XP</th>
                    <th>Streak</th>
                    <th>Last Active</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td><?= e($row['username']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= e($row['role']) ?></td>
                        <td><?= (int) $row['xp'] ?></td>
                        <td><?= (int) $row['streak'] ?></td>
                        <td><?= e((string) ($row['last_active'] ?? 'Never')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
});
