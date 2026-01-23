# Production Deployment Guide

## Server Requirements

- Ubuntu 22.04 LTS or Debian 12
- PHP 8.2+ with extensions: bcmath, ctype, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml, curl, redis
- MySQL 8.0+ or MariaDB 10.6+
- Redis 6+
- Nginx or Apache
- Node.js 18+ (for building)
- Composer 2+

## Installation Steps

### 1. Install System Dependencies

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP and extensions
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
    php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath

# Install MySQL
sudo apt install -y mysql-server

# Install Redis
sudo apt install -y redis-server

# Install Nginx
sudo apt install -y nginx

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. Create Database

```bash
sudo mysql -e "CREATE DATABASE midgard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'midgard'@'localhost' IDENTIFIED BY 'your_secure_password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON midgard.* TO 'midgard'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### 3. Clone and Configure

```bash
cd /var/www
git clone https://github.com/yourorg/midgard.git
cd midgard

# Set permissions
sudo chown -R www-data:www-data /var/www/midgard
sudo chmod -R 755 /var/www/midgard
sudo chmod -R 775 storage bootstrap/cache

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Configure environment
cp .env.example .env
php artisan key:generate
```

### 4. Edit .env

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://midgard.yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=midgard
DB_USERNAME=midgard
DB_PASSWORD=your_secure_password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 5. Run Migrations

```bash
php artisan migrate --force --seed
```

### 6. Optimize Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### 7. Configure Nginx

Create `/etc/nginx/sites-available/midgard`:

```nginx
server {
    listen 80;
    server_name midgard.yourdomain.com;
    root /var/www/midgard/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/midgard /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 8. Configure SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d midgard.yourdomain.com
```

### 9. Configure Queue Worker (Supervisor)

Create `/etc/supervisor/conf.d/midgard-worker.conf`:

```ini
[program:midgard-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/midgard/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/midgard/storage/logs/worker.log
stopwaitsecs=3600
```

Enable:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start midgard-worker:*
```

## Updating

```bash
cd /var/www/midgard

# Pull updates
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Run migrations
php artisan migrate --force

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart workers
sudo supervisorctl restart midgard-worker:*
```

## Default Credentials

After installation:
- Admin: `admin@midgard.local` / `password`
- User: `user@midgard.local` / `password`

**Change these immediately after first login!**

## Troubleshooting

### Permission Issues
```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
```
