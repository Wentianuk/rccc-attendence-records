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

## Server Deployment Guide

### 1. Initial Setup

1. Clone the repository on your server:
```bash
git clone https://github.com/Wentianuk/rccc-attendence-records.git
cd rccc-attendence-records
```

2. Install dependencies:
```bash
composer install
```

3. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

### 2. Database Setup

1. Create a new MySQL database
2. Update `.env` with your database credentials:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rccc_attendance
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password
```

3. Run migrations:
```bash
php artisan migrate
```

### 3. File Permissions

Set proper permissions for Laravel:
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 4. Web Server Configuration

#### Apache Configuration
Create a virtual host configuration (`/etc/apache2/sites-available/rccc-attendance.conf`):
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/rccc-attendence-records/public
    
    <Directory /path/to/rccc-attendence-records/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/rccc-attendance-error.log
    CustomLog ${APACHE_LOG_DIR}/rccc-attendance-access.log combined
</VirtualHost>
```

Enable the site:
```bash
a2ensite rccc-attendance.conf
systemctl restart apache2
```

#### Nginx Configuration
Create a server block (`/etc/nginx/sites-available/rccc-attendance`):
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/rccc-attendence-records/public;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Enable the site:
```bash
ln -s /etc/nginx/sites-available/rccc-attendance /etc/nginx/sites-enabled/
systemctl restart nginx
```

### 5. SSL Configuration (Recommended)

Install SSL certificate using Certbot:

For Apache:
```bash
certbot --apache -d your-domain.com
```

For Nginx:
```bash
certbot --nginx -d your-domain.com
```

### 6. CompreFace Setup

1. Install and configure CompreFace on your server
2. Update `.env` with CompreFace settings:
```
COMPREFACE_API_KEY=your_api_key
COMPREFACE_API_URL=your_api_url
```

## Usage

1. Access the system through your web browser
2. Start the face recognition system using the "Start Recognition" button
3. Register new members using the registration form
4. View attendance records and reports through the management interface

## Troubleshooting

1. If you encounter permission issues:
   - Check storage and cache directory permissions
   - Ensure web server user has proper access

2. If face recognition doesn't work:
   - Verify CompreFace API is accessible
   - Check SSL certificate for camera access
   - Confirm browser permissions for camera

3. For database issues:
   - Verify database credentials
   - Check database connection settings
   - Ensure migrations are up to date

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).