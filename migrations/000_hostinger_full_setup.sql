-- Hostinger-safe all-in-one setup for SQLAB
-- This file is idempotent and avoids privileged statements (no CREATE USER / GRANT / FLUSH).
-- Recommended: run on an empty database for the cleanest result.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    google_id VARCHAR(191) NULL UNIQUE,
    auth_provider ENUM('password', 'google', 'password+google') NOT NULL DEFAULT 'password',
    role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    xp INT NOT NULL DEFAULT 0,
    streak INT NOT NULL DEFAULT 0,
    last_active DATE DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS datasets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    schema_sql LONGTEXT NOT NULL,
    seed_sql LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS problems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    difficulty ENUM('easy', 'medium', 'hard') NOT NULL,
    category VARCHAR(60) NOT NULL,
    expected_query TEXT NOT NULL,
    dataset_sql LONGTEXT DEFAULT NULL,
    hint1 TEXT DEFAULT NULL,
    hint2 TEXT DEFAULT NULL,
    hint3 TEXT DEFAULT NULL,
    subject_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_problems_subject FOREIGN KEY (subject_id) REFERENCES subjects(id),
    CONSTRAINT fk_problems_created_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    submitted_query TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL,
    execution_time_ms INT NOT NULL,
    row_count INT NOT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_submissions_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    status ENUM('attempted', 'solved') NOT NULL DEFAULT 'attempted',
    hints_used TINYINT NOT NULL DEFAULT 0,
    solved_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_user_problem (user_id, problem_id),
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS problem_datasets (
    problem_id INT NOT NULL,
    dataset_id INT NOT NULL,
    PRIMARY KEY (problem_id, dataset_id),
    CONSTRAINT fk_problem_datasets_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    CONSTRAINT fk_problem_datasets_dataset FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon_svg TEXT NOT NULL,
    xp_reward INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_badge (user_id, badge_id),
    CONSTRAINT fk_user_badges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_badges_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT NOT NULL DEFAULT 0,
    resend_available_at DATETIME NOT NULL,
    last_sent_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_verifications_user (user_id),
    CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Core users / subjects
INSERT INTO users (username, email, password_hash, email_verified_at, role, xp, streak, last_active)
VALUES
('admin', 'admin@sqlab.dev', '$2y$12$ZRpCpetkL3jD9JZoLK95V.eT6W6FKSWTfkcWdrZZBClE3k3Otj7.y', NOW(), 'admin', 0, 0, CURDATE())
ON DUPLICATE KEY UPDATE
    role = 'admin',
    email_verified_at = COALESCE(email_verified_at, VALUES(email_verified_at));

INSERT INTO subjects (slug, name, description, is_active, sort_order)
VALUES
('sql', 'SQL', 'Querying, joins, and database problem solving.', 1, 1),
('python', 'Python', 'Core syntax, data structures, and coding practice.', 1, 2),
('java', 'Java', 'OOP, collections, and backend development basics.', 1, 3)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order);

-- Datasets
INSERT INTO datasets (name, description, schema_sql, seed_sql)
SELECT * FROM (
    SELECT
        'ecommerce' AS name,
        'Online retail dataset with customers, products, orders, and line items.' AS description,
        'CREATE TABLE users (
            id INT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(120) NOT NULL,
            city VARCHAR(80) NOT NULL,
            signup_date DATE NOT NULL
        );
        CREATE TABLE products (
            id INT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            category VARCHAR(60) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            stock INT NOT NULL
        );
        CREATE TABLE orders (
            id INT PRIMARY KEY,
            user_id INT NOT NULL,
            order_date DATE NOT NULL,
            status VARCHAR(40) NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        CREATE TABLE order_items (
            id INT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id),
            FOREIGN KEY (product_id) REFERENCES products(id)
        )' AS schema_sql,
        'INSERT INTO users (id, full_name, email, city, signup_date) VALUES
        (1, ''Ava Patel'', ''ava.patel@example.com'', ''Mumbai'', ''2025-01-03''),
        (2, ''Rohan Mehta'', ''rohan.mehta@example.com'', ''Delhi'', ''2025-01-08''),
        (3, ''Mia Thompson'', ''mia.thompson@example.com'', ''Bengaluru'', ''2025-01-12''),
        (4, ''Noah Garcia'', ''noah.garcia@example.com'', ''Pune'', ''2025-01-18''),
        (5, ''Emma Khan'', ''emma.khan@example.com'', ''Hyderabad'', ''2025-01-20''),
        (6, ''Liam Chen'', ''liam.chen@example.com'', ''Chennai'', ''2025-01-25'');
        INSERT INTO products (id, name, category, price, stock) VALUES
        (1, ''Wireless Mouse'', ''Electronics'', 899.00, 34),
        (2, ''Mechanical Keyboard'', ''Electronics'', 3499.00, 18),
        (3, ''Notebook Set'', ''Stationery'', 299.00, 90),
        (4, ''Desk Lamp'', ''Home'', 1499.00, 26),
        (5, ''Water Bottle'', ''Lifestyle'', 499.00, 52),
        (6, ''USB-C Hub'', ''Electronics'', 2199.00, 20);
        INSERT INTO orders (id, user_id, order_date, status, total_amount) VALUES
        (1, 1, ''2025-02-01'', ''delivered'', 1398.00),
        (2, 2, ''2025-02-03'', ''delivered'', 3499.00),
        (3, 3, ''2025-02-04'', ''shipped'', 1998.00),
        (4, 1, ''2025-02-07'', ''processing'', 2199.00),
        (5, 4, ''2025-02-09'', ''delivered'', 798.00),
        (6, 5, ''2025-02-12'', ''cancelled'', 1499.00);
        INSERT INTO order_items (id, order_id, product_id, quantity, unit_price) VALUES
        (1, 1, 1, 1, 899.00),
        (2, 1, 3, 1, 299.00),
        (3, 1, 5, 1, 200.00),
        (4, 2, 2, 1, 3499.00),
        (5, 3, 4, 1, 1499.00),
        (6, 3, 5, 1, 499.00),
        (7, 4, 6, 1, 2199.00),
        (8, 5, 3, 1, 299.00),
        (9, 5, 5, 1, 499.00),
        (10, 6, 4, 1, 1499.00)' AS seed_sql
) x
WHERE NOT EXISTS (SELECT 1 FROM datasets d WHERE d.name = 'ecommerce');

INSERT INTO datasets (name, description, schema_sql, seed_sql)
SELECT * FROM (
    SELECT
        'university' AS name,
        'Academic records dataset with students, courses, enrollments, and grades.' AS description,
        'CREATE TABLE students (
            id INT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            major VARCHAR(80) NOT NULL,
            year_level INT NOT NULL
        );
        CREATE TABLE courses (
            id INT PRIMARY KEY,
            course_code VARCHAR(20) NOT NULL,
            title VARCHAR(120) NOT NULL,
            department VARCHAR(80) NOT NULL,
            credits INT NOT NULL
        );
        CREATE TABLE enrollments (
            id INT PRIMARY KEY,
            student_id INT NOT NULL,
            course_id INT NOT NULL,
            semester VARCHAR(20) NOT NULL,
            FOREIGN KEY (student_id) REFERENCES students(id),
            FOREIGN KEY (course_id) REFERENCES courses(id)
        );
        CREATE TABLE grades (
            id INT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            letter_grade VARCHAR(2) NOT NULL,
            FOREIGN KEY (enrollment_id) REFERENCES enrollments(id)
        )' AS schema_sql,
        'INSERT INTO students (id, full_name, major, year_level) VALUES
        (1, ''Arjun Rao'', ''Computer Science'', 2),
        (2, ''Sophia Lee'', ''Biology'', 3),
        (3, ''Daniel Kim'', ''Mathematics'', 1),
        (4, ''Priya Nair'', ''Economics'', 4),
        (5, ''Olivia Brown'', ''Computer Science'', 3);
        INSERT INTO courses (id, course_code, title, department, credits) VALUES
        (1, ''CS101'', ''Intro to Programming'', ''Computer Science'', 4),
        (2, ''BIO220'', ''Genetics'', ''Biology'', 3),
        (3, ''MTH210'', ''Linear Algebra'', ''Mathematics'', 4),
        (4, ''ECO310'', ''Game Theory'', ''Economics'', 3),
        (5, ''CS250'', ''Databases'', ''Computer Science'', 4);
        INSERT INTO enrollments (id, student_id, course_id, semester) VALUES
        (1, 1, 1, ''Spring 2025''),
        (2, 1, 5, ''Spring 2025''),
        (3, 2, 2, ''Spring 2025''),
        (4, 3, 3, ''Spring 2025''),
        (5, 4, 4, ''Spring 2025''),
        (6, 5, 1, ''Spring 2025''),
        (7, 5, 5, ''Spring 2025'');
        INSERT INTO grades (id, enrollment_id, score, letter_grade) VALUES
        (1, 1, 88.50, ''A''),
        (2, 2, 91.00, ''A''),
        (3, 3, 84.00, ''B''),
        (4, 4, 79.50, ''B''),
        (5, 5, 94.00, ''A''),
        (6, 6, 86.00, ''B''),
        (7, 7, 93.50, ''A'')' AS seed_sql
) x
WHERE NOT EXISTS (SELECT 1 FROM datasets d WHERE d.name = 'university');

INSERT INTO datasets (name, description, schema_sql, seed_sql)
SELECT * FROM (
    SELECT
        'hospital' AS name,
        'Clinical operations dataset with patients, doctors, appointments, and prescriptions.' AS description,
        'CREATE TABLE patients (
            id INT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            age INT NOT NULL,
            city VARCHAR(80) NOT NULL
        );
        CREATE TABLE doctors (
            id INT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            specialty VARCHAR(80) NOT NULL,
            years_experience INT NOT NULL
        );
        CREATE TABLE appointments (
            id INT PRIMARY KEY,
            patient_id INT NOT NULL,
            doctor_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            status VARCHAR(30) NOT NULL,
            FOREIGN KEY (patient_id) REFERENCES patients(id),
            FOREIGN KEY (doctor_id) REFERENCES doctors(id)
        );
        CREATE TABLE prescriptions (
            id INT PRIMARY KEY,
            appointment_id INT NOT NULL,
            medication VARCHAR(100) NOT NULL,
            dosage VARCHAR(60) NOT NULL,
            duration_days INT NOT NULL,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id)
        )' AS schema_sql,
        'INSERT INTO patients (id, full_name, age, city) VALUES
        (1, ''Ishaan Verma'', 29, ''Mumbai''),
        (2, ''Charlotte Hall'', 41, ''Delhi''),
        (3, ''Kabir Singh'', 35, ''Pune''),
        (4, ''Ethan White'', 52, ''Chennai''),
        (5, ''Aisha Ali'', 24, ''Bengaluru'');
        INSERT INTO doctors (id, full_name, specialty, years_experience) VALUES
        (1, ''Dr. Neha Kapoor'', ''Cardiology'', 12),
        (2, ''Dr. Ryan Scott'', ''Dermatology'', 8),
        (3, ''Dr. Fatima Noor'', ''Pediatrics'', 10),
        (4, ''Dr. Lucas Reed'', ''Orthopedics'', 15),
        (5, ''Dr. Meera Joshi'', ''General Medicine'', 9);
        INSERT INTO appointments (id, patient_id, doctor_id, appointment_date, status) VALUES
        (1, 1, 5, ''2025-03-01'', ''completed''),
        (2, 2, 1, ''2025-03-02'', ''completed''),
        (3, 3, 4, ''2025-03-03'', ''scheduled''),
        (4, 4, 1, ''2025-03-04'', ''completed''),
        (5, 5, 2, ''2025-03-05'', ''completed''),
        (6, 1, 3, ''2025-03-06'', ''cancelled'');
        INSERT INTO prescriptions (id, appointment_id, medication, dosage, duration_days) VALUES
        (1, 1, ''Paracetamol'', ''500mg'', 5),
        (2, 2, ''Atorvastatin'', ''10mg'', 30),
        (3, 4, ''Aspirin'', ''75mg'', 14),
        (4, 5, ''Cetirizine'', ''10mg'', 7),
        (5, 2, ''Vitamin D'', ''1000 IU'', 30)' AS seed_sql
) x
WHERE NOT EXISTS (SELECT 1 FROM datasets d WHERE d.name = 'hospital');

-- Badges
INSERT INTO badges (name, description, icon_svg, xp_reward)
SELECT * FROM (
    SELECT 'First Solve' AS name, 'Complete your first SQL problem.' AS description,
           '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M8 12l2.5 2.5L16 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>' AS icon_svg,
           20 AS xp_reward
) x WHERE NOT EXISTS (SELECT 1 FROM badges b WHERE b.name = 'First Solve');

INSERT INTO badges (name, description, icon_svg, xp_reward)
SELECT * FROM (
    SELECT '10 Solves', 'Solve ten SQL problems.',
           '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="4" width="14" height="16" rx="3" stroke="currentColor" stroke-width="2"/><path d="M9 9h6M9 13h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
           50
) x WHERE NOT EXISTS (SELECT 1 FROM badges b WHERE b.name = '10 Solves');

INSERT INTO badges (name, description, icon_svg, xp_reward)
SELECT * FROM (
    SELECT 'Streak Master', 'Maintain a seven day practice streak.',
           '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l2.4 4.86L20 8.6l-4 3.9.94 5.5L12 15.8 7.06 18l.94-5.5-4-3.9 5.6-.74L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
           40
) x WHERE NOT EXISTS (SELECT 1 FROM badges b WHERE b.name = 'Streak Master');

INSERT INTO badges (name, description, icon_svg, xp_reward)
SELECT * FROM (
    SELECT 'Speed Demon', 'Submit a correct query in 150ms or less.',
           '<svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/></svg>',
           30
) x WHERE NOT EXISTS (SELECT 1 FROM badges b WHERE b.name = 'Speed Demon');

INSERT INTO badges (name, description, icon_svg, xp_reward)
SELECT * FROM (
    SELECT 'Hard Hitter', 'Solve a hard SQL challenge.',
           '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z" stroke="currentColor" stroke-width="2"/><path d="M9.5 12.5l1.7 1.7 3.3-4.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
           35
) x WHERE NOT EXISTS (SELECT 1 FROM badges b WHERE b.name = 'Hard Hitter');

-- SQL problems
INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'List all ecommerce customers',
    'Return every ecommerce customer with their city and signup date ordered by signup date.',
    'easy', 'SELECT Basics',
    'SELECT full_name, city, signup_date FROM users ORDER BY signup_date',
    '',
    'Start from the users table.',
    'Select only the requested columns.',
    'Sort by signup_date ascending.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'List all ecommerce customers');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Delivered order totals',
    'Show each delivered order id with its total amount from the ecommerce dataset.',
    'easy', 'Filtering',
    'SELECT id, total_amount FROM orders WHERE status = ''delivered'' ORDER BY id',
    '', 'Orders with status delivered only.', 'Pick id and total_amount.', 'Use ORDER BY id.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Delivered order totals');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'High value products',
    'Return ecommerce products priced above 1000 ordered from highest price to lowest.',
    'easy', 'Filtering',
    'SELECT name, price FROM products WHERE price > 1000 ORDER BY price DESC',
    '', 'Use the products table.', 'Filter price > 1000.', 'Sort descending by price.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'High value products');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Customer order counts',
    'Count how many orders each ecommerce customer has placed. Include customers with zero orders and sort by order count descending, then name.',
    'medium', 'Joins',
    'SELECT u.full_name, COUNT(o.id) AS order_count FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id, u.full_name ORDER BY order_count DESC, u.full_name',
    '', 'LEFT JOIN keeps customers without orders.', 'Group by customer.', 'Count order ids.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Customer order counts');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Best selling categories',
    'Calculate total quantity sold per product category from the ecommerce dataset.',
    'medium', 'Aggregations',
    'SELECT p.category, SUM(oi.quantity) AS total_quantity FROM order_items oi INNER JOIN products p ON p.id = oi.product_id GROUP BY p.category ORDER BY total_quantity DESC, p.category',
    '', 'Join order_items to products.', 'Aggregate quantity.', 'Group by category.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Best selling categories');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Computer science achievers',
    'List university students majoring in Computer Science who scored at least 90 in any course.',
    'medium', 'Joins',
    'SELECT DISTINCT s.full_name FROM students s INNER JOIN enrollments e ON e.student_id = s.id INNER JOIN grades g ON g.enrollment_id = e.id WHERE s.major = ''Computer Science'' AND g.score >= 90 ORDER BY s.full_name',
    '', 'You need students, enrollments, and grades.', 'Filter by major and score.', 'DISTINCT avoids duplicate names.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Computer science achievers');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Average score by department',
    'Return each university department with the average grade score rounded to two decimals.',
    'medium', 'Aggregations',
    'SELECT c.department, ROUND(AVG(g.score), 2) AS avg_score FROM courses c INNER JOIN enrollments e ON e.course_id = c.id INNER JOIN grades g ON g.enrollment_id = e.id GROUP BY c.department ORDER BY avg_score DESC, c.department',
    '', 'Start from courses.', 'Join through enrollments to grades.', 'Use ROUND(AVG(...), 2).',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Average score by department');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Doctors with completed visits',
    'Show each hospital doctor and the number of completed appointments they handled.',
    'hard', 'Joins',
    'SELECT d.full_name, COUNT(a.id) AS completed_visits FROM doctors d LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status = ''completed'' GROUP BY d.id, d.full_name ORDER BY completed_visits DESC, d.full_name',
    '', 'Filter completed visits in the JOIN condition.', 'LEFT JOIN keeps doctors with zero completed visits.', 'Count appointment ids.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Doctors with completed visits');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Patients with multiple prescriptions',
    'Find hospital patients who received more than one prescription across all appointments.',
    'hard', 'Aggregations',
    'SELECT p.full_name, COUNT(pr.id) AS prescription_count FROM patients p INNER JOIN appointments a ON a.patient_id = p.id INNER JOIN prescriptions pr ON pr.appointment_id = a.id GROUP BY p.id, p.full_name HAVING COUNT(pr.id) > 1 ORDER BY prescription_count DESC, p.full_name',
    '', 'Join patients to appointments to prescriptions.', 'Group by patient.', 'Use HAVING for counts greater than one.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Patients with multiple prescriptions');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT
    'Top spending customers',
    'Find ecommerce customers whose delivered orders total more than 1000, ordered by total spend descending.',
    'hard', 'Aggregations',
    'SELECT u.full_name, SUM(o.total_amount) AS total_spend FROM users u INNER JOIN orders o ON o.user_id = u.id WHERE o.status = ''delivered'' GROUP BY u.id, u.full_name HAVING SUM(o.total_amount) > 1000 ORDER BY total_spend DESC, u.full_name',
    '', 'Join users to orders.', 'Filter to delivered orders before aggregation.', 'Use HAVING for the total greater than 1000.',
    1,
    (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
    (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Top spending customers');

-- Python and Java starter problems
INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT 'Python: Sum of Two Numbers', 'Read two integers from standard input (space-separated) and print their sum.', 'easy', 'Basics', '7', NULL,
       'Use input().split() and map(int, ...).', 'Store numbers in a and b, then print(a + b).', 'Output should be a single integer.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       (SELECT id FROM subjects WHERE slug = 'python' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Python: Sum of Two Numbers');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT 'Python: Count Vowels', 'Given the string "GenzLAB", print the count of vowels (a, e, i, o, u).', 'easy', 'Strings', '2', NULL,
       'Convert characters to lowercase before checking.', 'Loop through each character and increment count for vowels.', 'Vowels in GenzLAB are e and A.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       (SELECT id FROM subjects WHERE slug = 'python' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Python: Count Vowels');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT 'Python: Fibonacci Nth', 'For input N = 7, print the 7th Fibonacci number using 0-indexing (0, 1, 1, 2, 3, ...).', 'medium', 'Loops', '13', NULL,
       'Use iterative approach with two variables.', 'Update pair: a, b = b, a + b.', 'For 0-indexing, F(7) = 13.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       (SELECT id FROM subjects WHERE slug = 'python' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Python: Fibonacci Nth');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT 'Java: Sum of Two Numbers', 'Write a program that prints the sum of 10 and 25.', 'easy', 'Basics', '35', NULL,
       'Use int a = 10 and int b = 25.', 'Print with System.out.println(a + b).', 'Expected output is one line: 35', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       (SELECT id FROM subjects WHERE slug = 'java' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Java: Sum of Two Numbers');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT 'Java: Reverse a String', 'Reverse the string "genz" and print the result.', 'easy', 'Strings', 'zneg', NULL,
       'Use StringBuilder.', 'new StringBuilder(str).reverse().toString()', 'Print only the reversed text.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       (SELECT id FROM subjects WHERE slug = 'java' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Java: Reverse a String');

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
SELECT 'Java: Prime Check', 'Check if 29 is prime and print "PRIME" if true else "NOT PRIME".', 'medium', 'Math', 'PRIME', NULL,
       'Try dividing from 2 to sqrt(n).', 'If any divisor exists, number is not prime.', '29 has no divisors other than 1 and 29.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1),
       (SELECT id FROM subjects WHERE slug = 'java' LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM problems WHERE title = 'Java: Prime Check');

-- Problem-dataset mappings
INSERT INTO problem_datasets (problem_id, dataset_id)
SELECT p.id, d.id FROM problems p JOIN datasets d ON d.name = 'ecommerce'
WHERE p.title IN (
    'List all ecommerce customers', 'Delivered order totals', 'High value products',
    'Customer order counts', 'Best selling categories', 'Top spending customers'
)
AND NOT EXISTS (
    SELECT 1 FROM problem_datasets pd WHERE pd.problem_id = p.id AND pd.dataset_id = d.id
);

INSERT INTO problem_datasets (problem_id, dataset_id)
SELECT p.id, d.id FROM problems p JOIN datasets d ON d.name = 'university'
WHERE p.title IN ('Computer science achievers', 'Average score by department')
AND NOT EXISTS (
    SELECT 1 FROM problem_datasets pd WHERE pd.problem_id = p.id AND pd.dataset_id = d.id
);

INSERT INTO problem_datasets (problem_id, dataset_id)
SELECT p.id, d.id FROM problems p JOIN datasets d ON d.name = 'hospital'
WHERE p.title IN ('Doctors with completed visits', 'Patients with multiple prescriptions')
AND NOT EXISTS (
    SELECT 1 FROM problem_datasets pd WHERE pd.problem_id = p.id AND pd.dataset_id = d.id
);

-- Quiz seeds: base section quizzes
INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz1', CONCAT(s.name, ' Quiz 1'), 'Starter checkpoint quiz.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.section = 'quiz1' AND q.title = CONCAT(s.name, ' Quiz 1')
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz2', CONCAT(s.name, ' Quiz 2'), 'Intermediate checkpoint quiz.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.section = 'quiz2' AND q.title = CONCAT(s.name, ' Quiz 2')
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'endterm', CONCAT(s.name, ' Endterm'), 'Endterm challenge quiz.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.section = 'endterm' AND q.title = CONCAT(s.name, ' Endterm')
);

-- Extra sample quiz sets
INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz1', CONCAT(s.name, ' Quiz 1 - Set B'), 'Additional Quiz 1 sample.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.title = CONCAT(s.name, ' Quiz 1 - Set B')
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz2', CONCAT(s.name, ' Quiz 2 - Set B'), 'Additional Quiz 2 sample.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.title = CONCAT(s.name, ' Quiz 2 - Set B')
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'endterm', CONCAT(s.name, ' Endterm - Set B'), 'Additional Endterm sample.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q WHERE q.subject_id = s.id AND q.title = CONCAT(s.name, ' Endterm - Set B')
);

-- 5 test quizzes per section per subject
INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz1', CONCAT(s.name, ' Quiz 1 - Test ', nums.n), 'Test quiz seed.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
CROSS JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) nums
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q
    WHERE q.subject_id = s.id AND q.section = 'quiz1' AND q.title = CONCAT(s.name, ' Quiz 1 - Test ', nums.n)
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'quiz2', CONCAT(s.name, ' Quiz 2 - Test ', nums.n), 'Test quiz seed.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
CROSS JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) nums
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q
    WHERE q.subject_id = s.id AND q.section = 'quiz2' AND q.title = CONCAT(s.name, ' Quiz 2 - Test ', nums.n)
);

INSERT INTO quizzes (subject_id, section, title, description, is_active, created_by)
SELECT s.id, 'endterm', CONCAT(s.name, ' Endterm - Test ', nums.n), 'Test quiz seed.', 1,
       (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1)
FROM subjects s
CROSS JOIN (SELECT 1 AS n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) nums
WHERE NOT EXISTS (
    SELECT 1 FROM quizzes q
    WHERE q.subject_id = s.id AND q.section = 'endterm' AND q.title = CONCAT(s.name, ' Endterm - Test ', nums.n)
);

-- Quiz questions for base section quizzes
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
       'A', 1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'quiz1'
  AND q.title = CONCAT(s.name, ' Quiz 1')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 1);

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
       'B', 2
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'quiz1'
  AND q.title = CONCAT(s.name, ' Quiz 1')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 2);

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
       'C', 1
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'quiz2'
  AND q.title = CONCAT(s.name, ' Quiz 2')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 1);

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
  AND q.title = CONCAT(s.name, ' Endterm')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 1);

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
       'C', 2
FROM quizzes q
INNER JOIN subjects s ON s.id = q.subject_id
WHERE q.section = 'endterm'
  AND q.title = CONCAT(s.name, ' Endterm')
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id AND qq.sort_order = 2);

-- Quiz questions for Set B quizzes
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

-- Questions for generated test quizzes
INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order)
SELECT
    q.id,
    CASE q.section
        WHEN 'quiz1' THEN 'Test Q1: Pick option A.'
        WHEN 'quiz2' THEN 'Test Q2: Pick option A.'
        ELSE 'Test Endterm: Pick option A.'
    END,
    'Option A', 'Option B', 'Option C', 'Option D', 'A', 1
FROM quizzes q
WHERE q.title LIKE '% - Test %'
  AND NOT EXISTS (SELECT 1 FROM quiz_questions qq WHERE qq.quiz_id = q.id);
