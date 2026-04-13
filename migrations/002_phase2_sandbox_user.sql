CREATE USER IF NOT EXISTS 'sqlab_sandbox'@'localhost' IDENTIFIED BY 'SandboxPass!99';
GRANT SELECT ON sqlab_datasets.* TO 'sqlab_sandbox'@'localhost';
FLUSH PRIVILEGES;

