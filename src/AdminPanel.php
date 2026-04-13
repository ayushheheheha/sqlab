<?php

declare(strict_types=1);

final class AdminPanel
{
    public static function dashboardStats(): array
    {
        $row = DB::getConnection()->query(
            'SELECT
                (SELECT COUNT(*) FROM users) AS total_users,
                (SELECT COUNT(*) FROM users WHERE last_active = CURDATE()) AS active_today,
                (SELECT COUNT(*) FROM problems) AS total_problems,
                (SELECT COUNT(*) FROM submissions) AS total_submissions,
                (SELECT ROUND(COALESCE(SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 0), 2) FROM submissions) AS correct_pct,
                (SELECT ROUND(COALESCE(AVG(execution_time_ms), 0), 2) FROM submissions) AS avg_execution_ms'
        )->fetch();

        return $row ?: [
            'total_users' => 0,
            'active_today' => 0,
            'total_problems' => 0,
            'total_submissions' => 0,
            'correct_pct' => 0,
            'avg_execution_ms' => 0,
        ];
    }

    public static function recentSubmissions(int $limit = 20): array
    {
        $stmt = DB::getConnection()->prepare(
            'SELECT
                s.submitted_query,
                s.is_correct,
                s.execution_time_ms,
                s.submitted_at,
                u.username,
                p.title AS problem_title
             FROM submissions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN problems p ON p.id = s.problem_id
             ORDER BY s.submitted_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function problemSolveRates(): array
    {
        return DB::getConnection()->query(
            'SELECT
                p.id,
                p.title,
                COUNT(s.id) AS attempts,
                COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.user_id END) AS solves,
                ROUND(COALESCE(COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.user_id END) * 100.0 / NULLIF(COUNT(s.id), 0), 0), 2) AS solve_rate,
                ROUND(COALESCE(AVG(s.execution_time_ms), 0), 2) AS avg_time_ms
             FROM problems p
             LEFT JOIN submissions s ON s.problem_id = p.id
             LEFT JOIN user_progress up ON up.problem_id = p.id
             GROUP BY p.id
             ORDER BY solve_rate ASC, attempts DESC, p.title ASC'
        )->fetchAll();
    }
}
