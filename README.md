# Trade Journal

## Overview

Trade Journal is a web application that allows users to record and analyze their trading activities. Users can register, log in, record trades, view statistics, and reset their passwords if needed.

## Features

- User registration with email verification
- User login
- Record trades with details such as entry date, exit date, price, quantity, etc.
- View recent trades
- Display trades by date range
- View trade statistics
- Password reset functionality

## Installation

1. **Clone the repository:**
   ```sh
   git clone https://github.com/your-repo/trade-journal.git
   cd trade-journal
   ```

2. **Install dependencies using Composer:**
   ```sh
   composer install
   ```

3. **Set up the database:**
   - Create a database named `trade_one`.
   - Import the `database.sql` file to create the necessary tables.
     ```sh
     mysql -u your_username -p trade_one < database.sql
     ```

4. **Configure the application:**
   - Update the `config.php` file with your database credentials.
   - Note: The `config.php` file now includes a `getDatabaseConnection` function to establish the database connection.

5. **Set up the web server:**
   - Configure your web server to point to the `public` directory.
   - Ensure the `DocumentRoot` and `Directory` directives in your virtual host configuration point to the correct directory.

## Usage

### Registration

1. Navigate to the registration page: `http://journal.hopto.org/register.php`
2. Fill in the registration form with your username, password, and email.
3. Click the "Register" button.
4. Check your email for a verification link and click it to verify your email address.

### Login

1. Navigate to the login page: `http://journal.hopto.org/login.php`
2. Enter your username and password.
3. Click the "Login" button.

### Record Trades

1. After logging in, navigate to the trade entry page: `http://journal.hopto.org/data_entry.php`
2. Fill in the trade details such as entry date, exit date, price, quantity, etc.
3. Click the "Save Trade" button to record the trade.

### View Recent Trades

1. On the trade entry page, scroll down to the "Last Three Trades" section to view your most recent trades.

### Display Trades by Date

1. Navigate to the statistics page: `http://journal.hopto.org/statistics.php`
2. Enter the start date and end date in the "Display Trades by Date" section.
3. Click the "Display" button to view trades within the specified date range.

### View Trade Statistics

1. Navigate to the statistics page: `http://journal.hopto.org/statistics.php`
2. Scroll down to view various trade statistics such as average profit/loss, average won, and average lost.

### View All Trades

1. Navigate to the statistics page: `http://journal.hopto.org/statistics.php`
2. Scroll down to the "All Trades" section to view all recorded trades.

### View Trade Symbols

1. Navigate to the statistics page: `http://journal.hopto.org/statistics.php`
2. Scroll down to the "Trade Symbols" section to view the percentage of trades for each symbol.

### Password Reset

1. Navigate to the forgot password page: `http://journal.hopto.org/forgot_password.php`
2. Enter your email address and click the "Send Reset Link" button.
3. Check your email for a password reset link and click it.
4. Enter your new password and click the "Reset Password" button.

## License

This project is licensed under the MIT License.
# Trade-Journal-2
# Trade-Journal-2
# T-Journal
