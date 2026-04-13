<?php

declare(strict_types=1);

final class Submission
{
    public static function saveExecution(int $userId, int $problemId, string $query, bool $isCorrect, int $executionMs, int $rowCount): array
    {
        $pdo = DB::getConnection();
        $pdo->beginTransaction();
        $firstCorrectSolve = false;
        $xpAwarded = 0;
        $difficulty = 'easy';
        $streak = 0;

        try {
            $problemStmt = $pdo->prepare('SELECT difficulty FROM problems WHERE id = :id LIMIT 1');
            $problemStmt->execute(['id' => $problemId]);
            $difficulty = (string) ($problemStmt->fetchColumn() ?: 'easy');

            $progressStmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = :user_id AND problem_id = :problem_id LIMIT 1');
            $progressStmt->execute(['user_id' => $userId, 'problem_id' => $problemId]);
            $progress = $progressStmt->fetch();
            $wasSolved = $progress && $progress['status'] === 'solved';
            $firstCorrectSolve = $isCorrect && !$wasSolved;

            $stmt = $pdo->prepare(
                'INSERT INTO submissions (user_id, problem_id, submitted_query, is_correct, execution_time_ms, row_count)
                 VALUES (:user_id, :problem_id, :submitted_query, :is_correct, :execution_time_ms, :row_count)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'problem_id' => $problemId,
                'submitted_query' => $query,
                'is_correct' => $isCorrect ? 1 : 0,
                'execution_time_ms' => $executionMs,
                'row_count' => $rowCount,
            ]);

            if ($progress) {
                $updateProgress = $pdo->prepare(
                    'UPDATE user_progress
                     SET status = :status,
                         solved_at = CASE WHEN :is_correct = 1 AND solved_at IS NULL THEN NOW() ELSE solved_at END
                     WHERE id = :id'
                );
                $updateProgress->execute([
                    'status' => $wasSolved ? 'solved' : ($isCorrect ? 'solved' : 'attempted'),
                    'is_correct' => $isCorrect ? 1 : 0,
                    'id' => $progress['id'],
                ]);
            } else {
                $insertProgress = $pdo->prepare(
                    'INSERT INTO user_progress (user_id, problem_id, status, solved_at)
                     VALUES (:user_id, :problem_id, :status, :solved_at)'
                );
                $insertProgress->execute([
                    'user_id' => $userId,
                    'problem_id' => $problemId,
                    'status' => $isCorrect ? 'solved' : 'attempted',
                    'solved_at' => $isCorrect ? date('Y-m-d H:i:s') : null,
                ]);
            }

            if ($firstCorrectSolve) {
                $xpAwarded = ['easy' => 10, 'medium' => 20, 'hard' => 40][$difficulty] ?? 10;
            }

            if ($isCorrect) {
                $activity = self::activityUpdate($userId);
                $streak = $activity['streak'];
                $updateUser = $pdo->prepare('UPDATE users SET xp = xp + :xp, streak = :streak, last_active = CURDATE() WHERE id = :id');
                $updateUser->execute(['xp' => $xpAwarded, 'streak' => $streak, 'id' => $userId]);
            }

            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }

        $badges = $isCorrect ? BadgeEngine::evaluateCorrectSolve($userId, $difficulty, $executionMs, $streak) : [];

        return [
            'first_correct_solve' => $firstCorrectSolve,
            'xp_awarded' => $xpAwarded,
            'badges' => $badges,
        ];
    }

    public static function incrementHint(int $userId, int $problemId): void
    {
        $stmt = DB::getConnection()->prepare(
            'INSERT INTO user_progress (user_id, problem_id, status, hints_used)
             VALUES (:user_id, :problem_id, "attempted", 1)
             ON DUPLICATE KEY UPDATE hints_used = LEAST(3, hints_used + 1)'
        );
        $stmt->execute(['user_id' => $userId, 'problem_id' => $problemId]);
    }

    public static function record(int $userId, int $problemId, string $query, bool $isCorrect, int $executionTimeMs, int $rowCount, int $hintsUsed = 0): void
    {
        $pdo = DB::getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO submissions (user_id, problem_id, submitted_query, is_correct, execution_time_ms, row_count)
                 VALUES (:user_id, :problem_id, :submitted_query, :is_correct, :execution_time_ms, :row_count)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'problem_id' => $problemId,
                'submitted_query' => $query,
                'is_correct' => $isCorrect ? 1 : 0,
                'execution_time_ms' => $executionTimeMs,
                'row_count' => $rowCount,
            ]);

            $progressStmt = $pdo->prepare('SELECT * FROM user_progress WHERE user_id = :user_id AND problem_id = :problem_id LIMIT 1');
            $progressStmt->execute(['user_id' => $userId, 'problem_id' => $problemId]);
            $existing = $progressStmt->fetch();

            $status = $isCorrect ? 'solved' : 'attempted';
            $solvedAt = $isCorrect ? date('Y-m-d H:i:s') : null;

            if ($existing) {
                $update = $pdo->prepare(
                    'UPDATE user_progress
                     SET status = :status,
                         hints_used = GREATEST(hints_used, :hints_used),
                         solved_at = CASE
                             WHEN :status = "solved" AND solved_at IS NULL THEN :solved_at
                             ELSE solved_at
                         END
                     WHERE id = :id'
                );
                $update->execute([
                    'status' => $existing['status'] === 'solved' ? 'solved' : $status,
                    'hints_used' => $hintsUsed,
                    'solved_at' => $solvedAt,
                    'id' => $existing['id'],
                ]);
            } else {
                $insertProgress = $pdo->prepare(
                    'INSERT INTO user_progress (user_id, problem_id, status, hints_used, solved_at)
                     VALUES (:user_id, :problem_id, :status, :hints_used, :solved_at)'
                );
                $insertProgress->execute([
                    'user_id' => $userId,
                    'problem_id' => $problemId,
                    'status' => $status,
                    'hints_used' => $hintsUsed,
                    'solved_at' => $solvedAt,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }

        if ($isCorrect) {
            User::rewardSolve($userId, $problemId, $executionTimeMs);
        }

        User::touchActivity($userId);
    }

    public static function recentForUser(int $userId, int $limit = 10): array
    {
        $stmt = DB::getConnection()->prepare(
            'SELECT s.*, p.title, p.difficulty
             FROM submissions s
             INNER JOIN problems p ON p.id = s.problem_id
             WHERE s.user_id = :user_id
             ORDER BY s.submitted_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function recentForUserProblem(int $userId, int $problemId, int $limit = 5): array
    {
        $stmt = DB::getConnection()->prepare(
            'SELECT submitted_query, is_correct, execution_time_ms, row_count, submitted_at
             FROM submissions
             WHERE user_id = :user_id AND problem_id = :problem_id
             ORDER BY submitted_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':problem_id', $problemId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function paginatedForUser(int $userId, int $page = 1, int $perPage = 10): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $stmt = DB::getConnection()->prepare(
            'SELECT s.*, p.title, p.difficulty
             FROM submissions s
             INNER JOIN problems p ON p.id = s.problem_id
             WHERE s.user_id = :user_id
             ORDER BY s.submitted_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countForUser(int $userId): int
    {
        $stmt = DB::getConnection()->prepare('SELECT COUNT(*) FROM submissions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public static function statsForUser(int $userId): array
    {
        $stmt = DB::getConnection()->prepare(
            'SELECT
                COUNT(*) AS total_submissions,
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_submissions,
                AVG(execution_time_ms) AS avg_execution_time_ms
             FROM submissions
             WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch() ?: [
            'total_submissions' => 0,
            'correct_submissions' => 0,
            'avg_execution_time_ms' => 0,
        ];
    }

    private static function activityUpdate(int $userId): array
    {
        $stmt = DB::getConnection()->prepare('SELECT last_active, streak FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['streak' => 1];
        }

        $today = new DateTimeImmutable('today');
        $lastActive = !empty($user['last_active']) ? new DateTimeImmutable($user['last_active']) : null;
        $streak = (int) ($user['streak'] ?? 0);

        if ($lastActive?->format('Y-m-d') === $today->format('Y-m-d')) {
            return ['streak' => max(1, $streak)];
        }

        if ($lastActive && $lastActive->modify('+1 day')->format('Y-m-d') === $today->format('Y-m-d')) {
            return ['streak' => $streak + 1];
        }

        return ['streak' => 1];
    }
}
