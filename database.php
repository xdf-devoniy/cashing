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
        runMigrations($db);

        if ($isNew) {
            seedDefaultAccounts($db);
            seedDefaultCategories($db);
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

    $db->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        color TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        type TEXT NOT NULL CHECK(type IN ("expense", "deposit", "transfer")),
        description TEXT,
        transaction_date TEXT NOT NULL,
        category_id INTEGER,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(account_id) REFERENCES accounts(id) ON DELETE CASCADE,
        FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL
    )');
}

function runMigrations(PDO $db): void
{
    ensureCategoryColumnExists($db);
}

function ensureCategoryColumnExists(PDO $db): void
{
    $columns = $db->query('PRAGMA table_info(transactions)')->fetchAll();
    $columnNames = array_map(static fn(array $column) => $column['name'], $columns);

    if (!in_array('category_id', $columnNames, true)) {
        $db->exec('ALTER TABLE transactions ADD COLUMN category_id INTEGER');
    }

    // Backfill old free-text categories into a managed "General" category.
    seedDefaultCategories($db);
    $generalId = (int)$db->query('SELECT id FROM categories WHERE name = "General" LIMIT 1')->fetchColumn();

    if ($generalId > 0) {
        $db->exec(sprintf('UPDATE transactions SET category_id = %d WHERE category_id IS NULL', $generalId));
    }
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

function seedDefaultCategories(PDO $db): void
{
    $defaults = [
        ['General', '#0f172a'],
        ['Food & Groceries', '#0284c7'],
        ['Transport', '#7c3aed'],
    ];

    $stmt = $db->prepare('INSERT OR IGNORE INTO categories (name, color) VALUES (:name, :color)');
    foreach ($defaults as [$name, $color]) {
        $stmt->execute([
            ':name' => $name,
            ':color' => $color,
        ]);
    }
}
