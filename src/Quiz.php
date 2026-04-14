<?php

declare(strict_types=1);

final class Quiz
{
    private const SECTIONS = [
        'quiz1' => 'Quiz 1',
        'quiz2' => 'Quiz 2',
        'endterm' => 'Endterm',
    ];

    private static ?bool $ready = null;

    public static function isReady(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        try {
            $stmt = DB::getConnection()->query("SHOW TABLES LIKE 'quizzes'");
            self::$ready = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            self::$ready = false;
        }

        return self::$ready;
    }

    public static function sections(): array
    {
        return self::SECTIONS;
    }

    public static function sectionLabel(string $section): string
    {
        $section = self::normalizeSection($section);

        return self::SECTIONS[$section];
    }

    public static function normalizeSection(string $section): string
    {
        $section = strtolower(trim($section));

        return array_key_exists($section, self::SECTIONS) ? $section : 'quiz1';
    }

    public static function allForSubjectGrouped(int $subjectId): array
    {
        $grouped = [
            'quiz1' => [],
            'quiz2' => [],
            'endterm' => [],
        ];

        if (!self::isReady() || $subjectId <= 0) {
            return $grouped;
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT q.*, COUNT(qq.id) AS question_count
             FROM quizzes q
             LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
             WHERE q.subject_id = :subject_id AND q.is_active = 1
             GROUP BY q.id
             ORDER BY FIELD(q.section, "quiz1", "quiz2", "endterm"), q.id'
        );
        $stmt->execute(['subject_id' => $subjectId]);

        foreach ($stmt->fetchAll() as $row) {
            $section = self::normalizeSection((string) ($row['section'] ?? 'quiz1'));
            $grouped[$section][] = $row;
        }

        return $grouped;
    }

    public static function bestAttemptsForUserSubject(int $userId, int $subjectId): array
    {
        if (!self::isReady() || $subjectId <= 0) {
            return [];
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT
                qa.quiz_id,
                MAX(qa.score) AS best_score,
                MAX(qa.total_questions) AS total_questions,
                MAX(qa.percentage) AS best_percentage,
                MAX(qa.submitted_at) AS last_submitted_at
             FROM quiz_attempts qa
             INNER JOIN quizzes q ON q.id = qa.quiz_id
             WHERE qa.user_id = :user_id AND q.subject_id = :subject_id
             GROUP BY qa.quiz_id'
        );
        $stmt->execute([
            'user_id' => $userId,
            'subject_id' => $subjectId,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(int) $row['quiz_id']] = $row;
        }

        return $rows;
    }

    public static function allForAdmin(?int $subjectId = null, ?string $section = null): array
    {
        if (!self::isReady()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($subjectId !== null && $subjectId > 0) {
            $where[] = 'q.subject_id = :subject_id';
            $params['subject_id'] = $subjectId;
        }

        if ($section !== null && $section !== '') {
            $where[] = 'q.section = :section';
            $params['section'] = self::normalizeSection($section);
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT q.*, s.name AS subject_name, COUNT(qq.id) AS question_count
             FROM quizzes q
             INNER JOIN subjects s ON s.id = q.subject_id
             LEFT JOIN quiz_questions qq ON qq.quiz_id = q.id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY q.id
             ORDER BY s.sort_order, FIELD(q.section, "quiz1", "quiz2", "endterm"), q.id'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function findWithSubject(int $quizId): ?array
    {
        if (!self::isReady() || $quizId <= 0) {
            return null;
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT q.*, s.slug AS subject_slug, s.name AS subject_name
             FROM quizzes q
             INNER JOIN subjects s ON s.id = q.subject_id
             WHERE q.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $quizId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function questions(int $quizId): array
    {
        if (!self::isReady() || $quizId <= 0) {
            return [];
        }

        $stmt = DB::getConnection()->prepare(
            'SELECT *
             FROM quiz_questions
             WHERE quiz_id = :quiz_id
             ORDER BY sort_order, id'
        );
        $stmt->execute(['quiz_id' => $quizId]);

        return $stmt->fetchAll();
    }

    public static function save(array $data, int $adminId): int
    {
        if (!self::isReady()) {
            throw new RuntimeException('Quiz tables are not ready. Run migrations/006_quizathon.sql first.');
        }

        $id = (int) ($data['id'] ?? 0);
        $subjectId = (int) ($data['subject_id'] ?? 0);
        $section = self::normalizeSection((string) ($data['section'] ?? 'quiz1'));
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $isActive = empty($data['is_active']) ? 0 : 1;
        $questions = self::parseQuestions((string) ($data['questions_raw'] ?? ''));

        if ($subjectId <= 0 || $title === '') {
            throw new RuntimeException('Subject and title are required.');
        }

        if (count($questions) === 0) {
            throw new RuntimeException('At least one valid question is required.');
        }

        $pdo = DB::getConnection();
        $pdo->beginTransaction();

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE quizzes SET
                        subject_id = :subject_id,
                        section = :section,
                        title = :title,
                        description = :description,
                        is_active = :is_active
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $id,
                    'subject_id' => $subjectId,
                    'section' => $section,
                    'title' => $title,
                    'description' => $description,
                    'is_active' => $isActive,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
                     VALUES (:subject_id, :section, :title, :description, :is_active, :created_by)'
                );
                $stmt->execute([
                    'subject_id' => $subjectId,
                    'section' => $section,
                    'title' => $title,
                    'description' => $description,
                    'is_active' => $isActive,
                    'created_by' => $adminId,
                ]);
                $id = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('DELETE FROM quiz_questions WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $id]);

            $insert = $pdo->prepare(
                'INSERT INTO quiz_questions
                    (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
                 VALUES
                    (:quiz_id, :question_text, :option_a, :option_b, :option_c, :option_d, :correct_option, :sort_order)'
            );

            foreach ($questions as $index => $question) {
                $insert->execute([
                    'quiz_id' => $id,
                    'question_text' => $question['question_text'],
                    'option_a' => $question['option_a'],
                    'option_b' => $question['option_b'],
                    'option_c' => $question['option_c'],
                    'option_d' => $question['option_d'],
                    'correct_option' => $question['correct_option'],
                    'sort_order' => $index + 1,
                ]);
            }

            $pdo->commit();

            return $id;
        } catch (Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public static function questionsToText(array $questions): string
    {
        $lines = [];

        foreach ($questions as $row) {
            $lines[] = implode(' || ', [
                trim((string) ($row['question_text'] ?? '')),
                trim((string) ($row['option_a'] ?? '')),
                trim((string) ($row['option_b'] ?? '')),
                trim((string) ($row['option_c'] ?? '')),
                trim((string) ($row['option_d'] ?? '')),
                strtoupper(trim((string) ($row['correct_option'] ?? 'A'))),
            ]);
        }

        return implode("\n", $lines);
    }

    public static function evaluate(int $quizId, int $userId, array $answers): array
    {
        if (!self::isReady()) {
            throw new RuntimeException('Quiz tables are not ready.');
        }

        $questions = self::questions($quizId);

        if (!$questions) {
            throw new RuntimeException('Quiz has no questions yet.');
        }

        $score = 0;
        $review = [];

        foreach ($questions as $question) {
            $questionId = (int) $question['id'];
            $selected = strtoupper(trim((string) ($answers[$questionId] ?? '')));
            $correct = strtoupper(trim((string) $question['correct_option']));
            $isCorrect = in_array($selected, ['A', 'B', 'C', 'D'], true) && $selected === $correct;

            if ($isCorrect) {
                $score++;
            }

            $review[] = [
                'id' => $questionId,
                'question_text' => $question['question_text'],
                'selected' => $selected,
                'correct' => $correct,
                'is_correct' => $isCorrect,
            ];
        }

        $total = count($questions);
        $percentage = $total > 0 ? round(($score * 100) / $total, 2) : 0.0;

        $stmt = DB::getConnection()->prepare(
            'INSERT INTO quiz_attempts (user_id, quiz_id, score, total_questions, percentage)
             VALUES (:user_id, :quiz_id, :score, :total_questions, :percentage)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'quiz_id' => $quizId,
            'score' => $score,
            'total_questions' => $total,
            'percentage' => $percentage,
        ]);

        return [
            'score' => $score,
            'total' => $total,
            'percentage' => $percentage,
            'review' => $review,
        ];
    }

    private static function parseQuestions(string $raw): array
    {
        $rows = preg_split('/\r?\n/', $raw) ?: [];
        $parsed = [];

        foreach ($rows as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('||', $line));
            if (count($parts) !== 6) {
                continue;
            }

            [$questionText, $a, $b, $c, $d, $correct] = $parts;
            $correct = strtoupper($correct);

            if ($questionText === '' || $a === '' || $b === '' || $c === '' || $d === '') {
                continue;
            }

            if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
                continue;
            }

            $parsed[] = [
                'question_text' => $questionText,
                'option_a' => $a,
                'option_b' => $b,
                'option_c' => $c,
                'option_d' => $d,
                'correct_option' => $correct,
            ];
        }

        return $parsed;
    }
}
