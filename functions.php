<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function getAccountsWithBalance(): array
{
    $db = getDatabase();
    $stmt = $db->query('SELECT a.id, a.name, a.type, a.created_at,
        IFNULL(SUM(CASE WHEN t.type = "deposit" THEN t.amount ELSE 0 END), 0) AS total_deposits,
        IFNULL(SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END), 0) AS total_expenses
        FROM accounts a
        LEFT JOIN transactions t ON a.id = t.account_id
        GROUP BY a.id
        ORDER BY a.created_at ASC');
    $accounts = $stmt->fetchAll();

    foreach ($accounts as &$account) {
        $account['balance'] = (float)$account['total_deposits'] - (float)$account['total_expenses'];
    }

    return $accounts;
}

function getAccountOptions(): array
{
    $db = getDatabase();
    $stmt = $db->query('SELECT id, name FROM accounts ORDER BY name ASC');
    return $stmt->fetchAll();
}

function createAccount(string $name, string $type): void
{
    $db = getDatabase();
    $stmt = $db->prepare('INSERT INTO accounts (name, type) VALUES (:name, :type)');
    $stmt->execute([
        ':name' => trim($name),
        ':type' => $type,
    ]);
}

function recordTransaction(int $accountId, float $amount, string $category, string $type, ?string $description, string $date): void
{
    $db = getDatabase();
    $stmt = $db->prepare('INSERT INTO transactions (account_id, amount, category, type, description, transaction_date)
        VALUES (:account_id, :amount, :category, :type, :description, :transaction_date)');
    $stmt->execute([
        ':account_id' => $accountId,
        ':amount' => abs($amount),
        ':category' => trim($category),
        ':type' => $type,
        ':description' => $description,
        ':transaction_date' => $date,
    ]);
}

function getRecentTransactions(int $limit = 10): array
{
    $db = getDatabase();
    $stmt = $db->prepare('SELECT t.*, a.name AS account_name FROM transactions t
        INNER JOIN accounts a ON a.id = t.account_id
        ORDER BY transaction_date DESC, t.id DESC
        LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getTransactions(array $filters = []): array
{
    $db = getDatabase();
    $conditions = [];
    $params = [];

    if (!empty($filters['account_id'])) {
        $conditions[] = 't.account_id = :account_id';
        $params[':account_id'] = (int)$filters['account_id'];
    }

    if (!empty($filters['type']) && $filters['type'] !== 'all') {
        $conditions[] = 't.type = :type';
        $params[':type'] = $filters['type'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 't.transaction_date >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 't.transaction_date <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = 'SELECT t.*, a.name AS account_name FROM transactions t
        INNER JOIN accounts a ON a.id = t.account_id
        ' . $where . '
        ORDER BY transaction_date DESC, t.id DESC';

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function getSummaryMetrics(): array
{
    $db = getDatabase();
    $summary = [
        'total_balance' => 0.0,
        'total_expenses' => 0.0,
        'total_deposits' => 0.0,
    ];

    $stmt = $db->query('SELECT
        IFNULL(SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END), 0) AS deposits,
        IFNULL(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expenses
        FROM transactions');
    $totals = $stmt->fetch();

    $summary['total_deposits'] = (float)$totals['deposits'];
    $summary['total_expenses'] = (float)$totals['expenses'];
    $summary['total_balance'] = $summary['total_deposits'] - $summary['total_expenses'];

    return $summary;
}

function getExpenseByCategory(): array
{
    $db = getDatabase();
    $stmt = $db->query('SELECT category, SUM(amount) AS total FROM transactions
        WHERE type = "expense"
        GROUP BY category
        ORDER BY total DESC');
    return $stmt->fetchAll();
}

function getMonthlyCashFlow(): array
{
    $db = getDatabase();
    $stmt = $db->query('SELECT strftime("%Y-%m", transaction_date) AS month,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) AS deposits,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) AS expenses
        FROM transactions
        GROUP BY month
        ORDER BY month ASC');
    return $stmt->fetchAll();
}
