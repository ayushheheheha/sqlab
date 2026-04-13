<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/layout_app.php';

Auth::requireAdmin();
$user = Auth::getCurrentUser();
$stats = AdminPanel::dashboardStats();
$recentSubmissions = AdminPanel::recentSubmissions(20);
$problemRates = AdminPanel::problemSolveRates();

render_app_layout('Admin', $user, static function () use ($stats, $recentSubmissions, $problemRates): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Admin Dashboard</h1>
            <p class="page-subtitle">Manage content, users, and platform activity.</p>
        </div>
    </section>
    <section class="grid grid-3">
        <article class="card"><p class="stat-label">Total Users</p><div class="stat-value"><?= (int) $stats['total_users'] ?></div></article>
        <article class="card"><p class="stat-label">Active Today</p><div class="stat-value"><?= (int) $stats['active_today'] ?></div></article>
        <article class="card"><p class="stat-label">Total Problems</p><div class="stat-value"><?= (int) $stats['total_problems'] ?></div></article>
    </section>
    <section class="grid grid-2" style="margin-top:16px;">
        <article class="card"><p class="stat-label">Total Submissions</p><div class="stat-value"><?= (int) $stats['total_submissions'] ?></div></article>
        <article class="card"><p class="stat-label">Correct Submissions %</p><div class="stat-value"><?= e((string) $stats['correct_pct']) ?>%</div></article>
        <article class="card"><p class="stat-label">Avg Execution Time (ms)</p><div class="stat-value"><?= e((string) $stats['avg_execution_ms']) ?></div></article>
    </section>
    <section class="card" style="margin-top:16px;">
        <h2 style="margin-bottom:14px;">Recent Submissions</h2>
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>User</th>
                    <th>Problem</th>
                    <th>Query</th>
                    <th>Result</th>
                    <th>Time</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentSubmissions as $row): ?>
                    <tr>
                        <td><?= e($row['username']) ?></td>
                        <td><?= e($row['problem_title']) ?></td>
                        <td><code><?= e(mb_strimwidth((string) $row['submitted_query'], 0, 60, '...')) ?></code></td>
                        <td>
                            <span class="badge <?= (int) $row['is_correct'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                <?= (int) $row['is_correct'] === 1 ? 'Correct' : 'Wrong' ?>
                            </span>
                        </td>
                        <td><?= (int) $row['execution_time_ms'] ?> ms</td>
                        <td><?= e($row['submitted_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recentSubmissions): ?>
                    <tr><td colspan="6">No submissions yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="card" style="margin-top:16px;">
        <h2 style="margin-bottom:14px;">Problems Solve Rate</h2>
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>Problem</th>
                    <th>Attempts</th>
                    <th>Solves</th>
                    <th>Solve Rate %</th>
                    <th>Avg Time (ms)</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($problemRates as $row): ?>
                    <tr>
                        <td><?= e($row['title']) ?></td>
                        <td><?= (int) $row['attempts'] ?></td>
                        <td><?= (int) $row['solves'] ?></td>
                        <td><?= e((string) $row['solve_rate']) ?>%</td>
                        <td><?= e((string) $row['avg_time_ms']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$problemRates): ?>
                    <tr><td colspan="5">No problem stats yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
});
