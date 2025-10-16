<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$errors = [];
$successMessage = null;

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $value): string
{
    return 'UZS ' . number_format($value, 0, '.', ' ');
}

$validAccountTypes = ['cash', 'bank', 'savings', 'investment', 'other'];
$validTransactionTypes = ['expense', 'deposit'];

$page = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'accounts', 'transactions', 'categories', 'reports'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create_account':
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'cash';

                if ($name === '') {
                    throw new InvalidArgumentException('Account name is required.');
                }

                if (!in_array($type, $validAccountTypes, true)) {
                    throw new InvalidArgumentException('Invalid account type.');
                }

                createAccount($name, $type);
                $successMessage = 'Account created successfully.';
                break;

            case 'update_account':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'cash';

                if ($id <= 0) {
                    throw new InvalidArgumentException('Account not found.');
                }

                if ($name === '') {
                    throw new InvalidArgumentException('Account name is required.');
                }

                if (!in_array($type, $validAccountTypes, true)) {
                    throw new InvalidArgumentException('Invalid account type.');
                }

                updateAccount($id, $name, $type);
                $successMessage = 'Account updated successfully.';
                break;

            case 'delete_account':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new InvalidArgumentException('Account not found.');
                }

                deleteAccount($id);
                $successMessage = 'Account deleted successfully.';
                break;

            case 'create_category':
                $name = trim($_POST['name'] ?? '');
                $color = trim($_POST['color'] ?? '') ?: null;

                if ($name === '') {
                    throw new InvalidArgumentException('Category name is required.');
                }

                createCategory($name, $color);
                $successMessage = 'Category created successfully.';
                break;

            case 'update_category':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $color = trim($_POST['color'] ?? '') ?: null;

                if ($id <= 0) {
                    throw new InvalidArgumentException('Category not found.');
                }

                if ($name === '') {
                    throw new InvalidArgumentException('Category name is required.');
                }

                updateCategory($id, $name, $color);
                $successMessage = 'Category updated successfully.';
                break;

            case 'delete_category':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new InvalidArgumentException('Category not found.');
                }

                deleteCategory($id);
                $successMessage = 'Category deleted successfully.';
                break;

            case 'create_transaction':
                $accountId = (int)($_POST['account_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $type = $_POST['transaction_type'] ?? 'expense';
                $date = $_POST['transaction_date'] ?? date('Y-m-d');
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $description = trim($_POST['description'] ?? '') ?: null;

                if ($accountId <= 0) {
                    throw new InvalidArgumentException('Please choose an account.');
                }

                if ($categoryId <= 0) {
                    throw new InvalidArgumentException('Please select a category.');
                }

                if ($amount <= 0) {
                    throw new InvalidArgumentException('Amount must be greater than zero.');
                }

                if (!in_array($type, $validTransactionTypes, true)) {
                    throw new InvalidArgumentException('Invalid transaction type.');
                }

                $dateTime = DateTime::createFromFormat('Y-m-d', $date);
                if (!$dateTime) {
                    throw new InvalidArgumentException('Invalid transaction date.');
                }

                createTransaction($accountId, $amount, $type, $dateTime->format('Y-m-d'), $categoryId, $description);
                $successMessage = 'Transaction recorded successfully.';
                break;

            case 'update_transaction':
                $transactionId = (int)($_POST['id'] ?? 0);
                $accountId = (int)($_POST['account_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $type = $_POST['transaction_type'] ?? 'expense';
                $date = $_POST['transaction_date'] ?? date('Y-m-d');
                $categoryId = (int)($_POST['category_id'] ?? 0);
                $description = trim($_POST['description'] ?? '') ?: null;

                if ($transactionId <= 0) {
                    throw new InvalidArgumentException('Transaction not found.');
                }

                if ($accountId <= 0) {
                    throw new InvalidArgumentException('Please choose an account.');
                }

                if ($categoryId <= 0) {
                    throw new InvalidArgumentException('Please select a category.');
                }

                if ($amount <= 0) {
                    throw new InvalidArgumentException('Amount must be greater than zero.');
                }

                if (!in_array($type, $validTransactionTypes, true)) {
                    throw new InvalidArgumentException('Invalid transaction type.');
                }

                $dateTime = DateTime::createFromFormat('Y-m-d', $date);
                if (!$dateTime) {
                    throw new InvalidArgumentException('Invalid transaction date.');
                }

                updateTransaction($transactionId, $accountId, $amount, $type, $dateTime->format('Y-m-d'), $categoryId, $description);
                $successMessage = 'Transaction updated successfully.';
                break;

            case 'delete_transaction':
                $transactionId = (int)($_POST['id'] ?? 0);
                if ($transactionId <= 0) {
                    throw new InvalidArgumentException('Transaction not found.');
                }

                deleteTransaction($transactionId);
                $successMessage = 'Transaction deleted successfully.';
                break;
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$accounts = getAccountsWithBalance();
$recentTransactions = getRecentTransactions();
$metrics = getSummaryMetrics();
$generalCategoryId = getGeneralCategoryId();
$accountOptions = getAccountOptions();
$categoryOptions = getCategoryOptions();

$filters = [
    'account_id' => $_GET['filter_account'] ?? null,
    'category_id' => $_GET['filter_category'] ?? 'all',
    'type' => $_GET['filter_type'] ?? 'all',
    'date_from' => $_GET['filter_from'] ?? null,
    'date_to' => $_GET['filter_to'] ?? null,
];

$transactions = $page === 'transactions' ? getTransactions($filters) : [];
$expenseByCategory = getExpenseByCategory();
$topSpendingCategories = getTopSpendingCategories();
$monthlyCashFlow = getMonthlyCashFlow();

$expenseChartSource = array_filter($expenseByCategory, static fn($item) => (float)$item['total'] > 0);
if (!$expenseChartSource && $expenseByCategory) {
    $expenseChartSource = $expenseByCategory;
}

$expenseChartData = [
    'labels' => array_map(static fn($item) => $item['category_name'], $expenseChartSource),
    'values' => array_map(static fn($item) => (float)$item['total'], $expenseChartSource),
];

$cashFlowChartData = [
    'labels' => array_map(static fn($item) => $item['month'], $monthlyCashFlow),
    'deposits' => array_map(static fn($item) => (float)$item['deposits'], $monthlyCashFlow),
    'expenses' => array_map(static fn($item) => (float)$item['expenses'], $monthlyCashFlow),
];

$editAccountId = isset($_GET['edit_account']) ? (int)$_GET['edit_account'] : null;
$editAccount = $editAccountId ? getAccountById($editAccountId) : null;

$editCategoryId = isset($_GET['edit_category']) ? (int)$_GET['edit_category'] : null;
$editCategory = $editCategoryId ? getCategoryById($editCategoryId) : null;

$editTransactionId = isset($_GET['edit_transaction']) ? (int)$_GET['edit_transaction'] : null;
$editTransaction = $editTransactionId ? getTransactionById($editTransactionId) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashing - Expense Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        }
        .pill {
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
    <div class="min-h-screen">
        <header class="bg-white shadow-sm">
            <div class="max-w-6xl mx-auto px-4 py-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Cashing Expense Tracker</h1>
                    <p class="text-slate-500">Stay on top of your so'm spending with structured accounts and smart insights.</p>
                </div>
                <nav class="flex flex-wrap gap-2">
                    <?php foreach ($allowedPages as $navPage): ?>
                        <?php
                        $isActive = $page === $navPage;
                        $labels = [
                            'dashboard' => 'Dashboard',
                            'accounts' => 'Accounts',
                            'transactions' => 'Transactions',
                            'categories' => 'Categories',
                            'reports' => 'Reports',
                        ];
                        ?>
                        <a href="?page=<?php echo $navPage; ?>" class="px-4 py-2 pill text-sm font-medium <?php echo $isActive ? 'bg-slate-900 text-white shadow-lg' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                            <?php echo $labels[$navPage]; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-10 space-y-8">
            <?php if ($successMessage): ?>
                <div class="glass-card rounded-xl border border-emerald-200 p-4 text-emerald-800 bg-emerald-50">
                    <p class="font-medium"><?php echo sanitize($successMessage); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="glass-card rounded-xl border border-red-200 p-4 bg-red-50 text-red-700 space-y-2">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo sanitize($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($page === 'dashboard'): ?>
                <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Total Balance</h2>
                        <p class="text-3xl font-semibold text-slate-900 mt-2"><?php echo sanitize(formatCurrency($metrics['total_balance'])); ?></p>
                        <p class="text-sm text-slate-500 mt-4">Deposits: <span class="font-medium text-emerald-600"><?php echo sanitize(formatCurrency($metrics['total_deposits'])); ?></span></p>
                        <p class="text-sm text-slate-500">Expenses: <span class="font-medium text-rose-500"><?php echo sanitize(formatCurrency($metrics['total_expenses'])); ?></span></p>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6 lg:col-span-2">
                        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Accounts Overview</h2>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <?php foreach ($accounts as $account): ?>
                                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm uppercase tracking-wide text-slate-400"><?php echo sanitize(ucfirst($account['type'])); ?></p>
                                            <h3 class="text-lg font-semibold text-slate-900"><?php echo sanitize($account['name']); ?></h3>
                                        </div>
                                        <span class="text-sm text-slate-500"><?php echo date('d M Y', strtotime($account['created_at'])); ?></span>
                                    </div>
                                    <p class="mt-3 text-2xl font-semibold text-slate-900"><?php echo sanitize(formatCurrency((float)$account['balance'])); ?></p>
                                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                        <div class="rounded-xl bg-emerald-50 text-emerald-700 px-3 py-2">
                                            <p>Deposits</p>
                                            <p class="font-medium"><?php echo sanitize(formatCurrency((float)$account['total_deposits'])); ?></p>
                                        </div>
                                        <div class="rounded-xl bg-rose-50 text-rose-700 px-3 py-2">
                                            <p>Expenses</p>
                                            <p class="font-medium"><?php echo sanitize(formatCurrency((float)$account['total_expenses'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-lg font-semibold text-slate-900">Recent Transactions</h2>
                        <div class="mt-4 space-y-3">
                            <?php if (!$recentTransactions): ?>
                                <p class="text-sm text-slate-500">No transactions yet. Start logging your spending.</p>
                            <?php endif; ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white/80 px-4 py-3">
                                    <div>
                                        <p class="font-medium text-slate-800"><?php echo sanitize($transaction['account_name']); ?></p>
                                        <p class="text-sm text-slate-500"><?php echo sanitize($transaction['category_name'] ?? 'General'); ?> • <?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold <?php echo $transaction['type'] === 'deposit' ? 'text-emerald-600' : 'text-rose-600'; ?>"><?php echo ($transaction['type'] === 'deposit' ? '+' : '-') . sanitize(formatCurrency((float)$transaction['amount'])); ?></p>
                                        <?php if (!empty($transaction['description'])): ?>
                                            <p class="text-xs text-slate-400"><?php echo sanitize((string)$transaction['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-lg font-semibold text-slate-900">Top Spending Categories</h2>
                        <ul class="mt-4 space-y-3">
                            <?php foreach ($topSpendingCategories as $index => $category): ?>
                                <li class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white/80 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-white font-semibold"><?php echo $index + 1; ?></span>
                                        <div>
                                            <p class="font-medium text-slate-800"><?php echo sanitize($category['category_name']); ?></p>
                                            <p class="text-xs text-slate-400">Share of total expenses</p>
                                        </div>
                                    </div>
                                    <p class="font-semibold text-rose-600"><?php echo sanitize(formatCurrency((float)$category['total'])); ?></p>
                                </li>
                            <?php endforeach; ?>
                            <?php if (!$topSpendingCategories): ?>
                                <li class="rounded-2xl border border-dashed border-slate-200 bg-white/60 px-4 py-6 text-center text-sm text-slate-500">
                                    Log some expenses to discover your top spending categories.
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </section>
            <?php elseif ($page === 'accounts'): ?>
                <section class="glass-card rounded-3xl border border-slate-200 p-6 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">Manage Accounts</h2>
                            <p class="text-sm text-slate-500">Organize your cash, bank and Hamkor accounts in one place.</p>
                        </div>
                        <a href="?page=accounts" class="text-sm text-slate-500 hover:text-slate-800">Clear edit form</a>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-1">
                            <h3 class="text-lg font-semibold text-slate-900"><?php echo $editAccount ? 'Edit account' : 'Create new account'; ?></h3>
                            <form method="post" class="mt-4 space-y-4">
                                <input type="hidden" name="action" value="<?php echo $editAccount ? 'update_account' : 'create_account'; ?>">
                                <?php if ($editAccount): ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$editAccount['id']; ?>">
                                <?php endif; ?>
                                <div>
                                    <label class="block text-sm text-slate-500">Account name</label>
                                    <input type="text" name="name" required value="<?php echo $editAccount ? sanitize($editAccount['name']) : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-500">Type</label>
                                    <select name="type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                        <?php foreach ($validAccountTypes as $type): ?>
                                            <option value="<?php echo $type; ?>" <?php echo $editAccount && $editAccount['type'] === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="w-full rounded-xl bg-slate-900 py-2 text-white font-medium hover:bg-slate-800">
                                    <?php echo $editAccount ? 'Update account' : 'Create account'; ?>
                                </button>
                            </form>
                        </div>
                        <div class="lg:col-span-2">
                            <table class="min-w-full divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Name</th>
                                        <th class="px-4 py-3">Type</th>
                                        <th class="px-4 py-3">Balance</th>
                                        <th class="px-4 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 text-sm">
                                    <?php foreach ($accounts as $account): ?>
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-slate-800"><?php echo sanitize($account['name']); ?></td>
                                            <td class="px-4 py-3 text-slate-500"><?php echo sanitize(ucfirst($account['type'])); ?></td>
                                            <td class="px-4 py-3 font-semibold text-slate-900"><?php echo sanitize(formatCurrency((float)$account['balance'])); ?></td>
                                            <td class="px-4 py-3 space-x-3">
                                                <a href="?page=accounts&edit_account=<?php echo (int)$account['id']; ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900">Edit</a>
                                                <form method="post" class="inline" onsubmit="return confirm('Delete this account? Transactions will also be removed.');">
                                                    <input type="hidden" name="action" value="delete_account">
                                                    <input type="hidden" name="id" value="<?php echo (int)$account['id']; ?>">
                                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            <?php elseif ($page === 'transactions'): ?>
                <section class="glass-card rounded-3xl border border-slate-200 p-6 space-y-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">Transactions</h2>
                            <p class="text-sm text-slate-500">Log deposits and expenses against any account and category.</p>
                        </div>
                        <a href="?page=transactions" class="text-sm text-slate-500 hover:text-slate-800">Clear edit form</a>
                    </div>

                    <form method="post" class="grid grid-cols-1 lg:grid-cols-6 gap-4 bg-slate-50/80 rounded-2xl border border-slate-200 p-4">
                        <input type="hidden" name="action" value="<?php echo $editTransaction ? 'update_transaction' : 'create_transaction'; ?>">
                        <?php if ($editTransaction): ?>
                            <input type="hidden" name="id" value="<?php echo (int)$editTransaction['id']; ?>">
                        <?php endif; ?>
                        <div class="lg:col-span-2">
                            <label class="block text-sm text-slate-500">Account</label>
                            <select name="account_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                <option value="">Select account</option>
                                <?php foreach ($accountOptions as $option): ?>
                                    <option value="<?php echo (int)$option['id']; ?>" <?php echo $editTransaction && (int)$editTransaction['account_id'] === (int)$option['id'] ? 'selected' : ''; ?>><?php echo sanitize($option['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">Type</label>
                            <select name="transaction_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                <?php foreach ($validTransactionTypes as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $editTransaction && $editTransaction['type'] === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">Amount (UZS)</label>
                            <input type="number" min="0" step="0.01" name="amount" required value="<?php echo $editTransaction ? sanitize((string)$editTransaction['amount']) : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">Category</label>
                            <select name="category_id" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                <option value="">Select category</option>
                                <?php foreach ($categoryOptions as $option): ?>
                                    <option value="<?php echo (int)$option['id']; ?>" <?php echo $editTransaction && (int)$editTransaction['category_id'] === (int)$option['id'] ? 'selected' : ''; ?>><?php echo sanitize($option['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">Date</label>
                            <input type="date" name="transaction_date" required value="<?php echo $editTransaction ? sanitize($editTransaction['transaction_date']) : date('Y-m-d'); ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                        </div>
                        <div class="lg:col-span-6">
                            <label class="block text-sm text-slate-500">Description</label>
                            <textarea name="description" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none" placeholder="Optional details about this transaction"><?php echo $editTransaction ? sanitize((string)$editTransaction['description']) : ''; ?></textarea>
                        </div>
                        <div class="lg:col-span-6 flex justify-end">
                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-white font-medium hover:bg-slate-800">
                                <?php echo $editTransaction ? 'Update transaction' : 'Add transaction'; ?>
                            </button>
                        </div>
                    </form>

                    <form method="get" class="grid grid-cols-1 lg:grid-cols-6 gap-4">
                        <input type="hidden" name="page" value="transactions">
                        <div>
                            <label class="block text-sm text-slate-500">Account</label>
                            <select name="filter_account" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                <option value="">All accounts</option>
                                <?php foreach ($accountOptions as $option): ?>
                                    <option value="<?php echo (int)$option['id']; ?>" <?php echo isset($filters['account_id']) && (int)$filters['account_id'] === (int)$option['id'] ? 'selected' : ''; ?>><?php echo sanitize($option['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">Category</label>
                            <select name="filter_category" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                <option value="all" <?php echo ($filters['category_id'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All categories</option>
                                <?php foreach ($categoryOptions as $option): ?>
                                    <option value="<?php echo (int)$option['id']; ?>" <?php echo (string)$filters['category_id'] === (string)$option['id'] ? 'selected' : ''; ?>><?php echo sanitize($option['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">Type</label>
                            <select name="filter_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                <option value="all" <?php echo ($filters['type'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All</option>
                                <?php foreach ($validTransactionTypes as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo ($filters['type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">From</label>
                            <input type="date" name="filter_from" value="<?php echo $filters['date_from'] ? sanitize($filters['date_from']) : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm text-slate-500">To</label>
                            <input type="date" name="filter_to" value="<?php echo $filters['date_to'] ? sanitize($filters['date_to']) : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-white font-medium hover:bg-slate-800">Apply filters</button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Account</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3">Amount</th>
                                    <th class="px-4 py-3">Description</th>
                                    <th class="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 text-sm">
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-slate-500"><?php echo date('d M Y', strtotime($transaction['transaction_date'])); ?></td>
                                        <td class="px-4 py-3 font-medium text-slate-800"><?php echo sanitize($transaction['account_name']); ?></td>
                                        <td class="px-4 py-3 text-slate-600"><?php echo sanitize($transaction['category_name'] ?? 'General'); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-3 py-1 text-xs font-semibold pill <?php echo $transaction['type'] === 'deposit' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'; ?>"><?php echo ucfirst($transaction['type']); ?></span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold <?php echo $transaction['type'] === 'deposit' ? 'text-emerald-600' : 'text-rose-600'; ?>"><?php echo ($transaction['type'] === 'deposit' ? '+' : '-') . sanitize(formatCurrency((float)$transaction['amount'])); ?></td>
                                        <td class="px-4 py-3 text-slate-500 max-w-xs"><?php echo sanitize((string)($transaction['description'] ?? '—')); ?></td>
                                        <td class="px-4 py-3 space-x-3">
                                            <a href="?page=transactions&edit_transaction=<?php echo (int)$transaction['id']; ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900">Edit</a>
                                            <form method="post" class="inline" onsubmit="return confirm('Delete this transaction?');">
                                                <input type="hidden" name="action" value="delete_transaction">
                                                <input type="hidden" name="id" value="<?php echo (int)$transaction['id']; ?>">
                                                <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$transactions): ?>
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">No transactions found for your selected filters.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($page === 'categories'): ?>
                <section class="glass-card rounded-3xl border border-slate-200 p-6 space-y-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">Spending Categories</h2>
                            <p class="text-sm text-slate-500">Create the categories you want to use when logging transactions.</p>
                        </div>
                        <a href="?page=categories" class="text-sm text-slate-500 hover:text-slate-800">Clear edit form</a>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900"><?php echo $editCategory ? 'Edit category' : 'Create new category'; ?></h3>
                            <form method="post" class="mt-4 space-y-4">
                                <input type="hidden" name="action" value="<?php echo $editCategory ? 'update_category' : 'create_category'; ?>">
                                <?php if ($editCategory): ?>
                                    <input type="hidden" name="id" value="<?php echo (int)$editCategory['id']; ?>">
                                <?php endif; ?>
                                <div>
                                    <label class="block text-sm text-slate-500">Category name</label>
                                    <input type="text" name="name" required value="<?php echo $editCategory ? sanitize($editCategory['name']) : ''; ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-500">Accent color (optional)</label>
                                    <input type="color" name="color" value="<?php echo $editCategory && $editCategory['color'] ? sanitize($editCategory['color']) : '#0f172a'; ?>" class="mt-1 h-12 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                </div>
                                <button type="submit" class="w-full rounded-xl bg-slate-900 py-2 text-white font-medium hover:bg-slate-800">
                                    <?php echo $editCategory ? 'Update category' : 'Create category'; ?>
                                </button>
                            </form>
                        </div>
                        <div class="lg:col-span-2 grid grid-cols-1 gap-4">
                            <?php foreach ($expenseByCategory as $category): ?>
                                <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white/80 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="h-10 w-10 rounded-full" style="background: <?php echo sanitize($category['color'] ?? '#0f172a'); ?>;"></span>
                                        <div>
                                            <p class="font-medium text-slate-800"><?php echo sanitize($category['category_name']); ?></p>
                                            <p class="text-sm text-slate-500">Spent: <?php echo sanitize(formatCurrency((float)$category['total'])); ?></p>
                                        </div>
                                    </div>
                                        <div class="space-x-3">
                                            <a href="?page=categories&edit_category=<?php echo (int)$category['id']; ?>" class="text-sm font-medium text-slate-600 hover:text-slate-900">Edit</a>
                                            <?php if ((int)$category['id'] !== $generalCategoryId): ?>
                                                <form method="post" class="inline" onsubmit="return confirm('Delete this category? Transactions will move to General.');">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="id" value="<?php echo (int)$category['id']; ?>">
                                                    <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-800">Delete</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$expenseByCategory): ?>
                                <p class="text-sm text-slate-500">No categories available yet. Create one to get started.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php elseif ($page === 'reports'): ?>
                <section class="space-y-6">
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-xl font-semibold text-slate-900">Expense distribution</h2>
                        <p class="text-sm text-slate-500">See which categories consume most of your spending.</p>
                        <div class="mt-6">
                            <canvas id="expenseChart" height="280"></canvas>
                        </div>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-xl font-semibold text-slate-900">Monthly cash flow</h2>
                        <p class="text-sm text-slate-500">Compare monthly inflows versus outflows.</p>
                        <div class="mt-6">
                            <canvas id="cashFlowChart" height="320"></canvas>
                        </div>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-xl font-semibold text-slate-900">Where you spend the most</h2>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($topSpendingCategories as $category): ?>
                                <div class="rounded-2xl border border-slate-200 bg-white/80 p-4">
                                    <p class="text-sm uppercase tracking-wide text-slate-400">Category</p>
                                    <h3 class="text-lg font-semibold text-slate-900"><?php echo sanitize($category['category_name']); ?></h3>
                                    <p class="mt-3 text-2xl font-semibold text-rose-600"><?php echo sanitize(formatCurrency((float)$category['total'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$topSpendingCategories): ?>
                                <p class="text-sm text-slate-500">Log some expenses to populate this view.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($page === 'reports'): ?>
        <script>
            const expenseCtx = document.getElementById('expenseChart');
            const expenseData = <?php echo json_encode($expenseChartData, JSON_THROW_ON_ERROR); ?>;
            const cashFlowData = <?php echo json_encode($cashFlowChartData, JSON_THROW_ON_ERROR); ?>;

            new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: expenseData.labels,
                    datasets: [{
                        data: expenseData.values,
                        backgroundColor: ['#0f172a', '#0284c7', '#7c3aed', '#f97316', '#059669', '#facc15', '#ef4444', '#10b981'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#1f2937',
                            },
                        },
                    },
                },
            });

            const cashFlowCtx = document.getElementById('cashFlowChart');
            new Chart(cashFlowCtx, {
                type: 'bar',
                data: {
                    labels: cashFlowData.labels,
                    datasets: [
                        {
                            label: 'Deposits',
                            data: cashFlowData.deposits,
                            backgroundColor: '#059669',
                            borderRadius: 10,
                        },
                        {
                            label: 'Expenses',
                            data: cashFlowData.expenses,
                            backgroundColor: '#ef4444',
                            borderRadius: 10,
                        }
                    ],
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            stacked: false,
                            ticks: { color: '#475569' },
                            grid: { display: false },
                        },
                        y: {
                            ticks: { color: '#475569' },
                            grid: { color: '#e2e8f0' },
                        },
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#1f2937',
                            },
                        },
                    },
                },
            });
        </script>
    <?php endif; ?>
</body>
</html>
