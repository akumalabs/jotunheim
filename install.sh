#!/bin/bash

# Continue on error, but track failures
ERRORS=0
trap 'ERRORS=$((ERRORS+1))' ERR

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

# Pre-flight check: Make sure we have enough disk space
echo "Checking disk space..."
DISK_AVAIL=$(df -BG / | tail -1 | awk '{print $4}' | sed 's/G//')
if [ "$DISK_AVAIL" -lt 10 ]; then
    echo -e "${RED}ERROR: Less than 10GB disk space available. Please free up space.${NC}"
    exit 1
fi
echo -e "${GREEN}Disk space check passed: ${DISK_AVAIL}GB available${NC}"

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
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --fix-missing mariadb-server mariadb-client || true

# Start MySQL service
echo "Starting MySQL service..."
systemctl start mariadb || systemctl start mysql
systemctl enable mariadb || systemctl enable mysql

# Wait for MySQL to be ready
echo "Waiting for MySQL to be ready..."
for i in {1..30}; do
    if /usr/bin/mysql -uroot -pjotunheim -e "SELECT 1" >/dev/null 2>&1; then
        echo "MySQL is ready!"
        break
    fi
    echo "Waiting for MySQL... ($i/30)"
    sleep 2
done

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
    if [ -f .env.example ]; then
        echo "Creating .env file..."
        cp .env.example .env
    else
        echo -e "${RED}ERROR: .env.example not found!${NC}"
        exit 1
    fi

    # Generate app key
    php artisan key:generate

    # Generate secure database password
    DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-24)

    # Configure .env with dedicated database user
    sed -i "s/DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/" .env
    sed -i "s/DB_DATABASE=jotunheim/DB_DATABASE=jotunheim/" .env
    sed -i "s/DB_USERNAME=jotunheim/DB_USERNAME=jotunheim/" .env
    sed -i "s/DB_PASSWORD=/DB_PASSWORD=$DB_PASSWORD/" .env

    # Create database and user
    echo "Setting up database..."
    if command -v mysql &> /dev/null; then
        if mysql -uroot -pjotunheim -e "
            CREATE DATABASE IF NOT EXISTS jotunheim;
            CREATE USER IF NOT EXISTS 'jotunheim'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
            GRANT ALL PRIVILEGES ON jotunheim.* TO 'jotunheim'@'localhost';
            FLUSH PRIVILEGES;
        " 2>/dev/null; then
            echo "Database and user created successfully!"
        else
            echo "WARNING: Failed to create database automatically."
            echo "Please run manually:"
            echo "  mysql -uroot -pjotunheim"
            echo "  CREATE DATABASE jotunheim;"
            echo "  CREATE USER 'jotunheim'@'localhost' IDENTIFIED BY 'your_password';"
            echo "  GRANT ALL PRIVILEGES ON jotunheim.* TO 'jotunheim'@'localhost';"
            echo "  FLUSH PRIVILEGES;"
            echo "Then update .env with the correct DB_PASSWORD"
        fi
    elif command -v mariadb &> /dev/null; then
        if mariadb -uroot -pjotunheim -e "
            CREATE DATABASE IF NOT EXISTS jotunheim;
            CREATE USER IF NOT EXISTS 'jotunheim'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
            GRANT ALL PRIVILEGES ON jotunheim.* TO 'jotunheim'@'localhost';
            FLUSH PRIVILEGES;
        " 2>/dev/null; then
            echo "Database and user created successfully!"
        else
            echo "WARNING: Failed to create database automatically."
            echo "Please run manually:"
            echo "  mariadb -uroot -pjotunheim"
            echo "  CREATE DATABASE jotunheim;"
            echo "  CREATE USER 'jotunheim'@'localhost' IDENTIFIED BY 'your_password';"
            echo "  GRANT ALL PRIVILEGES ON jotunheim.* TO 'jotunheim'@'localhost';"
            echo "  FLUSH PRIVILEGES;"
            echo "Then update .env with the correct DB_PASSWORD"
        fi
    else
        echo "WARNING: Neither mysql nor mariadb command found."
        echo "Please create database and user manually, then update .env"
    fi

    # Run migrations
    echo "Running database migrations..."
    php artisan migrate --seed --force || echo "WARNING: Migrations failed, please check database connection in .env"
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

# Final verification
echo ""
echo -e "${YELLOW}Verifying installation...${NC}"
echo ""

# Check services
SERVICES_OK=true
for service in nginx php8.2-fpm redis-server; do
    if systemctl is-active --quiet "$service"; then
        echo -e "${GREEN}✓ $service is running${NC}"
    else
        echo -e "${RED}✗ $service is NOT running${NC}"
        SERVICES_OK=false
    fi
done

# Check MySQL/MariaDB
if command -v mysql &> /dev/null || command -v mariadb &> /dev/null; then
    echo -e "${GREEN}✓ MySQL/MariaDB is installed${NC}"
else
    echo -e "${RED}✗ MySQL/MariaDB is NOT installed or not in PATH${NC}"
    SERVICES_OK=false
fi

# Check application
if [ -f "$INSTALL_DIR/public/index.php" ]; then
    echo -e "${GREEN}✓ Application files are present${NC}"
else
    echo -e "${RED}✗ Application files are missing${NC}"
    SERVICES_OK=false
fi

if [ -f "$INSTALL_DIR/.env" ]; then
    echo -e "${GREEN}✓ .env file exists${NC}"
else
    echo -e "${YELLOW}⚠ .env file not found${NC}"
fi

echo ""
echo -e "${GREEN}=============================${NC}"
if [ "$ERRORS" -eq 0 ] && [ "$SERVICES_OK" = true ]; then
    echo -e "${GREEN}Installation Complete!${NC}"
else
    echo -e "${YELLOW}Installation completed with warnings/errors${NC}"
    echo -e "${RED}Errors encountered: $ERRORS${NC}"
fi
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
if [ "$ERRORS" -gt 0 ] || [ "$SERVICES_OK" = false ]; then
    echo -e "${YELLOW}IMPORTANT: Please check the warnings above and fix any issues manually.${NC}"
    echo ""
fi
echo "Thank you for installing Jotunheim!"
