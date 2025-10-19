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

function getAccountById(int $id): ?array
{
    $db = getDatabase();
    $stmt = $db->prepare('SELECT * FROM accounts WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $account = $stmt->fetch();

    return $account ?: null;
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

function updateAccount(int $id, string $name, string $type): void
{
    $db = getDatabase();
    $stmt = $db->prepare('UPDATE accounts SET name = :name, type = :type WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':name' => trim($name),
        ':type' => $type,
    ]);
}

function deleteAccount(int $id): void
{
    $db = getDatabase();
    $stmt = $db->prepare('DELETE FROM accounts WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function getCategories(): array
{
    $db = getDatabase();
    $stmt = $db->query('SELECT * FROM categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

function getCategoryOptions(): array
{
    $db = getDatabase();
    $stmt = $db->query('SELECT id, name FROM categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

function getCategoryById(int $id): ?array
{
    $db = getDatabase();
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $category = $stmt->fetch();

    return $category ?: null;
}

function createCategory(string $name, ?string $color = null): void
{
    $db = getDatabase();
    $stmt = $db->prepare('INSERT INTO categories (name, color) VALUES (:name, :color)');
    $stmt->execute([
        ':name' => trim($name),
        ':color' => $color ?: null,
    ]);
}

function updateCategory(int $id, string $name, ?string $color = null): void
{
    $db = getDatabase();
    $stmt = $db->prepare('UPDATE categories SET name = :name, color = :color WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':name' => trim($name),
        ':color' => $color ?: null,
    ]);
}

function deleteCategory(int $id): void
{
    $db = getDatabase();
    $generalId = getGeneralCategoryId();

    if ($id === $generalId) {
        throw new InvalidArgumentException('The General category cannot be deleted.');
    }

    $stmt = $db->prepare('UPDATE transactions SET category_id = :general_id WHERE category_id = :category_id');
    $stmt->execute([
        ':general_id' => $generalId,
        ':category_id' => $id,
    ]);

    $stmt = $db->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function getGeneralCategoryId(): int
{
    $db = getDatabase();

    return ensureGeneralCategoryId($db);
}

function createTransaction(int $accountId, float $amount, string $type, string $date, int $categoryId, ?string $description): void
{
    $db = getDatabase();
    $stmt = $db->prepare('INSERT INTO transactions (account_id, amount, type, description, transaction_date, category_id)
        VALUES (:account_id, :amount, :type, :description, :transaction_date, :category_id)');
    $stmt->execute([
        ':account_id' => $accountId,
        ':amount' => abs($amount),
        ':type' => $type,
        ':description' => $description ?: null,
        ':transaction_date' => $date,
        ':category_id' => $categoryId,
    ]);
}

function updateTransaction(int $id, int $accountId, float $amount, string $type, string $date, int $categoryId, ?string $description): void
{
    $db = getDatabase();
    $stmt = $db->prepare('UPDATE transactions SET account_id = :account_id, amount = :amount, type = :type,
        description = :description, transaction_date = :transaction_date, category_id = :category_id WHERE id = :id');
    $stmt->execute([
        ':id' => $id,
        ':account_id' => $accountId,
        ':amount' => abs($amount),
        ':type' => $type,
        ':description' => $description ?: null,
        ':transaction_date' => $date,
        ':category_id' => $categoryId,
    ]);
}

function deleteTransaction(int $id): void
{
    $db = getDatabase();
    $stmt = $db->prepare('DELETE FROM transactions WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function getRecentTransactions(int $limit = 10): array
{
    $db = getDatabase();
    $stmt = $db->prepare('SELECT t.*, a.name AS account_name, c.name AS category_name FROM transactions t
        INNER JOIN accounts a ON a.id = t.account_id
        LEFT JOIN categories c ON c.id = t.category_id
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

    if (!empty($filters['category_id']) && $filters['category_id'] !== 'all') {
        $conditions[] = 't.category_id = :category_id';
        $params[':category_id'] = (int)$filters['category_id'];
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

    $sql = 'SELECT t.*, a.name AS account_name, c.name AS category_name FROM transactions t
        INNER JOIN accounts a ON a.id = t.account_id
        LEFT JOIN categories c ON c.id = t.category_id
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

function getTransactionById(int $id): ?array
{
    $db = getDatabase();
    $stmt = $db->prepare('SELECT t.*, a.name AS account_name, c.name AS category_name FROM transactions t
        INNER JOIN accounts a ON a.id = t.account_id
        LEFT JOIN categories c ON c.id = t.category_id
        WHERE t.id = :id');
    $stmt->execute([':id' => $id]);
    $transaction = $stmt->fetch();

    return $transaction ?: null;
}

function getSummaryMetrics(array $filters = []): array
{
    $db = getDatabase();
    $summary = [
        'total_balance' => 0.0,
        'total_expenses' => 0.0,
        'total_deposits' => 0.0,
    ];

    $conditions = [];
    $params = [];

    if (!empty($filters['account_id']) && $filters['account_id'] !== 'all') {
        $conditions[] = 'account_id = :account_id';
        $params[':account_id'] = (int)$filters['account_id'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'transaction_date >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'transaction_date <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = 'SELECT
        IFNULL(SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END), 0) AS deposits,
        IFNULL(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expenses
        FROM transactions ' . $where;

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->execute();
    $totals = $stmt->fetch();

    $summary['total_deposits'] = (float)$totals['deposits'];
    $summary['total_expenses'] = (float)$totals['expenses'];
    $summary['total_balance'] = $summary['total_deposits'] - $summary['total_expenses'];

    return $summary;
}

function getExpenseByCategory(array $filters = []): array
{
    $db = getDatabase();
    $params = [];
    $joinConditions = ['c.id = t.category_id', 't.type = "expense"'];

    if (!empty($filters['account_id']) && $filters['account_id'] !== 'all') {
        $joinConditions[] = 't.account_id = :account_id';
        $params[':account_id'] = (int)$filters['account_id'];
    }

    if (!empty($filters['date_from'])) {
        $joinConditions[] = 't.transaction_date >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $joinConditions[] = 't.transaction_date <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }

    $joinClause = implode(' AND ', $joinConditions);

    $sql = 'SELECT c.id, c.name AS category_name, c.color, IFNULL(SUM(t.amount), 0) AS total
        FROM categories c
        LEFT JOIN transactions t ON ' . $joinClause . '
        GROUP BY c.id
        ORDER BY total DESC';

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->execute();

    return $stmt->fetchAll();
}

function getTopSpendingCategories(int $limit = 5, array $filters = []): array
{
    $categories = getExpenseByCategory($filters);
    $spending = array_values(array_filter($categories, static fn($item) => (float)$item['total'] > 0));

    return array_slice($spending, 0, $limit);
}

function getMonthlyCashFlow(array $filters = []): array
{
    $db = getDatabase();
    $conditions = [];
    $params = [];

    if (!empty($filters['account_id']) && $filters['account_id'] !== 'all') {
        $conditions[] = 'account_id = :account_id';
        $params[':account_id'] = (int)$filters['account_id'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'transaction_date >= :date_from';
        $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'transaction_date <= :date_to';
        $params[':date_to'] = $filters['date_to'];
    }

    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

    $sql = 'SELECT strftime("%Y-%m", transaction_date) AS month,
        SUM(CASE WHEN type = "deposit" THEN amount ELSE 0 END) AS deposits,
        SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) AS expenses
        FROM transactions ' . $where . '
        GROUP BY month
        ORDER BY month ASC';

    $stmt = $db->prepare($sql);

    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $type);
    }

    $stmt->execute();

    return $stmt->fetchAll();
}
