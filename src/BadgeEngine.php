<?php

declare(strict_types=1);

final class BadgeEngine
{
    public static function awardIfMissing(int $userId, string $badgeName): ?string
    {
        $pdo = DB::getConnection();
        $badgeStmt = $pdo->prepare('SELECT id FROM badges WHERE name = :name LIMIT 1');
        $badgeStmt->execute(['name' => $badgeName]);
        $badgeId = $badgeStmt->fetchColumn();

        if (!$badgeId) {
            return null;
        }

        $check = $pdo->prepare('SELECT id FROM user_badges WHERE user_id = :user_id AND badge_id = :badge_id LIMIT 1');
        $check->execute(['user_id' => $userId, 'badge_id' => $badgeId]);

        if ($check->fetch()) {
            return null;
        }

        $insert = $pdo->prepare('INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (:user_id, :badge_id, NOW())');
        $insert->execute(['user_id' => $userId, 'badge_id' => $badgeId]);

        return $badgeName;
    }

    public static function evaluateCorrectSolve(int $userId, string $difficulty, int $executionMs, int $streak): array
    {
        $earned = [];
        $pdo = DB::getConnection();

        $solvedStmt = $pdo->prepare('SELECT COUNT(*) FROM user_progress WHERE user_id = :user_id AND status = "solved"');
        $solvedStmt->execute(['user_id' => $userId]);
        $solvedCount = (int) $solvedStmt->fetchColumn();

        foreach ([
            $solvedCount >= 1 ? 'First Solve' : null,
            $solvedCount >= 10 ? '10 Solves' : null,
            $difficulty === 'hard' ? 'Hard Hitter' : null,
            $executionMs < 50 ? 'Speed Demon' : null,
            $streak >= 7 ? 'Streak Master' : null,
        ] as $badgeName) {
            if ($badgeName === null) {
                continue;
            }

            $awarded = self::awardIfMissing($userId, $badgeName);

            if ($awarded !== null) {
                $earned[] = $awarded;
            }
        }

        return $earned;
    }
}

