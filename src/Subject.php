<?php

declare(strict_types=1);

final class Subject
{
    private static ?bool $ready = null;

    public static function isReady(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $pdo = DB::getConnection();
            $subjectsTable = (bool) $pdo->query("SHOW TABLES LIKE 'subjects'")->fetchColumn();

            if (!$subjectsTable) {
                self::$ready = false;
                return self::$ready;
            }

            $subjectIdColumnStmt = $pdo->query(
                "SHOW COLUMNS FROM problems LIKE 'subject_id'"
            );
            $subjectIdColumn = (bool) $subjectIdColumnStmt->fetchColumn();

            self::$ready = $subjectIdColumn;
        } catch (Throwable) {
            self::$ready = false;
        }

        return self::$ready;
    }

    public static function allActive(): array
    {
        if (!self::isReady()) {
            return self::fallbackSubjects();
        }

        return DB::getConnection()->query(
            'SELECT id, slug, name, description, is_active, sort_order
             FROM subjects
             WHERE is_active = 1
             ORDER BY sort_order, id'
        )->fetchAll();
    }

    public static function findBySlug(string $slug): ?array
    {
        $slug = trim(strtolower($slug));

        if ($slug === '') {
            return null;
        }

        if (!self::isReady()) {
            foreach (self::fallbackSubjects() as $subject) {
                if ($subject['slug'] === $slug) {
                    return $subject;
                }
            }

            return null;
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT id, slug, name, description, is_active, sort_order
             FROM subjects
             WHERE slug = :slug AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if (!self::isReady()) {
            foreach (self::fallbackSubjects() as $subject) {
                if ((int) $subject['id'] === $id) {
                    return $subject;
                }
            }

            return null;
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT id, slug, name, description, is_active, sort_order
             FROM subjects
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function statsForUser(int $userId): array
    {
        $subjects = self::allActive();

        if (!self::isReady()) {
            $totalSql = (int) DB::getConnection()->query('SELECT COUNT(*) FROM problems WHERE is_active = 1')->fetchColumn();
            $solvedSqlStmt = DB::getConnection()->prepare(
                'SELECT COUNT(DISTINCT up.problem_id)
                 FROM user_progress up
                 INNER JOIN problems p ON p.id = up.problem_id
                 WHERE up.user_id = :user_id AND up.status = "solved" AND p.is_active = 1'
            );
            $solvedSqlStmt->execute(['user_id' => $userId]);
            $solvedSql = (int) $solvedSqlStmt->fetchColumn();

            return array_map(static function (array $subject) use ($totalSql, $solvedSql): array {
                if ($subject['slug'] === 'sql') {
                    $subject['total_problems'] = $totalSql;
                    $subject['solved_count'] = $solvedSql;
                } else {
                    $subject['total_problems'] = 0;
                    $subject['solved_count'] = 0;
                }

                return $subject;
            }, $subjects);
        }

        try {
            $stmt = DB::getConnection()->prepare(
                'SELECT
                    s.id,
                    s.slug,
                    s.name,
                    s.description,
                    s.sort_order,
                    COUNT(DISTINCT CASE WHEN p.is_active = 1 THEN p.id END) AS total_problems,
                    COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.problem_id END) AS solved_count
                 FROM subjects s
                 LEFT JOIN problems p ON p.subject_id = s.id
                 LEFT JOIN user_progress up ON up.problem_id = p.id AND up.user_id = :user_id
                 WHERE s.is_active = 1
                 GROUP BY s.id
                 ORDER BY s.sort_order, s.id'
            );
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll();
        } catch (Throwable) {
            self::$ready = false;
            return self::statsForUser($userId);
        }
    }

    private static function fallbackSubjects(): array
    {
        return [
            [
                'id' => 1,
                'slug' => 'sql',
                'name' => 'SQL',
                'description' => 'Querying, joins, and database problem solving.',
                'is_active' => 1,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'slug' => 'python',
                'name' => 'Python',
                'description' => 'Core syntax, data structures, and coding practice.',
                'is_active' => 1,
                'sort_order' => 2,
            ],
            [
                'id' => 3,
                'slug' => 'java',
                'name' => 'Java',
                'description' => 'OOP, collections, and backend development basics.',
                'is_active' => 1,
                'sort_order' => 3,
            ],
        ];
    }
}
