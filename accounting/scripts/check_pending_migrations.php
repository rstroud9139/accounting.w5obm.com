<?php
require __DIR__ . '/../../include/dbconn.php';
require __DIR__ . '/../lib/helpers.php';

try {
    $db = accounting_db_connection();
} catch (Throwable $e) {
    fwrite(STDERR, "Unable to connect: {$e->getMessage()}\n");
    exit(1);
}

$exists = $db->query("SHOW TABLES LIKE 'acc_migrations'");
if (!$exists || $exists->num_rows === 0) {
    echo "acc_migrations table missing\n";
    exit(0);
}

$files = [];
foreach (glob(dirname(__DIR__) . '/sql/*.sql') as $file) {
    $files[basename($file)] = sha1_file($file);
}

$applied = [];
$result = $db->query('SELECT filename, checksum, applied_at FROM acc_migrations');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applied[$row['filename']] = $row;
    }
    $result->close();
}

$pending = [];
foreach ($files as $name => $checksum) {
    if (!isset($applied[$name])) {
        $pending[] = $name;
    }
}

if (empty($pending)) {
    echo "No pending migrations.\n";
} else {
    echo "Pending migrations:\n";
    foreach ($pending as $name) {
        echo " - {$name}\n";
    }
}
