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
    migrateLegacyCategoryColumn($db);
}

function ensureCategoryColumnExists(PDO $db): void
{
    $columns = $db->query('PRAGMA table_info(transactions)')->fetchAll();
    $columnNames = array_map(static fn(array $column) => $column['name'], $columns);

    if (!in_array('category_id', $columnNames, true)) {
        $db->exec('ALTER TABLE transactions ADD COLUMN category_id INTEGER');
    }

    seedDefaultCategories($db);
    $generalId = ensureGeneralCategoryId($db);

    $stmt = $db->prepare('UPDATE transactions SET category_id = :general WHERE category_id IS NULL');
    $stmt->execute([':general' => $generalId]);
}

function migrateLegacyCategoryColumn(PDO $db): void
{
    $columns = $db->query('PRAGMA table_info(transactions)')->fetchAll();

    $hasLegacyColumn = false;
    $hasCategoryId = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'category') {
            $hasLegacyColumn = true;
        }

        if ($column['name'] === 'category_id') {
            $hasCategoryId = true;
        }
    }

    if (!$hasLegacyColumn) {
        return;
    }

    if (!$hasCategoryId) {
        $db->exec('ALTER TABLE transactions ADD COLUMN category_id INTEGER');
    }

    seedDefaultCategories($db);

    $generalId = ensureGeneralCategoryId($db);

    $legacyNames = $db->query('SELECT DISTINCT category FROM transactions WHERE category IS NOT NULL AND TRIM(category) <> ""')
        ->fetchAll(PDO::FETCH_COLUMN);

    foreach ($legacyNames as $legacyName) {
        $categoryId = ensureNamedCategory($db, (string)$legacyName);
        $stmt = $db->prepare('UPDATE transactions SET category_id = :category_id WHERE category = :legacy_name');
        $stmt->execute([
            ':category_id' => $categoryId,
            ':legacy_name' => $legacyName,
        ]);
    }

    $stmt = $db->prepare('UPDATE transactions SET category_id = :general WHERE category IS NULL OR TRIM(category) = ""');
    $stmt->execute([':general' => $generalId]);

    rebuildTransactionsTable($db);
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

function ensureGeneralCategoryId(PDO $db): int
{
    $stmt = $db->prepare('SELECT id FROM categories WHERE name = :name LIMIT 1');
    $stmt->execute([':name' => 'General']);
    $generalId = $stmt->fetchColumn();

    if ($generalId) {
        return (int)$generalId;
    }

    $stmt = $db->prepare('INSERT INTO categories (name, color) VALUES (:name, :color)');
    $stmt->execute([
        ':name' => 'General',
        ':color' => '#0f172a',
    ]);

    return (int)$db->lastInsertId();
}

function ensureNamedCategory(PDO $db, string $name): int
{
    $trimmed = trim($name);
    if ($trimmed === '') {
        return ensureGeneralCategoryId($db);
    }

    $stmt = $db->prepare('SELECT id FROM categories WHERE name = :name COLLATE NOCASE LIMIT 1');
    $stmt->execute([':name' => $trimmed]);
    $categoryId = $stmt->fetchColumn();

    if ($categoryId) {
        return (int)$categoryId;
    }

    $stmt = $db->prepare('INSERT INTO categories (name, color) VALUES (:name, :color)');
    $stmt->execute([
        ':name' => $trimmed,
        ':color' => pickColorForCategory($trimmed),
    ]);

    return (int)$db->lastInsertId();
}

function pickColorForCategory(string $name): string
{
    $palette = ['#0f172a', '#0284c7', '#7c3aed', '#f97316', '#10b981', '#facc15', '#ef4444', '#6366f1', '#8b5cf6'];
    $normalized = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    $hash = crc32($normalized);

    return $palette[$hash % count($palette)];
}

function rebuildTransactionsTable(PDO $db): void
{
    $columns = $db->query('PRAGMA table_info(transactions)')->fetchAll();
    $hasLegacyColumn = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'category') {
            $hasLegacyColumn = true;
            break;
        }
    }

    if (!$hasLegacyColumn) {
        return;
    }

    $db->exec('PRAGMA foreign_keys = OFF');
    $db->exec('ALTER TABLE transactions RENAME TO transactions_legacy');
    $db->exec('CREATE TABLE transactions (
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
    $db->exec('INSERT INTO transactions (id, account_id, amount, type, description, transaction_date, category_id, created_at)
        SELECT id, account_id, amount, type, description, transaction_date, category_id, created_at FROM transactions_legacy');
    $db->exec('DROP TABLE transactions_legacy');
    $db->exec('PRAGMA foreign_keys = ON');

    $sequenceTable = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'")
        ->fetchColumn();

    if ($sequenceTable) {
        $maxId = (int)$db->query('SELECT IFNULL(MAX(id), 0) FROM transactions')->fetchColumn();
        $db->exec('DELETE FROM sqlite_sequence WHERE name = "transactions"');

        if ($maxId > 0) {
            $stmt = $db->prepare('INSERT INTO sqlite_sequence (name, seq) VALUES (:name, :seq)');
            $stmt->execute([
                ':name' => 'transactions',
                ':seq' => $maxId,
            ]);
        }
    }
}
