<?php

declare(strict_types=1);

final class User
{
    public static function findById(int $id): ?array
    {
        $stmt = DB::getConnection()->prepare('SELECT id, username, email, role, xp, streak, last_active, created_at, email_verified_at, google_id, auth_provider FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function all(): array
    {
        return DB::getConnection()->query(
            'SELECT id, username, email, role, xp, streak, last_active, created_at
             FROM users
             ORDER BY created_at DESC'
        )->fetchAll();
    }

    public static function allWithAdminStats(): array
    {
        return DB::getConnection()->query(
            'SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                u.xp,
                u.streak,
                u.last_active,
                u.created_at,
                COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.problem_id END) AS solved_count
             FROM users u
             LEFT JOIN user_progress up ON up.user_id = u.id
             GROUP BY u.id
             ORDER BY u.created_at DESC'
        )->fetchAll();
    }

    public static function leaderboard(): array
    {
        return DB::getConnection()->query(
            'SELECT
                u.id,
                u.username,
                u.xp,
                u.streak,
                COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.problem_id END) AS solved_count,
                AVG(CASE WHEN s.is_correct = 1 THEN s.execution_time_ms END) AS avg_execution_time_ms
             FROM users u
             LEFT JOIN user_progress up ON up.user_id = u.id
             LEFT JOIN submissions s ON s.user_id = u.id
             WHERE u.role = "student"
             GROUP BY u.id
             ORDER BY u.xp DESC, solved_count DESC, avg_execution_time_ms ASC'
        )->fetchAll();
    }

    public static function stats(int $userId): array
    {
        try {
            $stmt = DB::getConnection()->prepare(
                'SELECT
                    COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.problem_id END) AS solved_count,
                    COUNT(DISTINCT CASE WHEN up.status = "attempted" THEN up.problem_id END) AS attempted_count,
                    COUNT(DISTINCT ub.badge_id) AS badge_count
                 FROM users u
                 LEFT JOIN user_progress up ON up.user_id = u.id
                 LEFT JOIN user_badges ub ON ub.user_id = u.id
                 WHERE u.id = :user_id
                 GROUP BY u.id'
            );
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetch() ?: [
                'solved_count' => 0,
                'attempted_count' => 0,
                'badge_count' => 0,
            ];
        } catch (Throwable $throwable) {
            error_log('[sqlab] user stats failed: ' . $throwable->getMessage());
            return [
                'solved_count' => 0,
                'attempted_count' => 0,
                'badge_count' => 0,
            ];
        }
    }

    public static function badges(int $userId): array
    {
        $stmt = DB::getConnection()->prepare(
            'SELECT b.*, ub.earned_at
             FROM user_badges ub
             INNER JOIN badges b ON b.id = ub.badge_id
             WHERE ub.user_id = :user_id
             ORDER BY ub.earned_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll();
    }

    public static function allBadgesForUser(int $userId): array
    {
        try {
            $stmt = DB::getConnection()->prepare(
                'SELECT b.*, ub.earned_at
                 FROM badges b
                 LEFT JOIN user_badges ub ON ub.badge_id = b.id AND ub.user_id = :user_id
                 ORDER BY b.id'
            );
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll();
        } catch (Throwable $throwable) {
            error_log('[sqlab] allBadgesForUser failed: ' . $throwable->getMessage());
            return [];
        }
    }

    public static function touchActivity(int $userId): void
    {
        $pdo = DB::getConnection();
        $stmt = $pdo->prepare('SELECT last_active, streak FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return;
        }

        $today = new DateTimeImmutable('today');
        $lastActive = !empty($user['last_active']) ? new DateTimeImmutable($user['last_active']) : null;
        $streak = (int) ($user['streak'] ?? 0);

        if ($lastActive?->format('Y-m-d') === $today->format('Y-m-d')) {
            return;
        }

        if ($lastActive && $lastActive->modify('+1 day')->format('Y-m-d') === $today->format('Y-m-d')) {
            $streak++;
        } else {
            $streak = 1;
        }

        $update = $pdo->prepare('UPDATE users SET last_active = CURDATE(), streak = :streak WHERE id = :id');
        $update->execute(['streak' => $streak, 'id' => $userId]);

        if ($streak >= 7) {
            self::awardBadge($userId, 'Streak Master');
        }
    }

    public static function rewardSolve(int $userId, int $problemId, int $executionTimeMs): void
    {
        $pdo = DB::getConnection();
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM user_progress WHERE user_id = :user_id AND status = "solved"');
        $countStmt->execute(['user_id' => $userId]);
        $solvedCount = (int) $countStmt->fetchColumn();

        $difficultyStmt = $pdo->prepare('SELECT difficulty FROM problems WHERE id = :id LIMIT 1');
        $difficultyStmt->execute(['id' => $problemId]);
        $difficulty = (string) $difficultyStmt->fetchColumn();

        $correctAttemptsStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM submissions
             WHERE user_id = :user_id AND problem_id = :problem_id AND is_correct = 1'
        );
        $correctAttemptsStmt->execute(['user_id' => $userId, 'problem_id' => $problemId]);
        $correctAttempts = (int) $correctAttemptsStmt->fetchColumn();

        if ($correctAttempts === 1) {
            $xpByDifficulty = ['easy' => 20, 'medium' => 40, 'hard' => 70];
            $updateXp = $pdo->prepare('UPDATE users SET xp = xp + :xp WHERE id = :id');
            $updateXp->execute(['xp' => $xpByDifficulty[$difficulty] ?? 20, 'id' => $userId]);
        }

        if ($solvedCount >= 1) {
            self::awardBadge($userId, 'First Solve');
        }

        if ($solvedCount >= 10) {
            self::awardBadge($userId, '10 Solves');
        }

        if ($executionTimeMs <= 150) {
            self::awardBadge($userId, 'Speed Demon');
        }

        if ($difficulty === 'hard') {
            self::awardBadge($userId, 'Hard Hitter');
        }
    }

    public static function awardBadge(int $userId, string $badgeName): void
    {
        $pdo = DB::getConnection();
        $badgeStmt = $pdo->prepare('SELECT id, xp_reward FROM badges WHERE name = :name LIMIT 1');
        $badgeStmt->execute(['name' => $badgeName]);
        $badge = $badgeStmt->fetch();

        if (!$badge) {
            return;
        }

        $check = $pdo->prepare('SELECT id FROM user_badges WHERE user_id = :user_id AND badge_id = :badge_id LIMIT 1');
        $check->execute(['user_id' => $userId, 'badge_id' => $badge['id']]);

        if ($check->fetch()) {
            return;
        }

        $pdo->beginTransaction();

        try {
            $insert = $pdo->prepare('INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (:user_id, :badge_id, NOW())');
            $insert->execute(['user_id' => $userId, 'badge_id' => $badge['id']]);

            if ((int) $badge['xp_reward'] > 0) {
                $xp = $pdo->prepare('UPDATE users SET xp = xp + :xp_reward WHERE id = :id');
                $xp->execute(['xp_reward' => (int) $badge['xp_reward'], 'id' => $userId]);
            }

            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public static function toggleRole(int $userId): void
    {
        $stmt = DB::getConnection()->prepare(
            'UPDATE users
             SET role = CASE WHEN role = "admin" THEN "student" ELSE "admin" END
             WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    public static function resetPassword(int $userId): string
    {
        $tempPassword = self::generateTempPassword();
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = DB::getConnection()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $userId]);

        return $tempPassword;
    }

    public static function deleteById(int $userId): void
    {
        $stmt = DB::getConnection()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    private static function generateTempPassword(): string
    {
        return 'Tmp' . bin2hex(random_bytes(4)) . '!';
    }
}
