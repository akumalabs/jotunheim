#!/bin/bash

# Midgard Control Panel Auto Installer
# Usage: curl -sSL https://raw.githubusercontent.com/akumalabs/midgard-panel/main/install.sh | sudo bash

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Variables
INSTALL_DIR="/var/www/midgard"
DB_NAME="midgard"
DB_USER="midgard"
DB_PASS=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)
ADMIN_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 12)

# Banner
clear
echo -e "${CYAN}"
echo "  __  __ _     _                     _ "
echo " |  \/  (_)   | |                   | |"
echo " | \  / |_  __| | __ _  __ _ _ __ __| |"
echo " | |\/| | |/ _\` |/ _\` |/ _\` | '__/ _\` |"
echo " | |  | | | (_| | (_| | (_| | | | (_| |"
echo " |_|  |_|_|\__,_|\__, |\__,_|_|  \__,_|"
echo "                  __/ |                "
echo "                 |___/   Control Panel"
echo -e "${NC}"
echo ""

# Check root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Error: Please run as root (sudo)${NC}"
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}Error: Cannot detect OS${NC}"
    exit 1
fi

echo -e "${GREEN}➜${NC} Detected: $OS $VERSION"

# Only support Ubuntu/Debian
if [[ "$OS" != "ubuntu" && "$OS" != "debian" ]]; then
    echo -e "${RED}Error: Only Ubuntu and Debian are supported${NC}"
    exit 1
fi

echo -e "${GREEN}➜${NC} Starting installation..."
echo ""

# Update system
echo -e "${BLUE}[1/8]${NC} Updating system packages..."
apt update -qq
apt upgrade -y -qq

# Detect and purge Apache2 if installed (common source of conflicts)
if dpkg -l | grep -q apache2; then
    echo -e "${YELLOW}[Warn]${NC} Apache2 detected. Purging to prevent port 80 conflicts..."
    systemctl stop apache2 2>/dev/null || true
    apt purge -y apache2 apache2-utils apache2-bin apache2.2-common
    apt autoremove -y
fi

# Install dependencies
echo -e "${BLUE}[2/8]${NC} Installing PHP 8.2 and extensions..."
apt install -y -qq --no-install-recommends software-properties-common curl gnupg2

# Add PHP repository if needed
if ! command -v php8.2 &> /dev/null; then
    if [ "$OS" = "ubuntu" ]; then
        add-apt-repository -y ppa:ondrej/php
    else
        echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
        curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/php.gpg
    fi
    apt update -qq
fi

apt install -y -qq --no-install-recommends php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
    php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-bcmath \
    php8.2-gd php8.2-intl

# Install other services
echo -e "${BLUE}[3/8]${NC} Installing database, Redis, Nginx, and Supervisor..."

# Debian uses MariaDB, Ubuntu uses MySQL
if [ "$OS" = "debian" ]; then
    apt install -y -qq --no-install-recommends mariadb-server redis-server nginx supervisor git unzip lsof psmisc
else
    apt install -y -qq --no-install-recommends mysql-server redis-server nginx supervisor git unzip lsof psmisc
fi

# Install Composer
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Install Node.js 20 (Vite 7 requires Node 20+)
if ! command -v node &> /dev/null || [[ $(node -v | cut -d. -f1 | tr -d 'v') -lt 20 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt install -y -qq nodejs
fi

# Configure MySQL
echo -e "${BLUE}[4/8]${NC} Configuring database..."
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# Reset user to ensure clean state
mysql -e "DROP USER IF EXISTS '${DB_USER}'@'localhost';"
mysql -e "DROP USER IF EXISTS '${DB_USER}'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"

# Create user for both localhost (socket) and 127.0.0.1 (TCP)
mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"

# Grant privileges
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"

# Clone repository
echo -e "${BLUE}[5/8]${NC} Downloading Midgard..."
if [ -d "$INSTALL_DIR" ]; then
    rm -rf "$INSTALL_DIR"
fi
git clone -q https://github.com/akumalabs/midgard-panel.git "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Install PHP dependencies
# Error handling
trap 'echo -e "${RED}Installation failed at step: $BASH_COMMAND${NC}"; exit 1' ERR

# Install PHP dependencies
echo -e "${BLUE}[6/8]${NC} Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet

# Install Node dependencies and build
# Install Node dependencies and build
echo "Running npm install..."
npm install
echo "Fixing permissions for build tools..."
chmod +x node_modules/.bin/*
echo "Running npm run build..."
npm run build

# Get server IP early for domain prompt
SERVER_IP=$(hostname -I | awk '{print $1}')
if [ -z "$SERVER_IP" ]; then
    # Fallback to ip command if hostname -I fails
    SERVER_IP=$(ip route get 1 | awk '{print $(NF-2);exit}')
fi

# Prompt for Domain
echo ""
echo -e "${YELLOW}Enter your domain name (e.g., panel.example.com)${NC}"
echo -e "${YELLOW}Or press ENTER to use IP address: ${CYAN}$SERVER_IP${NC}"
read -p "Domain: " USER_DOMAIN < /dev/tty

if [ -z "$USER_DOMAIN" ]; then
    APP_URL="http://${SERVER_IP}"
    SERVER_NAME="_"
    echo -e "${GREEN}✓${NC} Using IP-based access: ${CYAN}$APP_URL${NC}"
else
    APP_URL="https://${USER_DOMAIN}"
    SERVER_NAME="${USER_DOMAIN}"
    echo -e "${GREEN}✓${NC} Using domain: ${CYAN}$USER_DOMAIN${NC}"
fi

# Configure Laravel
echo -e "${BLUE}[7/8]${NC} Configuring application..."
cp .env.example .env

# Update .env BEFORE generating key
sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
sed -i "s|APP_URL=.*|APP_URL=${APP_URL}|" .env
sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/DB_HOST=.*/DB_HOST=127.0.0.1/" .env
sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
sed -i "s/CACHE_STORE=.*/CACHE_STORE=redis/" .env
sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=redis/" .env

# Generate key after .env is configured
php artisan key:generate --no-interaction --quiet

# Clear any cached config
php artisan config:clear --quiet 2>/dev/null || true

# Run migrations
php artisan migrate:fresh --force --seed --quiet

# Set permissions
chown -R www-data:www-data storage bootstrap/cache public/build
chmod -R 775 storage bootstrap/cache public/build

# Optimize
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan storage:link --quiet 2>/dev/null || true

# Configure Nginx
echo -e "${BLUE}[8/8]${NC} Configuring web server..."

cat > /etc/nginx/sites-available/midgard << 'NGINX'
server {
    listen 80 default_server;
    # listen [::]:80 default_server; # Commented out to prevent errors on systems with IPv6 disabled
    server_name ${SERVER_NAME};
    root /var/www/midgard/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';";

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
NGINX

# Enable site
rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/midgard /etc/nginx/sites-enabled/

# Test Nginx config before restart
echo -e "${BLUE}[Debug]${NC} Testing Nginx configuration..."
nginx -t

# Restart services
systemctl restart php8.2-fpm
systemctl restart redis-server

# Ensure port 80 is free
echo -e "${BLUE}[Debug]${NC} Checking for port 80 conflicts..."
if command -v lsof >/dev/null; then
    pid=$(lsof -t -i:80)
    if [ -n "$pid" ]; then
        echo -e "${YELLOW}[Warn]${NC} Process $pid is using port 80. Killing it..."
        kill -9 $pid || true
        sleep 3
    fi
else
    # Fallback if lsof is missing (should be installed now, but just in case)
    fuser -k 80/tcp || true
    sleep 3
fi

# Double check
if lsof -t -i:80 >/dev/null; then
    echo -e "${RED}[Error]${NC} Port 80 is still in use! Cannot proceed."
    lsof -i:80
    exit 1
fi

echo -e "${BLUE}[Debug]${NC} Starting Nginx..."
if ! systemctl restart nginx; then
    echo -e "${RED}[Error]${NC} Nginx failed to start. Dumping logs:"
    journalctl -xeu nginx.service --no-pager | tail -n 50
    echo -e "${RED}[Error]${NC} Checking config again:"
    nginx -t
    exit 1
fi

# Enable services (mariadb on Debian, mysql on Ubuntu)
if [ "$OS" = "debian" ]; then
    systemctl enable php8.2-fpm nginx redis-server mariadb
else
    systemctl enable php8.2-fpm nginx redis-server mysql
fi

# SSL Setup
if [ "$SERVER_NAME" != "_" ]; then
    echo ""
    echo -e "${YELLOW}Would you like to set up SSL with Let's Encrypt now? (y/n)${NC}"
    echo -e "${YELLOW}Note: Your domain must be pointing to this server's IP for this to work.${NC}"
    read -p "Answer: " OBTAIN_SSL < /dev/tty

    if [[ "$OBTAIN_SSL" =~ ^[Yy]$ ]]; then
        echo -e "${BLUE}Installing Certbot...${NC}"
        apt install -y -qq --no-install-recommends certbot python3-certbot-nginx

        echo -e "${BLUE}Obtaining certificate for $SERVER_NAME...${NC}"
        
        # Disable ERR trap temporarily for certbot (SSL is optional)
        set +e
        certbot --nginx -d "$SERVER_NAME" --non-interactive --agree-tos -m "admin@$SERVER_NAME" --redirect
        CERTBOT_EXIT=$?
        set -e
        
        if [ $CERTBOT_EXIT -ne 0 ]; then
            echo -e "${RED}✗${NC} SSL setup failed. This is usually because:"
            echo -e "  ${YELLOW}•${NC} Your domain DNS is not pointing to this server yet"
            echo -e "  ${YELLOW}•${NC} Port 80/443 is not accessible from the internet"
            echo -e "  ${YELLOW}•${NC} There's a firewall blocking Let's Encrypt validation"
            echo ""
            echo -e "${CYAN}You can set up SSL manually later using:${NC}"
            echo -e "  ${GREEN}certbot --nginx -d $SERVER_NAME${NC}"
            echo ""
            echo -e "${YELLOW}For now, access the panel via: http://$SERVER_NAME${NC}"
            
            # Update APP_URL back to HTTP since SSL failed
            sed -i "s|APP_URL=.*|APP_URL=http://$SERVER_NAME|" /var/www/midgard/.env
            php -r "echo 'Clearing config cache...'; exec('cd /var/www/midgard && php artisan config:clear --quiet');"
        else
            echo -e "${GREEN}✓${NC} SSL certificate obtained successfully!"
            APP_URL="https://$SERVER_NAME"
        fi
    else
        echo -e "${YELLOW}Skipping SSL setup. You can set it up later with: certbot --nginx -d $SERVER_NAME${NC}"
    fi
fi

# Output results
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          Installation Complete!                           ║${NC}"
echo -e "${GREEN}╠═══════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}  URL        │  ${APP_URL}                          ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  Username   │  admin@midgard.local                         ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  Password   │  password                                    ${GREEN}║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${YELLOW}⚠  Change the admin password immediately after login!${NC}"
echo -e 
echo -e "Documentation: ${BLUE}https://github.com/akumalabs/Midgard${NC}"
