<?php

declare(strict_types=1);

final class Problem
{
    private static ?bool $hasSubjectColumn = null;

    public static function allActive(?int $subjectId = null): array
    {
        $params = [];
        $where = 'p.is_active = 1';
        $subjectReady = self::supportsSubjects();

        $subjectSelect = $subjectReady
            ? ', s.slug AS subject_slug, s.name AS subject_name'
            : ', "sql" AS subject_slug, "SQL" AS subject_name';
        $subjectJoin = $subjectReady ? ' LEFT JOIN subjects s ON s.id = p.subject_id ' : '';

        if ($subjectReady && $subjectId !== null && $subjectId > 0) {
            $where .= ' AND p.subject_id = :subject_id';
            $params['subject_id'] = $subjectId;
        }

        $sql = 'SELECT p.*' . $subjectSelect . ',
                       GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ", ") AS datasets
                FROM problems p
                ' . $subjectJoin . '
                LEFT JOIN problem_datasets pd ON pd.problem_id = p.id
                LEFT JOIN datasets d ON d.id = pd.dataset_id
                WHERE ' . $where . '
                GROUP BY p.id
                ORDER BY FIELD(p.difficulty, "easy", "medium", "hard"), p.id';

        $stmt = DB::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function allWithMeta(): array
    {
        $subjectReady = self::supportsSubjects();
        $subjectSelect = $subjectReady
            ? ', s.name AS subject_name, s.slug AS subject_slug'
            : ', "SQL" AS subject_name, "sql" AS subject_slug';
        $subjectJoin = $subjectReady ? ' LEFT JOIN subjects s ON s.id = p.subject_id ' : '';

        $sql = 'SELECT p.*, u.username AS author' . $subjectSelect . ',
                       GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ", ") AS datasets,
                       COUNT(DISTINCT CASE WHEN up.status = "solved" THEN up.user_id END) AS solves
                FROM problems p
                LEFT JOIN users u ON u.id = p.created_by
                ' . $subjectJoin . '
                LEFT JOIN problem_datasets pd ON pd.problem_id = p.id
                LEFT JOIN datasets d ON d.id = pd.dataset_id
                LEFT JOIN user_progress up ON up.problem_id = p.id
                GROUP BY p.id
                ORDER BY p.created_at DESC';

        return DB::getConnection()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $subjectReady = self::supportsSubjects();
        $subjectSelect = $subjectReady
            ? ', s.name AS subject_name, s.slug AS subject_slug'
            : ', "SQL" AS subject_name, "sql" AS subject_slug';
        $subjectJoin = $subjectReady ? ' LEFT JOIN subjects s ON s.id = p.subject_id ' : '';

        $stmt = DB::getConnection()->prepare(
            'SELECT p.*' . $subjectSelect . ',
                    GROUP_CONCAT(d.name ORDER BY d.name SEPARATOR ", ") AS datasets
             FROM problems p
             ' . $subjectJoin . '
             LEFT JOIN problem_datasets pd ON pd.problem_id = p.id
             LEFT JOIN datasets d ON d.id = pd.dataset_id
             WHERE p.id = :id
             GROUP BY p.id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $problem = $stmt->fetch();

        return $problem ?: null;
    }

    public static function findWithDatasets(int $id): ?array
    {
        $problem = self::find($id);

        if (!$problem) {
            return null;
        }

        $datasetStmt = DB::getConnection()->prepare(
            'SELECT d.* FROM datasets d
             INNER JOIN problem_datasets pd ON pd.dataset_id = d.id
             WHERE pd.problem_id = :problem_id
             ORDER BY d.name'
        );
        $datasetStmt->execute(['problem_id' => $id]);
        $problem['dataset_records'] = $datasetStmt->fetchAll();

        return $problem;
    }

    public static function create(array $data): int
    {
        $pdo = DB::getConnection();
        $pdo->beginTransaction();
        $subjectReady = self::supportsSubjects();

        try {
            if ($subjectReady) {
                $stmt = $pdo->prepare(
                    'INSERT INTO problems
                    (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
                    VALUES
                    (:title, :description, :difficulty, :category, :expected_query, :dataset_sql, :hint1, :hint2, :hint3, :is_active, :created_by, :subject_id)'
                );
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO problems
                    (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by)
                    VALUES
                    (:title, :description, :difficulty, :category, :expected_query, :dataset_sql, :hint1, :hint2, :hint3, :is_active, :created_by)'
                );
            }

            $params = [
                'title' => trim((string) $data['title']),
                'description' => trim((string) $data['description']),
                'difficulty' => $data['difficulty'],
                'category' => trim((string) $data['category']),
                'expected_query' => trim((string) $data['expected_query']),
                'dataset_sql' => trim((string) ($data['dataset_sql'] ?? '')),
                'hint1' => trim((string) ($data['hint1'] ?? '')),
                'hint2' => trim((string) ($data['hint2'] ?? '')),
                'hint3' => trim((string) ($data['hint3'] ?? '')),
                'is_active' => empty($data['is_active']) ? 0 : 1,
                'created_by' => (int) $data['created_by'],
            ];

            if ($subjectReady) {
                $params['subject_id'] = max(1, (int) ($data['subject_id'] ?? 1));
            }

            $stmt->execute($params);

            $problemId = (int) $pdo->lastInsertId();
            self::syncProblemDataset($pdo, $problemId, (int) ($data['dataset_id'] ?? 0));
            $pdo->commit();
            return $problemId;
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public static function update(int $id, array $data): void
    {
        $pdo = DB::getConnection();
        $pdo->beginTransaction();
        $subjectReady = self::supportsSubjects();

        try {
            if ($subjectReady) {
                $stmt = $pdo->prepare(
                    'UPDATE problems SET
                        title = :title,
                        description = :description,
                        difficulty = :difficulty,
                        category = :category,
                        expected_query = :expected_query,
                        dataset_sql = :dataset_sql,
                        subject_id = :subject_id,
                        hint1 = :hint1,
                        hint2 = :hint2,
                        hint3 = :hint3,
                        is_active = :is_active
                     WHERE id = :id'
                );
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE problems SET
                        title = :title,
                        description = :description,
                        difficulty = :difficulty,
                        category = :category,
                        expected_query = :expected_query,
                        dataset_sql = :dataset_sql,
                        hint1 = :hint1,
                        hint2 = :hint2,
                        hint3 = :hint3,
                        is_active = :is_active
                     WHERE id = :id'
                );
            }

            $params = [
                'id' => $id,
                'title' => trim((string) $data['title']),
                'description' => trim((string) $data['description']),
                'difficulty' => $data['difficulty'],
                'category' => trim((string) $data['category']),
                'expected_query' => trim((string) $data['expected_query']),
                'dataset_sql' => trim((string) ($data['dataset_sql'] ?? '')),
                'hint1' => trim((string) ($data['hint1'] ?? '')),
                'hint2' => trim((string) ($data['hint2'] ?? '')),
                'hint3' => trim((string) ($data['hint3'] ?? '')),
                'is_active' => empty($data['is_active']) ? 0 : 1,
            ];

            if ($subjectReady) {
                $params['subject_id'] = max(1, (int) ($data['subject_id'] ?? 1));
            }

            $stmt->execute($params);

            self::syncProblemDataset($pdo, $id, (int) ($data['dataset_id'] ?? 0));
            $pdo->commit();
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $throwable;
        }
    }

    public static function categories(?int $subjectId = null): array
    {
        $sql = 'SELECT DISTINCT category FROM problems WHERE category <> ""';
        $params = [];

        if (self::supportsSubjects() && $subjectId !== null && $subjectId > 0) {
            $sql .= ' AND subject_id = :subject_id';
            $params['subject_id'] = $subjectId;
        }

        $sql .= ' ORDER BY category';

        $stmt = DB::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function toggleActive(int $problemId): void
    {
        $stmt = DB::getConnection()->prepare('UPDATE problems SET is_active = IF(is_active = 1, 0, 1) WHERE id = :id');
        $stmt->execute(['id' => $problemId]);
    }

    public static function softDelete(int $problemId): void
    {
        $stmt = DB::getConnection()->prepare('UPDATE problems SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $problemId]);
    }

    public static function hardDelete(int $problemId): void
    {
        $stmt = DB::getConnection()->prepare('DELETE FROM problems WHERE id = :id');
        $stmt->execute(['id' => $problemId]);
    }

    private static function syncProblemDataset(PDO $pdo, int $problemId, int $datasetId): void
    {
        $pdo->prepare('DELETE FROM problem_datasets WHERE problem_id = :problem_id')->execute(['problem_id' => $problemId]);

        if ($datasetId <= 0) {
            return;
        }

        $pdo->prepare('INSERT INTO problem_datasets (problem_id, dataset_id) VALUES (:problem_id, :dataset_id)')
            ->execute(['problem_id' => $problemId, 'dataset_id' => $datasetId]);
    }

    private static function supportsSubjects(): bool
    {
        if (!Subject::isReady()) {
            return false;
        }

        if (self::$hasSubjectColumn !== null) {
            return self::$hasSubjectColumn;
        }

        try {
            $stmt = DB::getConnection()->query("SHOW COLUMNS FROM problems LIKE 'subject_id'");
            self::$hasSubjectColumn = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$hasSubjectColumn = false;
        }

        return self::$hasSubjectColumn;
    }
}
