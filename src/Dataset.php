<?php

declare(strict_types=1);

final class Dataset
{
    public static function all(): array
    {
        return DB::getConnection()->query('SELECT * FROM datasets ORDER BY name')->fetchAll();
    }

    public static function allWithStats(): array
    {
        $datasets = DB::getConnection()->query(
            'SELECT
                d.*,
                COUNT(DISTINCT pd.problem_id) AS problems_count
             FROM datasets d
             LEFT JOIN problem_datasets pd ON pd.dataset_id = d.id
             GROUP BY d.id
             ORDER BY d.created_at DESC'
        )->fetchAll();

        foreach ($datasets as &$dataset) {
            $dataset['tables_count'] = self::extractTablesCount((string) $dataset['schema_sql']);
        }
        unset($dataset);

        return $datasets;
    }

    public static function find(int $datasetId): ?array
    {
        $stmt = DB::getConnection()->prepare('SELECT * FROM datasets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $datasetId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function save(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => trim((string) ($data['description'] ?? '')),
            'schema_sql' => trim((string) ($data['schema_sql'] ?? '')),
            'seed_sql' => trim((string) ($data['seed_sql'] ?? '')),
        ];

        if ($id > 0) {
            $stmt = DB::getConnection()->prepare(
                'UPDATE datasets
                 SET name = :name, description = :description, schema_sql = :schema_sql, seed_sql = :seed_sql
                 WHERE id = :id'
            );
            $stmt->execute($payload + ['id' => $id]);

            return $id;
        }

        $stmt = DB::getConnection()->prepare(
            'INSERT INTO datasets (name, description, schema_sql, seed_sql)
             VALUES (:name, :description, :schema_sql, :seed_sql)'
        );
        $stmt->execute($payload);

        return (int) DB::getConnection()->lastInsertId();
    }

    public static function delete(int $datasetId): void
    {
        $stmt = DB::getConnection()->prepare('DELETE FROM datasets WHERE id = :id');
        $stmt->execute(['id' => $datasetId]);
    }

    private static function extractTablesCount(string $schemaSql): int
    {
        if ($schemaSql === '') {
            return 0;
        }

        preg_match_all('/\bCREATE\s+TABLE\b/i', $schemaSql, $matches);

        return count($matches[0] ?? []);
    }
}
