CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

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

ALTER TABLE problems
    ADD COLUMN subject_id INT NULL AFTER hint3;

UPDATE problems
SET subject_id = (SELECT id FROM subjects WHERE slug = 'sql' LIMIT 1)
WHERE subject_id IS NULL;

ALTER TABLE problems
    MODIFY COLUMN subject_id INT NOT NULL,
    ADD CONSTRAINT fk_problems_subject FOREIGN KEY (subject_id) REFERENCES subjects(id);
