# RCCC Attendance System

A facial recognition-based attendance system built with Laravel and CompreFace.

## Features

- Real-time face recognition for attendance tracking
- Member registration with facial data
- Attendance reports and management
- User-friendly interface built with Tailwind CSS
- Real-time notifications and feedback

## Requirements

- PHP 8.1 or higher with extensions:
  - php-mbstring
  - php-xml
  - php-mysql
  - php-curl
  - php-gd
- Laravel 10.x
- MySQL 5.7 or higher
- CompreFace API
- Composer
- Node.js & NPM
- Web camera support
- SSL certificate (recommended for camera access)

## Local Development Setup

1. Clone the repository:
```bash
git clone https://github.com/Wentianuk/rccc-attendence-records.git
cd rccc-attendence-records
```

2. Install PHP dependencies:
```bash
composer install
```

3. Copy the environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Configure your database in `.env` file:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rccc_attendance
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

6. Run database migrations:
```bash
php artisan migrate
```

7. Configure CompreFace API settings in `.env`:
```
COMPREFACE_API_KEY=your_api_key
COMPREFACE_API_URL=your_api_url
```

8. Start the development server:
```bash
php artisan serve
```