<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout_app.php';

Auth::requireLogin();
$user = Auth::getCurrentUser();

render_app_layout('Leaderboard', $user, static function () use ($user): void {
    ?>
    <section class="page-header">
        <div>
            <h1>Leaderboard</h1>
            <p class="page-subtitle">Top solvers this week and all time.</p>
        </div>
    </section>
    <section class="audience-toggle leaderboard-toggle" id="leaderboardToggle">
        <button class="btn-ghost active" type="button" data-period="alltime">All Time</button>
        <button class="btn-ghost" type="button" data-period="week">This Week</button>
        <button class="btn-ghost" type="button" data-period="month">This Month</button>
    </section>
    <section class="leaderboard-podium" id="leaderboardPodium"></section>
    <section class="card">
        <div class="table-shell leaderboard-table-shell">
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Username</th>
                        <th>Problems Solved</th>
                        <th>XP</th>
                        <th>Streak</th>
                        <th>Badges Count</th>
                    </tr>
                </thead>
                <tbody id="leaderboardRows">
                    <tr><td colspan="6">Loading leaderboard...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
    <script>
        window.SQLAB_LEADERBOARD = {
            endpoints: {
                leaderboard: <?= json_encode(app_url('api/leaderboard.php'), JSON_THROW_ON_ERROR) ?>
            },
            currentUserId: <?= (int) $user['id'] ?>
        };
    </script>
    <script src="<?= e(app_url('assets/js/leaderboard.js')) ?>" defer></script>
    <?php
});
