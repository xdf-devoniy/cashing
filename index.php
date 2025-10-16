<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$db = getDatabase();

$errors = [];
$successMessage = null;

function sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatCurrency(float $value): string
{
    return '$' . number_format($value, 2);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_account') {
            $name = trim($_POST['account_name'] ?? '');
            $type = $_POST['account_type'] ?? 'cash';

            if ($name === '') {
                throw new InvalidArgumentException('Account name is required.');
            }

            if (!in_array($type, ['cash', 'bank', 'savings', 'investment', 'other'], true)) {
                throw new InvalidArgumentException('Invalid account type selected.');
            }

            createAccount($name, $type);
            $successMessage = 'Account added successfully!';
        } elseif ($action === 'add_transaction') {
            $accountId = (int)($_POST['transaction_account'] ?? 0);
            $amount = (float)($_POST['transaction_amount'] ?? 0);
            $category = trim($_POST['transaction_category'] ?? 'General');
            $type = $_POST['transaction_type'] ?? 'expense';
            $description = trim($_POST['transaction_description'] ?? '') ?: null;
            $date = $_POST['transaction_date'] ?? date('Y-m-d');

            if ($accountId <= 0) {
                throw new InvalidArgumentException('Please choose a valid account.');
            }

            if ($amount <= 0) {
                throw new InvalidArgumentException('Amount should be greater than zero.');
            }

            if (!in_array($type, ['expense', 'deposit'], true)) {
                throw new InvalidArgumentException('Invalid transaction type.');
            }

            $dateTime = DateTime::createFromFormat('Y-m-d', $date);
            if (!$dateTime) {
                throw new InvalidArgumentException('Invalid transaction date.');
            }

            recordTransaction($accountId, $amount, $category, $type, $description, $dateTime->format('Y-m-d'));
            $successMessage = 'Transaction saved successfully!';
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$page = $_GET['page'] ?? 'dashboard';
$page = in_array($page, ['dashboard', 'transactions', 'reports'], true) ? $page : 'dashboard';

$accounts = getAccountsWithBalance();
$recentTransactions = getRecentTransactions();
$metrics = getSummaryMetrics();
$accountOptions = getAccountOptions();

$filters = [
    'account_id' => $_GET['filter_account'] ?? null,
    'type' => $_GET['filter_type'] ?? 'all',
    'date_from' => $_GET['filter_from'] ?? null,
    'date_to' => $_GET['filter_to'] ?? null,
];
$transactions = getTransactions($filters);

$expenseByCategory = getExpenseByCategory();
$monthlyCashFlow = getMonthlyCashFlow();

$expenseChartData = [
    'labels' => array_map(static fn($item) => $item['category'], $expenseByCategory),
    'values' => array_map(static fn($item) => (float)$item['total'], $expenseByCategory),
];

$cashFlowChartData = [
    'labels' => array_map(static fn($item) => $item['month'], $monthlyCashFlow),
    'deposits' => array_map(static fn($item) => (float)$item['deposits'], $monthlyCashFlow),
    'expenses' => array_map(static fn($item) => (float)$item['expenses'], $monthlyCashFlow),
];

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
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.08);
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen text-slate-900">
    <div class="min-h-screen">
        <header class="bg-white shadow-sm">
            <div class="max-w-6xl mx-auto px-4 py-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Cashing Expense Tracker</h1>
                    <p class="text-slate-500">Manage accounts, track spending, and visualize your cash flow.</p>
                </div>
                <nav class="flex gap-2">
                    <a href="?page=dashboard" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $page === 'dashboard' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">Dashboard</a>
                    <a href="?page=transactions" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $page === 'transactions' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">Transactions</a>
                    <a href="?page=reports" class="px-4 py-2 rounded-full text-sm font-medium <?php echo $page === 'reports' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">Reports</a>
                </nav>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-4 py-10 space-y-8">
            <?php if ($successMessage): ?>
                <div class="glass-card rounded-xl border border-slate-200 p-4 text-green-700 bg-green-50">
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
                        <p class="text-sm text-slate-500 mt-3">Deposits: <span class="font-medium text-emerald-600"><?php echo sanitize(formatCurrency($metrics['total_deposits'])); ?></span></p>
                        <p class="text-sm text-slate-500">Expenses: <span class="font-medium text-rose-500"><?php echo sanitize(formatCurrency($metrics['total_expenses'])); ?></span></p>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6 lg:col-span-2">
                        <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide">Quick Actions</h2>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <form method="post" class="space-y-3">
                                <input type="hidden" name="action" value="add_account">
                                <h3 class="text-lg font-semibold text-slate-800">Add Account</h3>
                                <div>
                                    <label class="block text-sm text-slate-500">Account name</label>
                                    <input type="text" name="account_name" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none" placeholder="e.g. Travel Fund">
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-500">Type</label>
                                    <select name="account_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                        <option value="cash">Cash</option>
                                        <option value="bank">Bank</option>
                                        <option value="savings">Savings</option>
                                        <option value="investment">Investment</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full rounded-xl bg-slate-900 py-2 text-white font-medium hover:bg-slate-800">Add Account</button>
                            </form>

                            <form method="post" class="space-y-3">
                                <input type="hidden" name="action" value="add_transaction">
                                <h3 class="text-lg font-semibold text-slate-800">Log Transaction</h3>
                                <div>
                                    <label class="block text-sm text-slate-500">Account</label>
                                    <select name="transaction_account" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                        <option value="">Select account</option>
                                        <?php foreach ($accountOptions as $option): ?>
                                            <option value="<?php echo (int)$option['id']; ?>"><?php echo sanitize($option['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm text-slate-500">Amount</label>
                                        <input type="number" step="0.01" min="0" name="transaction_amount" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none" placeholder="0.00">
                                    </div>
                                    <div>
                                        <label class="block text-sm text-slate-500">Type</label>
                                        <select name="transaction_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                            <option value="expense">Expense</option>
                                            <option value="deposit">Deposit</option>
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-500">Category</label>
                                    <input type="text" name="transaction_category" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none" placeholder="e.g. Groceries">
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-500">Date</label>
                                    <input type="date" name="transaction_date" value="<?php echo sanitize(date('Y-m-d')); ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm text-slate-500">Notes</label>
                                    <textarea name="transaction_description" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 focus:border-slate-500 focus:outline-none" placeholder="Optional notes"></textarea>
                                </div>
                                <button type="submit" class="w-full rounded-xl bg-emerald-500 py-2 text-white font-medium hover:bg-emerald-600">Save Transaction</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="glass-card rounded-3xl border border-slate-200 p-6 lg:col-span-2">
                        <h2 class="text-lg font-semibold text-slate-800">Account Overview</h2>
                        <div class="mt-4 space-y-4">
                            <?php foreach ($accounts as $account): ?>
                                <div class="border border-slate-200 rounded-2xl p-4 bg-white/60">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <p class="text-sm font-medium text-slate-500 uppercase tracking-wide"><?php echo sanitize($account['type']); ?></p>
                                            <h3 class="text-xl font-semibold text-slate-900"><?php echo sanitize($account['name']); ?></h3>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-slate-500">Balance</p>
                                            <p class="text-xl font-semibold text-emerald-600"><?php echo sanitize(formatCurrency((float)$account['balance'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid grid-cols-2 gap-4 text-sm text-slate-500">
                                        <p>Deposits: <span class="text-emerald-600 font-medium"><?php echo sanitize(formatCurrency((float)$account['total_deposits'])); ?></span></p>
                                        <p>Expenses: <span class="text-rose-500 font-medium"><?php echo sanitize(formatCurrency((float)$account['total_expenses'])); ?></span></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-lg font-semibold text-slate-800">Recent Transactions</h2>
                        <div class="mt-4 space-y-4">
                            <?php if (!$recentTransactions): ?>
                                <p class="text-sm text-slate-500">No transactions yet. Start by logging your income or expenses.</p>
                            <?php endif; ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <div class="flex justify-between items-start border border-slate-200 rounded-2xl p-4 bg-white/60">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800"><?php echo sanitize($transaction['category']); ?></p>
                                        <p class="text-xs text-slate-500 mt-1"><?php echo sanitize($transaction['account_name']); ?> • <?php echo sanitize($transaction['transaction_date']); ?></p>
                                        <?php if (!empty($transaction['description'])): ?>
                                            <p class="text-xs text-slate-500 mt-2"><?php echo sanitize($transaction['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right font-semibold <?php echo $transaction['type'] === 'expense' ? 'text-rose-500' : 'text-emerald-600'; ?>">
                                        <?php echo $transaction['type'] === 'expense' ? '-' : '+'; ?><?php echo sanitize(formatCurrency((float)$transaction['amount'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php elseif ($page === 'transactions'): ?>
                <section class="glass-card rounded-3xl border border-slate-200 p-6 space-y-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-2xl font-semibold text-slate-900">Transactions</h2>
                            <p class="text-sm text-slate-500">Filter and review all your activity.</p>
                        </div>
                        <form method="get" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
                            <input type="hidden" name="page" value="transactions">
                            <div>
                                <label class="block text-xs text-slate-500 uppercase tracking-wide">Account</label>
                                <select name="filter_account" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                                    <option value="">All accounts</option>
                                    <?php foreach ($accountOptions as $option): ?>
                                        <option value="<?php echo (int)$option['id']; ?>" <?php echo ($filters['account_id'] == $option['id']) ? 'selected' : ''; ?>><?php echo sanitize($option['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 uppercase tracking-wide">Type</label>
                                <select name="filter_type" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                                    <option value="all" <?php echo ($filters['type'] === 'all') ? 'selected' : ''; ?>>All</option>
                                    <option value="deposit" <?php echo ($filters['type'] === 'deposit') ? 'selected' : ''; ?>>Deposits</option>
                                    <option value="expense" <?php echo ($filters['type'] === 'expense') ? 'selected' : ''; ?>>Expenses</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 uppercase tracking-wide">From</label>
                                <input type="date" name="filter_from" value="<?php echo sanitize((string)$filters['date_from']); ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 uppercase tracking-wide">To</label>
                                <input type="date" name="filter_to" value="<?php echo sanitize((string)$filters['date_to']); ?>" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-500 focus:outline-none">
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 rounded-xl bg-slate-900 py-2 text-sm font-semibold text-white hover:bg-slate-800">Apply</button>
                                <a href="?page=transactions" class="flex-1 rounded-xl border border-slate-200 py-2 text-sm font-semibold text-slate-600 text-center hover:bg-slate-100">Reset</a>
                            </div>
                        </form>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Account</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Type</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3">Notes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!$transactions): ?>
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No transactions found.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-slate-700"><?php echo sanitize($transaction['transaction_date']); ?></td>
                                        <td class="px-4 py-3 text-slate-700"><?php echo sanitize($transaction['account_name']); ?></td>
                                        <td class="px-4 py-3 text-slate-700"><?php echo sanitize($transaction['category']); ?></td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $transaction['type'] === 'expense' ? 'bg-rose-100 text-rose-600' : 'bg-emerald-100 text-emerald-600'; ?>">
                                                <?php echo sanitize(ucfirst($transaction['type'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold <?php echo $transaction['type'] === 'expense' ? 'text-rose-500' : 'text-emerald-600'; ?>">
                                            <?php echo $transaction['type'] === 'expense' ? '-' : '+'; ?><?php echo sanitize(formatCurrency((float)$transaction['amount'])); ?>
                                        </td>
                                        <td class="px-4 py-3 text-slate-500"><?php echo sanitize($transaction['description'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($page === 'reports'): ?>
                <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-lg font-semibold text-slate-800">Expenses by Category</h2>
                        <p class="text-sm text-slate-500">Identify where your money goes the most.</p>
                        <canvas id="expenseChart" class="mt-6"></canvas>
                    </div>
                    <div class="glass-card rounded-3xl border border-slate-200 p-6">
                        <h2 class="text-lg font-semibold text-slate-800">Monthly Cash Flow</h2>
                        <p class="text-sm text-slate-500">Track deposits against expenses every month.</p>
                        <canvas id="cashFlowChart" class="mt-6"></canvas>
                    </div>
                </section>

                <section class="glass-card rounded-3xl border border-slate-200 p-6">
                    <h2 class="text-lg font-semibold text-slate-800">Category Breakdown</h2>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3 text-right">Total Spent</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (!$expenseByCategory): ?>
                                    <tr>
                                        <td colspan="2" class="px-4 py-6 text-center text-slate-500">No expense data yet.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($expenseByCategory as $item): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-slate-700"><?php echo sanitize($item['category']); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-rose-500">-<?php echo sanitize(formatCurrency((float)$item['total'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <footer class="py-6 text-center text-sm text-slate-500">
            Built with ❤️ using PHP, Tailwind CSS, and SQLite.
        </footer>
    </div>

    <?php if ($page === 'reports'): ?>
    <script>
        const expenseChartData = <?php echo json_encode($expenseChartData, JSON_THROW_ON_ERROR); ?>;
        const cashFlowChartData = <?php echo json_encode($cashFlowChartData, JSON_THROW_ON_ERROR); ?>;

        if (expenseChartData.labels.length) {
            new Chart(document.getElementById('expenseChart'), {
                type: 'doughnut',
                data: {
                    labels: expenseChartData.labels,
                    datasets: [{
                        data: expenseChartData.values,
                        backgroundColor: ['#0f172a', '#475569', '#a855f7', '#22c55e', '#f97316', '#ef4444', '#0ea5e9'],
                        borderWidth: 0,
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#1e293b',
                                usePointStyle: true,
                            }
                        }
                    }
                }
            });
        }

        if (cashFlowChartData.labels.length) {
            new Chart(document.getElementById('cashFlowChart'), {
                type: 'line',
                data: {
                    labels: cashFlowChartData.labels,
                    datasets: [
                        {
                            label: 'Deposits',
                            data: cashFlowChartData.deposits,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.2)',
                            tension: 0.4,
                            fill: true,
                        },
                        {
                            label: 'Expenses',
                            data: cashFlowChartData.expenses,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.15)',
                            tension: 0.4,
                            fill: true,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#475569'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#475569'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: '#1e293b'
                            }
                        }
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>
