<?php
require __DIR__ . '/includes/bootstrap.php';
$pdo = DB::getConnection();
$exists = (bool) $pdo->query("SHOW TABLES LIKE 'rate_limits'")->fetchColumn();
echo $exists ? "rate_limits_exists\n" : "rate_limits_missing\n";
