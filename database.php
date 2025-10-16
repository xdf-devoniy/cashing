<?php

declare(strict_types=1);

function getDatabase(): PDO
{
    static $db = null;

    if ($db === null) {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $dbPath = $dataDir . '/database.sqlite';
        $isNew = !file_exists($dbPath);

        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        initializeDatabase($db);

        if ($isNew) {
            seedDefaultAccounts($db);
        }
    }

    return $db;
}

function initializeDatabase(PDO $db): void
{
    $db->exec('CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        type TEXT NOT NULL DEFAULT "cash",
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        category TEXT NOT NULL,
        type TEXT NOT NULL CHECK(type IN ("expense", "deposit", "transfer")),
        description TEXT,
        transaction_date TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(account_id) REFERENCES accounts(id) ON DELETE CASCADE
    )');
}

function seedDefaultAccounts(PDO $db): void
{
    $defaults = [
        ['Cash Wallet', 'cash'],
        ['Main Bank', 'bank'],
        ['Hamkor Bank', 'bank'],
    ];

    $stmt = $db->prepare('INSERT OR IGNORE INTO accounts (name, type) VALUES (:name, :type)');
    foreach ($defaults as [$name, $type]) {
        $stmt->execute([
            ':name' => $name,
            ':type' => $type,
        ]);
    }
}
