
## Prerequisites

- PHP >= 8.2
- Composer
- Node.js & NPM
- MySQL
- Pusher account (for WebSocket broadcasting)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd assessment-backend
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update the `.env` file with your configuration:

```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Pusher Configuration (for WebSockets)
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

# Queue Configuration
QUEUE_CONNECTION=database
```

### 4. Database Setup

```bash
php artisan migrate
php artisan db:seed
```

The seeder will create:
- Sample assets (BTC/USDT, ETH/USDT, etc.)
- Test users with initial balances

### 5. Build Assets

```bash
npm run build
```

## Running the Application

### Quick Start (Development)

Use the composer script to run all services concurrently:

```bash
composer dev
```

This will start:
- Laravel development server (http://localhost:8000)



```
## Testing

### Run All Tests

```bash
php artisan test
```

### Run Specific Test Suites

```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit

# Specific test file
php artisan test tests/Unit/OrderMatchingServiceTest.php
```


```


