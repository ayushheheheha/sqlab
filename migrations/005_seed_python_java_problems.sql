-- Seed starter Python and Java problems (idempotent)
-- Prerequisite: subjects table and problems.subject_id must exist (run 004_subjects.sql first).

INSERT INTO problems (
    title,
    description,
    difficulty,
    category,
    expected_query,
    dataset_sql,
    hint1,
    hint2,
    hint3,
    is_active,
    created_by,
    subject_id
)
SELECT
    'Python: Sum of Two Numbers',
    'Read two integers from standard input (space-separated) and print their sum.',
    'easy',
    'Basics',
    '7',
    NULL,
    'Use input().split() and map(int, ...).',
    'Store numbers in a and b, then print(a + b).',
    'Output should be a single integer.',
    1,
    admin.id,
    s.id
FROM subjects s
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE s.slug = 'python'
  AND NOT EXISTS (
      SELECT 1 FROM problems WHERE title = 'Python: Sum of Two Numbers'
  );

INSERT INTO problems (
    title,
    description,
    difficulty,
    category,
    expected_query,
    dataset_sql,
    hint1,
    hint2,
    hint3,
    is_active,
    created_by,
    subject_id
)
SELECT
    'Python: Count Vowels',
    'Given the string "GenzLAB", print the count of vowels (a, e, i, o, u).',
    'easy',
    'Strings',
    '2',
    NULL,
    'Convert characters to lowercase before checking.',
    'Loop through each character and increment count for vowels.',
    'Vowels in GenzLAB are e and A.',
    1,
    admin.id,
    s.id
FROM subjects s
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE s.slug = 'python'
  AND NOT EXISTS (
      SELECT 1 FROM problems WHERE title = 'Python: Count Vowels'
  );

INSERT INTO problems (
    title,
    description,
    difficulty,
    category,
    expected_query,
    dataset_sql,
    hint1,
    hint2,
    hint3,
    is_active,
    created_by,
    subject_id
)
SELECT
    'Python: Fibonacci Nth',
    'For input N = 7, print the 7th Fibonacci number using 0-indexing (0, 1, 1, 2, 3, ...).',
    'medium',
    'Loops',
    '13',
    NULL,
    'Use iterative approach with two variables.',
    'Update pair: a, b = b, a + b.',
    'For 0-indexing, F(7) = 13.',
    1,
    admin.id,
    s.id
FROM subjects s
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE s.slug = 'python'
  AND NOT EXISTS (
      SELECT 1 FROM problems WHERE title = 'Python: Fibonacci Nth'
  );

INSERT INTO problems (
    title,
    description,
    difficulty,
    category,
    expected_query,
    dataset_sql,
    hint1,
    hint2,
    hint3,
    is_active,
    created_by,
    subject_id
)
SELECT
    'Java: Sum of Two Numbers',
    'Write a program that prints the sum of 10 and 25.',
    'easy',
    'Basics',
    '35',
    NULL,
    'Use int a = 10 and int b = 25.',
    'Print with System.out.println(a + b).',
    'Expected output is one line: 35',
    1,
    admin.id,
    s.id
FROM subjects s
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE s.slug = 'java'
  AND NOT EXISTS (
      SELECT 1 FROM problems WHERE title = 'Java: Sum of Two Numbers'
  );

INSERT INTO problems (
    title,
    description,
    difficulty,
    category,
    expected_query,
    dataset_sql,
    hint1,
    hint2,
    hint3,
    is_active,
    created_by,
    subject_id
)
SELECT
    'Java: Reverse a String',
    'Reverse the string "genz" and print the result.',
    'easy',
    'Strings',
    'zneg',
    NULL,
    'Use StringBuilder.',
    'new StringBuilder(str).reverse().toString()',
    'Print only the reversed text.',
    1,
    admin.id,
    s.id
FROM subjects s
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE s.slug = 'java'
  AND NOT EXISTS (
      SELECT 1 FROM problems WHERE title = 'Java: Reverse a String'
  );

INSERT INTO problems (
    title,
    description,
    difficulty,
    category,
    expected_query,
    dataset_sql,
    hint1,
    hint2,
    hint3,
    is_active,
    created_by,
    subject_id
)
SELECT
    'Java: Prime Check',
    'Check if 29 is prime and print "PRIME" if true else "NOT PRIME".',
    'medium',
    'Math',
    'PRIME',
    NULL,
    'Try dividing from 2 to sqrt(n).',
    'If any divisor exists, number is not prime.',
    '29 has no divisors other than 1 and 29.',
    1,
    admin.id,
    s.id
FROM subjects s
CROSS JOIN (
    SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1
) admin
WHERE s.slug = 'java'
  AND NOT EXISTS (
      SELECT 1 FROM problems WHERE title = 'Java: Prime Check'
  );
