-- Add extra sample quizzes so each section can show multiple quizzes per subject.
-- Idempotent inserts using title + subject checks.

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz1', CONCAT(s.name, ' Quiz 1 - Set B'), 'Additional Quiz 1 sample.', 1, admin.id
FROM subjects s
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1) admin
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.title = CONCAT(s.name, ' Quiz 1 - Set B')
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz2', CONCAT(s.name, ' Quiz 2 - Set B'), 'Additional Quiz 2 sample.', 1, admin.id
FROM subjects s
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1) admin
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.title = CONCAT(s.name, ' Quiz 2 - Set B')
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'endterm', CONCAT(s.name, ' Endterm - Set B'), 'Additional Endterm sample.', 1, admin.id
FROM subjects s
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1) admin
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.title = CONCAT(s.name, ' Endterm - Set B')
);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which statement adds a new row in SQL?'
           WHEN s.slug = 'python' THEN 'Which bracket defines a list in Python?'
           ELSE 'Which keyword creates an object in Java?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'INSERT INTO' WHEN s.slug = 'python' THEN '[]' ELSE 'new' END,
       CASE WHEN s.slug = 'sql' THEN 'UPDATE' WHEN s.slug = 'python' THEN '{}' ELSE 'class' END,
       CASE WHEN s.slug = 'sql' THEN 'ALTER' WHEN s.slug = 'python' THEN '()' ELSE 'this' END,
       CASE WHEN s.slug = 'sql' THEN 'DELETE' WHEN s.slug = 'python' THEN '<>' ELSE 'static' END,
       'A',
       1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.title = CONCAT(s.name, ' Quiz 1 - Set B')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which keyword removes duplicate rows in SELECT output?'
           WHEN s.slug = 'python' THEN 'Which function prints output to console?'
           ELSE 'Which type stores true/false in Java?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'UNIQUE' WHEN s.slug = 'python' THEN 'input()' ELSE 'int' END,
       CASE WHEN s.slug = 'sql' THEN 'DISTINCT' WHEN s.slug = 'python' THEN 'echo()' ELSE 'String' END,
       CASE WHEN s.slug = 'sql' THEN 'LIMIT' WHEN s.slug = 'python' THEN 'print()' ELSE 'boolean' END,
       CASE WHEN s.slug = 'sql' THEN 'GROUP BY' WHEN s.slug = 'python' THEN 'show()' ELSE 'char' END,
       CASE WHEN s.slug = 'sql' THEN 'B' WHEN s.slug = 'python' THEN 'C' ELSE 'C' END,
       1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.title = CONCAT(s.name, ' Quiz 2 - Set B')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which command changes table structure?'
           WHEN s.slug = 'python' THEN 'Which statement handles exceptions?'
           ELSE 'Which keyword prevents method overriding?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'ALTER TABLE' WHEN s.slug = 'python' THEN 'try/except' ELSE 'final' END,
       CASE WHEN s.slug = 'sql' THEN 'TRUNCATE' WHEN s.slug = 'python' THEN 'if/else' ELSE 'const' END,
       CASE WHEN s.slug = 'sql' THEN 'MERGE' WHEN s.slug = 'python' THEN 'while' ELSE 'static' END,
       CASE WHEN s.slug = 'sql' THEN 'GRANT' WHEN s.slug = 'python' THEN 'for' ELSE 'abstract' END,
       'A',
       1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.title = CONCAT(s.name, ' Endterm - Set B')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);
