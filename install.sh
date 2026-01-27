#!/bin/bash

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Jotunheim Installation Script${NC}"
echo "============================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root${NC}"
    exit 1
fi

# Check OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}Cannot detect OS${NC}"
    exit 1
fi

if [[ "$OS" != "ubuntu" && "$OS" != "debian" ]]; then
    echo -e "${RED}Unsupported OS: $OS. Only Ubuntu and Debian are supported.${NC}"
    exit 1
fi

echo -e "${GREEN}Detected OS: $OS $VERSION${NC}"

# Update system
echo "Updating system packages..."
apt-get update -qq

# Fix any broken packages
echo "Fixing broken packages..."
apt-get --fix-broken install -y -qq || true

# Install required packages
echo "Installing required packages..."
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --fix-missing \
    curl \
    wget \
    git \
    unzip \
    nginx \
    redis-server \
    supervisor \
    php8.2 \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-bcmath \
    php8.2-curl \
    php8.2-dom \
    php8.2-mbstring \
    php8.2-redis \
    php8.2-xml \
    php8.2-zip \
    php8.2-intl \
    php8.2-gd \
    composer

# Install Node.js 20 (required by Vite 7)
echo "Installing Node.js 20..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nodejs

# Install MySQL/MariaDB
echo "Installing MySQL..."
debconf-set-selections <<< "mysql-server mysql-server/root_password password jotunheim"
debconf-set-selections <<< "mysql-server mysql-server/root_password_again password jotunheim"
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --fix-missing mysql-server mariadb-server || true

# Clone repository
INSTALL_DIR="/var/www/jotunheim"
echo "Cloning Jotunheim to $INSTALL_DIR..."
if [ -d "$INSTALL_DIR" ]; then
    echo "Directory already exists, removing..."
    rm -rf "$INSTALL_DIR"
fi
git clone https://github.com/akumalabs/jotunheim.git "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Install PHP dependencies
echo "Installing PHP dependencies..."
composer install --no-interaction --optimize-autoloader

# Install NPM dependencies
echo "Installing NPM dependencies..."
npm install

# Build frontend assets
echo "Building frontend assets..."
npm run build

# Copy environment file
if [ ! -f .env ]; then
    echo "Creating .env file..."
    cp .env.example .env

    # Generate app key
    php artisan key:generate

    # Configure .env
    sed -i "s/DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/" .env
    sed -i "s/DB_DATABASE=jotunheim/DB_DATABASE=jotunheim/" .env
    sed -i "s/DB_USERNAME=jotunheim/DB_USERNAME=root/" .env
    sed -i "s/DB_PASSWORD=/DB_PASSWORD=jotunheim/" .env

    # Create database
    echo "Creating database..."
    mysql -uroot -pjotunheim -e "CREATE DATABASE IF NOT EXISTS jotunheim;"

    # Run migrations
    echo "Running database migrations..."
    php artisan migrate --seed --force
fi

# Set permissions
echo "Setting file permissions..."
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR/storage"
chmod -R 755 "$INSTALL_DIR/bootstrap/cache"

# Configure Nginx
echo "Configuring Nginx..."
cat > /etc/nginx/sites-available/jotunheim <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name _;
    root /var/www/jotunheim/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

ln -sf /etc/nginx/sites-available/jotunheim /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl restart nginx

# Configure Supervisor
echo "Configuring Supervisor..."
cat > /etc/supervisor/conf.d/jotunheim-worker.conf <<'EOF'
[program:jotunheim-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/jotunheim/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/jotunheim/storage/logs/worker.log
stopwaitsecs=3600
EOF

supervisorctl reread
supervisorctl update
supervisorctl start jotunheim-worker:*

# Configure Redis
echo "Configuring Redis..."
systemctl enable redis-server
systemctl start redis-server

# Configure PHP-FPM
echo "Configuring PHP-FPM..."
systemctl enable php8.2-fpm
systemctl restart php8.2-fpm

echo ""
echo -e "${GREEN}=============================${NC}"
echo -e "${GREEN}Installation Complete!${NC}"
echo -e "${GREEN}=============================${NC}"
echo ""
echo "Your Jotunheim panel is now installed."
echo ""
echo "Default credentials:"
echo "  Email: admin@jotunheim.local"
echo "  Password: Password123!"
echo ""
echo "Access your panel at: http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Next steps:"
echo "  1. Update your .env file with your Proxmox credentials"
echo "  2. Configure your domain in Nginx"
echo "  3. Enable SSL with Let's Encrypt: certbot --nginx -d yourdomain.com"
echo ""
echo "Thank you for installing Jotunheim!"
