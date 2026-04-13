<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();
$leaders = User::leaderboard();

render_app_layout('Leaderboard', $user, static function () use ($leaders): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Leaderboard</h1>
            <p class="page-subtitle">XP first, then solves, then speed.</p>
        </div>
    </section>
    <section class="card">
        <div class="table-shell">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>XP</th>
                    <th>Solved</th>
                    <th>Streak</th>
                    <th>Avg Time</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($leaders as $index => $leader): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($leader['username']) ?></td>
                        <td><?= (int) $leader['xp'] ?></td>
                        <td><?= (int) $leader['solved_count'] ?></td>
                        <td><?= (int) $leader['streak'] ?></td>
                        <td><?= (int) ($leader['avg_execution_time_ms'] ?? 0) ?> ms</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
});
