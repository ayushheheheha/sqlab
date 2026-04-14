-- Add 5 test quizzes per section (quiz1, quiz2, endterm) for each subject.
-- Each quiz contains exactly 1 question. Idempotent by title.

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz1', CONCAT(s.name, ' Quiz 1 - Test ', nums.n), 'Test quiz seed.', 1, admin.id
FROM subjects s
CROSS JOIN (
    SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
) nums
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE NOT EXISTS (
    SELECT 1
    FROM quizzes q
    WHERE q.subject_id = s.id
      AND q.section = 'quiz1'
      AND q.title = CONCAT(s.name, ' Quiz 1 - Test ', nums.n)
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz2', CONCAT(s.name, ' Quiz 2 - Test ', nums.n), 'Test quiz seed.', 1, admin.id
FROM subjects s
CROSS JOIN (
    SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
) nums
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE NOT EXISTS (
    SELECT 1
    FROM quizzes q
    WHERE q.subject_id = s.id
      AND q.section = 'quiz2'
      AND q.title = CONCAT(s.name, ' Quiz 2 - Test ', nums.n)
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'endterm', CONCAT(s.name, ' Endterm - Test ', nums.n), 'Test quiz seed.', 1, admin.id
FROM subjects s
CROSS JOIN (
    SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5
) nums
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE NOT EXISTS (
    SELECT 1
    FROM quizzes q
    WHERE q.subject_id = s.id
      AND q.section = 'endterm'
      AND q.title = CONCAT(s.name, ' Endterm - Test ', nums.n)
);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT
    q.id,
    CASE q.section
        WHEN 'quiz1' THEN 'Test Q1: Pick option A.'
        WHEN 'quiz2' THEN 'Test Q2: Pick option A.'
        ELSE 'Test Endterm: Pick option A.'
    END,
    'Option A',
    'Option B',
    'Option C',
    'Option D',
    'A',
    1
FROM quizzes q
WHERE q.title LIKE '% - Test %'
  AND NOT EXISTS (
      SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id
  );
