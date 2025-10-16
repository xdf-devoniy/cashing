# Cashing Expense Tracker

A lightweight PHP + SQLite expense tracking application with a modern Tailwind UI. Quickly log deposits and expenses across multiple accounts, including cash, bank, and Hamkor (or any custom account you add), and visualize your spending trends with interactive charts.

## Features

- 📊 Dashboard with total balances, account summaries, and recent activity.
- 💼 Manage unlimited accounts (cash, bank, savings, Hamkor, etc.).
- 🧾 Record deposits and expenses with categories, notes, and dates.
- 🔍 Filterable transactions view with date range and account filters.
- 📈 Reports page featuring expense breakdowns and monthly cash-flow charts powered by Chart.js.
- 💾 Auto-initialized SQLite database seeded with common accounts.

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

- `index.php` – Single entry point containing the UI and routing between dashboard, transactions, and reports.
- `functions.php` – Data-access helper functions for accounts, transactions, and reporting metrics.
- `database.php` – Initializes the SQLite database and seeds default accounts.
- `data/` – Stores the generated SQLite database (gitignored).

## Tailwind & Chart.js

Tailwind CSS and Chart.js are loaded from CDNs to keep the project lightweight. The UI uses a light, glassmorphic style for a modern feel.

## License

This project is provided as-is without warranty.
