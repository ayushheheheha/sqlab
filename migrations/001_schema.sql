CREATE TABLE users (
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
);

CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE problems (
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
);

CREATE TABLE submissions (
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
);

CREATE TABLE user_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    problem_id INT NOT NULL,
    status ENUM('attempted', 'solved') NOT NULL DEFAULT 'attempted',
    hints_used TINYINT NOT NULL DEFAULT 0,
    solved_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_user_problem (user_id, problem_id),
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
);

CREATE TABLE datasets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    schema_sql LONGTEXT NOT NULL,
    seed_sql LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE problem_datasets (
    problem_id INT NOT NULL,
    dataset_id INT NOT NULL,
    PRIMARY KEY (problem_id, dataset_id),
    CONSTRAINT fk_problem_datasets_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE,
    CONSTRAINT fk_problem_datasets_dataset FOREIGN KEY (dataset_id) REFERENCES datasets(id) ON DELETE CASCADE
);

CREATE TABLE badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon_svg TEXT NOT NULL,
    xp_reward INT NOT NULL DEFAULT 0
);

CREATE TABLE user_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_badge (user_id, badge_id),
    CONSTRAINT fk_user_badges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_badges_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
);

CREATE TABLE email_verifications (
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
);

CREATE USER IF NOT EXISTS 'sqlab_sandbox'@'localhost' IDENTIFIED BY 'SandboxPass!99';
GRANT SELECT ON sqlab_datasets.* TO 'sqlab_sandbox'@'localhost';
FLUSH PRIVILEGES;

INSERT INTO users (username, email, password_hash, email_verified_at, role, xp, streak, last_active)
VALUES
('admin', 'admin@sqlab.dev', '$2y$12$ZRpCpetkL3jD9JZoLK95V.eT6W6FKSWTfkcWdrZZBClE3k3Otj7.y', NOW(), 'admin', 0, 0, CURDATE());

INSERT INTO datasets (name, description, schema_sql, seed_sql)
VALUES
(
    'ecommerce',
    'Online retail dataset with customers, products, orders, and line items.',
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
    )',
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
    (10, 6, 4, 1, 1499.00)'
),
(
    'university',
    'Academic records dataset with students, courses, enrollments, and grades.',
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
    )',
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
    (7, 7, 93.50, ''A'')'
),
(
    'hospital',
    'Clinical operations dataset with patients, doctors, appointments, and prescriptions.',
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
    )',
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
    (5, 2, ''Vitamin D'', ''1000 IU'', 30)'
);

INSERT INTO badges (name, description, icon_svg, xp_reward)
VALUES
('First Solve', 'Complete your first SQL problem.', '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M8 12l2.5 2.5L16 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>', 20),
('10 Solves', 'Solve ten SQL problems.', '<svg viewBox="0 0 24 24" fill="none"><rect x="5" y="4" width="14" height="16" rx="3" stroke="currentColor" stroke-width="2"/><path d="M9 9h6M9 13h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>', 50),
('Streak Master', 'Maintain a seven day practice streak.', '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l2.4 4.86L20 8.6l-4 3.9.94 5.5L12 15.8 7.06 18l.94-5.5-4-3.9 5.6-.74L12 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>', 40),
('Speed Demon', 'Submit a correct query in 150ms or less.', '<svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/></svg>', 30),
('Hard Hitter', 'Solve a hard SQL challenge.', '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z" stroke="currentColor" stroke-width="2"/><path d="M9.5 12.5l1.7 1.7 3.3-4.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>', 35);

INSERT INTO subjects (slug, name, description, is_active, sort_order)
VALUES
('sql', 'SQL', 'Querying, joins, and database problem solving.', 1, 1),
('python', 'Python', 'Core syntax, data structures, and coding practice.', 1, 2),
('java', 'Java', 'OOP, collections, and backend development basics.', 1, 3);

INSERT INTO problems (title, description, difficulty, category, expected_query, dataset_sql, hint1, hint2, hint3, is_active, created_by, subject_id)
VALUES
('List all ecommerce customers', 'Return every ecommerce customer with their city and signup date ordered by signup date.', 'easy', 'SELECT Basics', 'SELECT full_name, city, signup_date FROM users ORDER BY signup_date', '', 'Start from the users table.', 'Select only the requested columns.', 'Sort by signup_date ascending.', 1, 1, 1),
('Delivered order totals', 'Show each delivered order id with its total amount from the ecommerce dataset.', 'easy', 'Filtering', 'SELECT id, total_amount FROM orders WHERE status = ''delivered'' ORDER BY id', '', 'Orders with status delivered only.', 'Pick id and total_amount.', 'Use ORDER BY id.', 1, 1, 1),
('High value products', 'Return ecommerce products priced above 1000 ordered from highest price to lowest.', 'easy', 'Filtering', 'SELECT name, price FROM products WHERE price > 1000 ORDER BY price DESC', '', 'Use the products table.', 'Filter price > 1000.', 'Sort descending by price.', 1, 1, 1),
('Customer order counts', 'Count how many orders each ecommerce customer has placed. Include customers with zero orders and sort by order count descending, then name.', 'medium', 'Joins', 'SELECT u.full_name, COUNT(o.id) AS order_count FROM users u LEFT JOIN orders o ON o.user_id = u.id GROUP BY u.id, u.full_name ORDER BY order_count DESC, u.full_name', '', 'LEFT JOIN keeps customers without orders.', 'Group by customer.', 'Count order ids.', 1, 1, 1),
('Best selling categories', 'Calculate total quantity sold per product category from the ecommerce dataset.', 'medium', 'Aggregations', 'SELECT p.category, SUM(oi.quantity) AS total_quantity FROM order_items oi INNER JOIN products p ON p.id = oi.product_id GROUP BY p.category ORDER BY total_quantity DESC, p.category', '', 'Join order_items to products.', 'Aggregate quantity.', 'Group by category.', 1, 1, 1),
('Computer science achievers', 'List university students majoring in Computer Science who scored at least 90 in any course.', 'medium', 'Joins', 'SELECT DISTINCT s.full_name FROM students s INNER JOIN enrollments e ON e.student_id = s.id INNER JOIN grades g ON g.enrollment_id = e.id WHERE s.major = ''Computer Science'' AND g.score >= 90 ORDER BY s.full_name', '', 'You need students, enrollments, and grades.', 'Filter by major and score.', 'DISTINCT avoids duplicate names.', 1, 1, 1),
('Average score by department', 'Return each university department with the average grade score rounded to two decimals.', 'medium', 'Aggregations', 'SELECT c.department, ROUND(AVG(g.score), 2) AS avg_score FROM courses c INNER JOIN enrollments e ON e.course_id = c.id INNER JOIN grades g ON g.enrollment_id = e.id GROUP BY c.department ORDER BY avg_score DESC, c.department', '', 'Start from courses.', 'Join through enrollments to grades.', 'Use ROUND(AVG(...), 2).', 1, 1, 1),
('Doctors with completed visits', 'Show each hospital doctor and the number of completed appointments they handled.', 'hard', 'Joins', 'SELECT d.full_name, COUNT(a.id) AS completed_visits FROM doctors d LEFT JOIN appointments a ON a.doctor_id = d.id AND a.status = ''completed'' GROUP BY d.id, d.full_name ORDER BY completed_visits DESC, d.full_name', '', 'Filter completed visits in the JOIN condition.', 'LEFT JOIN keeps doctors with zero completed visits.', 'Count appointment ids.', 1, 1, 1),
('Patients with multiple prescriptions', 'Find hospital patients who received more than one prescription across all appointments.', 'hard', 'Aggregations', 'SELECT p.full_name, COUNT(pr.id) AS prescription_count FROM patients p INNER JOIN appointments a ON a.patient_id = p.id INNER JOIN prescriptions pr ON pr.appointment_id = a.id GROUP BY p.id, p.full_name HAVING COUNT(pr.id) > 1 ORDER BY prescription_count DESC, p.full_name', '', 'Join patients to appointments to prescriptions.', 'Group by patient.', 'Use HAVING for counts greater than one.', 1, 1, 1),
('Top spending customers', 'Find ecommerce customers whose delivered orders total more than 1000, ordered by total spend descending.', 'hard', 'Aggregations', 'SELECT u.full_name, SUM(o.total_amount) AS total_spend FROM users u INNER JOIN orders o ON o.user_id = u.id WHERE o.status = ''delivered'' GROUP BY u.id, u.full_name HAVING SUM(o.total_amount) > 1000 ORDER BY total_spend DESC, u.full_name', '', 'Join users to orders.', 'Filter to delivered orders before aggregation.', 'Use HAVING for the total greater than 1000.', 1, 1, 1);

INSERT INTO problem_datasets (problem_id, dataset_id)
VALUES
(1, 1),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 2),
(7, 2),
(8, 3),
(9, 3),
(10, 1);
