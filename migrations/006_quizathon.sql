CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    section ENUM('quiz1', 'quiz2', 'endterm') NOT NULL,
    title VARCHAR(160) NOT NULL,
    description VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quizzes_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_quizzes_created_by FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    score INT NOT NULL,
    total_questions INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quiz_attempts_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_quiz_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz1', CONCAT(s.name, ' Quiz 1'), 'Starter checkpoint quiz.', 1, admin.id
FROM subjects s
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1) admin
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.section = 'quiz1'
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz2', CONCAT(s.name, ' Quiz 2'), 'Intermediate checkpoint quiz.', 1, admin.id
FROM subjects s
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1) admin
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.section = 'quiz2'
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'endterm', CONCAT(s.name, ' Endterm'), 'Endterm challenge quiz.', 1, admin.id
FROM subjects s
CROSS JOIN (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1) admin
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.section = 'endterm'
);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which SQL clause is used to filter rows before grouping?'
           WHEN s.slug = 'python' THEN 'Which keyword defines a function in Python?'
           ELSE 'Which keyword is used to inherit a class in Java?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'WHERE' WHEN s.slug = 'python' THEN 'def' ELSE 'extends' END,
       CASE WHEN s.slug = 'sql' THEN 'HAVING' WHEN s.slug = 'python' THEN 'func' ELSE 'inherits' END,
       CASE WHEN s.slug = 'sql' THEN 'GROUP BY' WHEN s.slug = 'python' THEN 'lambda' ELSE 'implements' END,
       CASE WHEN s.slug = 'sql' THEN 'ORDER BY' WHEN s.slug = 'python' THEN 'method' ELSE 'instanceof' END,
       'A',
       1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'quiz1'
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which aggregate function counts rows?'
           WHEN s.slug = 'python' THEN 'What does len([1,2,3]) return?'
           ELSE 'Which method is the Java program entry point?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'SUM()' WHEN s.slug = 'python' THEN '2' ELSE 'run()' END,
       CASE WHEN s.slug = 'sql' THEN 'COUNT()' WHEN s.slug = 'python' THEN '3' ELSE 'main()' END,
       CASE WHEN s.slug = 'sql' THEN 'MAX()' WHEN s.slug = 'python' THEN '4' ELSE 'start()' END,
       CASE WHEN s.slug = 'sql' THEN 'AVG()' WHEN s.slug = 'python' THEN 'Error' ELSE 'init()' END,
       'B',
       2
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'quiz1'
  AND NOT EXISTS (
      SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 2
  );

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which join returns only matching rows from both tables?'
           WHEN s.slug = 'python' THEN 'Which data type is mutable?'
           ELSE 'Which collection allows duplicate values?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'LEFT JOIN' WHEN s.slug = 'python' THEN 'tuple' ELSE 'Set' END,
       CASE WHEN s.slug = 'sql' THEN 'RIGHT JOIN' WHEN s.slug = 'python' THEN 'str' ELSE 'Map' END,
       CASE WHEN s.slug = 'sql' THEN 'INNER JOIN' WHEN s.slug = 'python' THEN 'list' ELSE 'Queue' END,
       CASE WHEN s.slug = 'sql' THEN 'FULL JOIN' WHEN s.slug = 'python' THEN 'frozenset' ELSE 'None of these' END,
       'C',
       1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'quiz2'
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which clause is used with aggregate filters?'
           WHEN s.slug = 'python' THEN 'Which keyword exits a loop immediately?'
           ELSE 'Which access modifier has widest visibility?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'HAVING' WHEN s.slug = 'python' THEN 'pass' ELSE 'private' END,
       CASE WHEN s.slug = 'sql' THEN 'WHERE' WHEN s.slug = 'python' THEN 'continue' ELSE 'protected' END,
       CASE WHEN s.slug = 'sql' THEN 'LIMIT' WHEN s.slug = 'python' THEN 'stop' ELSE 'package' END,
       CASE WHEN s.slug = 'sql' THEN 'ORDER BY' WHEN s.slug = 'python' THEN 'next' ELSE 'public' END,
       CASE WHEN s.slug = 'sql' THEN 'A' WHEN s.slug = 'python' THEN 'B' ELSE 'D' END,
       1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'endterm'
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);

INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT q.id,
       CASE
           WHEN s.slug = 'sql' THEN 'Which command removes a table and its data?'
           WHEN s.slug = 'python' THEN 'What is the output type of input() in Python 3?'
           ELSE 'Which exception is unchecked in Java?'
       END,
       CASE WHEN s.slug = 'sql' THEN 'DELETE TABLE' WHEN s.slug = 'python' THEN 'int' ELSE 'IOException' END,
       CASE WHEN s.slug = 'sql' THEN 'TRUNCATE TABLE' WHEN s.slug = 'python' THEN 'float' ELSE 'SQLException' END,
       CASE WHEN s.slug = 'sql' THEN 'DROP TABLE' WHEN s.slug = 'python' THEN 'str' ELSE 'RuntimeException' END,
       CASE WHEN s.slug = 'sql' THEN 'REMOVE TABLE' WHEN s.slug = 'python' THEN 'bool' ELSE 'ClassNotFoundException' END,
       'C',
       2
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'endterm'
  AND NOT EXISTS (
      SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 2
  );
