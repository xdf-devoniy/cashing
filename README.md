# Cashing Expense Tracker

A lightweight PHP + SQLite expense tracking application with a modern Tailwind UI. Quickly log deposits and expenses across multiple accounts, including cash, bank, and Hamkor (or any custom account you add), and visualize your spending trends with interactive charts. Amounts are displayed in Uzbek so'm (UZS) by default.

## Features

- 📊 Dashboard with total balances, account summaries, and recent activity.
- 💼 Manage unlimited accounts (cash, bank, savings, Hamkor, etc.) with full CRUD.
- 🧾 Record, edit, and delete deposits and expenses tied to reusable spending categories.
- 🏷️ Build your own category taxonomy (including colors) and reuse it while logging transactions.
- 🔍 Filterable transactions view with account, category, type, and date range filters.
- 📈 Reports page featuring date-range filters, focused insights, and monthly cash-flow charts powered by Chart.js.
- 💾 Auto-initialized SQLite database seeded with common accounts and starter categories.
- 🔄 Automatic migrations upgrade legacy databases by converting free-text categories into the structured category system.

## Getting Started

1. Ensure you have PHP 8+ installed with SQLite support.
2. Clone the repository and install dependencies (none required beyond PHP).
3. Start the built-in PHP development server:

   ```bash
   php -S localhost:8000
   ```

4. Visit `http://localhost:8000/index.php` in your browser.

The first run will create a `data/database.sqlite` file and seed default accounts for you.

## Project Structure

- `index.php` – Single entry point containing the UI and routing between dashboard, accounts, categories, transactions, and reports.
- `functions.php` – Data-access helper functions for accounts, categories, transactions, and reporting metrics.
- `database.php` – Initializes the SQLite database, runs lightweight migrations, and seeds default accounts and categories.
- `data/` – Stores the generated SQLite database (gitignored).

## Tailwind & Chart.js

Tailwind CSS and Chart.js are loaded from CDNs to keep the project lightweight. The UI uses a light, glassmorphic style for a modern feel.

## License

This project is provided as-is without warranty.
