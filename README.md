# Jotunheim

> A modern, premium Proxmox VE control panel built with Laravel and Vue 3

## Documentation

- [Installation Guide](#installation)
- [Quick Start Guide](#quick-start)
- [API Documentation](#api-documentation)
- [Configuration Guide](#configuration)
- [Server Management](#server-management)

## Key Features

- **Modern UI/UX**: Clean, responsive interface
- **Data-Dense Layouts**: Information-rich tables and dashboards
- **Real-Time Updates**: Live status and metric polling
- **Advanced Server Management**:
  - Async Creation: Background provisioning for resilience
  - Cloud-Init Integration: Automated configuration
  - Power Controls: Start, Stop, Restart, Kill
  - Resource Management: Dynamic resizing of Disk, CPU, and RAM
  - Snapshots: Create, Rollback, Delete VM snapshots
  - Backups: Integrated Proxmox backup management
- Node Management: Add, configure, and monitor Proxmox VE nodes
  - IP Management: IPv4/IPv6 address pools with automated assignment
  - ISO Management: Mount/Unmount ISOs directly from panel
  - NoVNC Console: Secure, embedded browser-based VM access
- - Firewall: Manage Proxmox firewall rules
- - Guest Agent: Execute commands inside VM, network info, OS info

## Tech Stack

### Backend
- Laravel 12 (latest)
- MySQL 8+ / MariaDB 10.6+
- Laravel Sanctum (API Authentication)
- Redis (Queue & Caching)

### Frontend
- Vue 3 (Composition API)
- Tailwind CSS
- Pinia (State Management)
- TanStack Query (Smart Data Fetching)

### Proxmox
- Proxmox VE 7.0+ (Recommended)

## Requirements

### Minimum Hardware
| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 2 cores | 4+ cores |
| RAM | 2 GB | 4+ GB |
| Disk | 30 GB | 50+ GB SSD |

### Software Requirements
| Software | Version | Notes |
|----------|---------|-------|
| OS | Ubuntu 22.04 / Debian 12 | Required |
| PHP | 8.2+ | Extensions: bcmath, curl, dom, mbstring, mysql, redis, xml, zip |
| MySQL | 8.0+ | Or MariaDB 10.6+ |
| Nginx | 1.24+ | Required |
| Redis | 6.0+ | Required for queue |
| Node.js | 18+ | Required for frontend build |
| Composer | 2.x | Required |

## Repository

**Main Repository**: https://github.com/akumalabs/jotunheim

**Demo**: https://jotunheim.yourdomain.com

## Quick Start

### One-Line Install (Ubuntu/Debian)

```bash
curl -sSL https://raw.githubusercontent.com/akumalabs/jotunheim/main/install.sh | sudo bash
```

This command will:
- Clone repository
- Install all dependencies
- Configure environment
- Set up database
- Install Nginx configuration
- Start the application

### Manual Install

Clone repository:
```bash
git clone https://github.com/akumalabs/jotunheim.git
cd jotunheim
```

Install dependencies:
```bash
composer install --no-interaction --optimize-autoloader
npm install
npm run build
```

Configure environment:
```bash
cp .env.example .env
nano .env  # Configure database, Redis, and Proxmox
php artisan key:generate
php artisan migrate --seed
```

Start development servers:
```bash
# Terminal 1: Backend
php artisan serve

# Terminal 2: Frontend
npm run dev

# Terminal 3: Queue Worker
php artisan queue:work
```

---

## Installation

### Prerequisites

Before installing Jotunheim, ensure your server has:

1. **Operating System**: Ubuntu 22.04 or Debian 12
2. **PHP**: 8.2 or higher with required extensions
3. **Composer**: 2.x or higher
4. **MySQL**: 8.0+ or MariaDB 10.6+
5. **Redis**: 6.0 or higher
6. **Nginx**: 1.24+ for production
7. **Node.js**: 18+ or higher for frontend
8. **Proxmox VE**: 7.0+ (Recommended) with API Token

### System Requirements

The server running Jotunheim must have access to:

- Proxmox VE server(s) with API Token privileges
- Storage for ISO files and backups
- Network connectivity for API access

### Step 1: Clone Repository

```bash
git clone https://github.com/akumalabs/jotunheim.git
cd jotunheim
```

### Step 2: Install PHP Dependencies

```bash
# Install Composer dependencies
composer install --no-interaction --optimize-autoloader
```

### Step 3: Install Frontend Dependencies

```bash
# Install NPM dependencies
npm install

# Build frontend assets
npm run build
```

### Step 4: Configure Environment

```bash
# Copy environment configuration
cp .env.example .env

# Edit .env to configure your settings
nano .env
```

Key configuration options in `.env`:

```env
# Application
APP_NAME="Jotunheim"
APP_URL="https://your-domain.com"
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jotunheim
DB_USERNAME=jotunheim
DB_PASSWORD=your_secure_password

# Proxmox
PROXMOX_VERIFY_SSL=true
PROXMOX_API_TIMEOUT=30
PROXMOX_CONNECT_TIMEOUT=5
```

### Step 5: Generate Application Key

```bash
php artisan key:generate
```

### Step 6: Run Database Migrations

```bash
php artisan migrate --seed
```

### Step 7: Set File Permissions

```bash
# Ensure proper ownership for web server
sudo chown -R www-data:www-data /var/www/jotunheim
sudo chmod -R 755 /var/www/jotunheim/public/storage
sudo chmod -R 755 /var/www/jotunheim/bootstrap/cache
```

### Step 8: Configure Nginx

Create Nginx configuration:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name jotunheim.your-domain.com;
    root /var/www/jotunheim/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param REQUEST_METHOD $request_method;
        fastcgi_param QUERY_STRING $query_string;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_param DOCUMENT_ROOT $document_root;
        fastcgi_param PATH_INFO $path_info;

        # Pass to PHP-FPM
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ \.php$ {
        deny all;
    }
}
```

Enable site:

```bash
sudo ln -s /etc/nginx/sites-available/jotunheim /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### Step 9: Configure Supervisor (Production)

Create supervisor configuration for queue workers:

```ini
[program:jotunheim-worker]
command=php /var/www/jotunheim/artisan queue:work
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/jotunheim-worker.log
stopwaitsecs=10
```

Start supervisor:

```bash
sudo supervisorctl update
sudo supervisorctl start jotunheim-worker:*
```

---

## Quick Start

### 1. Add a Node

Log in to the admin panel and navigate to **Nodes → Add Node**.

Provide:
- Node Name: `pve1`
- Hostname: `pve1.yourdomain.com`
- API Token: User token with appropriate privileges
- Cluster Name: `cluster1`

The system will automatically fetch and display node statistics.

### 2. Add a Template

Navigate to **Templates → Add Template**.

Provide:
- Template Name: `Ubuntu 22.04 Cloud-Init`
- VMID: The source VM ID from Proxmox
- Node: Select the node where template is located
- Image URL: URL to the cloud-init enabled image

The template will be available for server deployment.

### 3. Add an IP Pool

Navigate to **IP Pools → Add Pool**.

Provide:
- Pool Name: `pool1`
- Gateway: `192.168.1.1`
- Netmask: `255.255.255.0`
- Start IP: `192.168.1.10`
- End IP: `192.168.1.254`
- CIDR: `/24`
- Node: Select the node for this pool

### 4. Create a Server

Navigate to **Servers → Add Server**.

Provide:
- Server Name: `my-server`
- User: Select a user from dropdown
- Template: Select a template to install
- Node: Auto-selects based on availability
- CPU: 2 cores
- Memory: 2 GB
- Disk: 30 GB
- IP Pool: Select an IP pool
- Bandwidth: 10000 GB

The server will be created asynchronously in the background.

### 5. Access Server Console

Navigate to **Servers → All Servers → Select Server → Console**.

Click **Launch Console** to open the NoVNC console in a new browser tab.

### 6. View Server Status

Navigate to **Servers → All Servers → Select Server → Status**.

View real-time CPU, memory, disk, and network usage metrics.

---

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_NAME` | Application name | Jotunheim |
| `APP_ENV` | Environment (local/production) | local |
| `APP_URL` | Application URL | http://localhost |
| `APP_DEBUG` | Debug mode | true |
| `DB_CONNECTION` | Database type | mysql |
| `DB_HOST` | Database host | 127.0.0.1 |
| `DB_PORT` | Database port | 3306 |
| `DB_DATABASE` | Database name | jotunheim |
| `DB_USERNAME` | Database user | jotunheim |
| `DB_PASSWORD` | Database password | (generated) |
| `BROADCAST_DRIVER` | Broadcast driver | log |
| `CACHE_DRIVER` | Cache driver | redis |
| `QUEUE_CONNECTION` | Queue driver | redis |
| `SESSION_DRIVER` | Session driver | redis |
| `PROXMOX_VERIFY_SSL` | Verify SSL for Proxmox API | true |
| `PROXMOX_API_TIMEOUT` | Proxmox API timeout (seconds) | 30 |
| `PROXMOX_CONNECT_TIMEOUT` | Proxmox connect timeout (seconds) | 5 |

### Application Settings

| Setting | Description | Default |
|---------|-------------|---------|
| `BACKUP_MAX_BACKUPS` | Maximum backups per server | 5 |
| `SERVER_DEFAULT_PASSWORD_LENGTH` | Default password length | 16 |
| `SERVER_MAX_PASSWORD_LENGTH` | Maximum password length | 72 |
| `SERVER_MIN_PASSWORD_LENGTH` | Minimum password length | 8 |

---

## API Documentation

Jotunheim provides a RESTful API for programmatic access.

### Authentication

All API endpoints require authentication via Bearer token.

#### Login

```bash
curl -X POST https://jotunheim.your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"your_password"}'
```

Response:
```json
{
    "token": "1|xxxxxxxxx...",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com"
    }
}
```

### Admin API Endpoints

#### Get All Servers

```bash
curl -X GET https://jotunheim.your-domain.com/api/v1/admin/servers \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Get Server Status

```bash
curl -X GET https://junheim.your-domain.com/api/v1/admin/servers/1/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Create Server

```bash
curl -X POST https://jotunheim.your-domain.com/api/v1/admin/servers \
  -H "Authorization: BORER YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "my-server",
    "user_id": 1,
    "node_id": 1,
    "template_id": 1,
    "cpu": 2,
    "memory": 2147483648,
    "disk": 32212254720
  }'
```

#### Update Server Resources

```bash
curl -X PATCH https://jotunheim.your-domain.com/api/v1/admin/servers/1/resources \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cpu": 4,
    "memory": 4294967296,
    "disk": 53687091200
  }'
```

#### Delete Server

```bash
curl -X DELETE https://jotunheim.your-domain.com/api/v1/admin/servers/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Client API Endpoints

#### Get User's Servers

```bash
curl -X GET https://jotunheim.your-domain.com/api/v1/client/servers \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### Resize Server

```bash
curl -X POST https://jotunheim.your-domain.com/api/v1/client/servers/{uuid}/settings/resize \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "cpu": 4,
    "memory": 4294967296,
    "disk": 53687091200
  }'
```

#### Change Password

```bash
curl -X POST https://jotunheim.your-domain.com/api/v1/client/servers/{uuid}/settings/password \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "password": "new_secure_password"
  }'
```

#### Get Server Console

```bash
curl -X GET https://jotunheim.your-domain.com/api/v1/client/servers/{uuid}/console \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Server Management

### Resource Limits

| Resource | Minimum | Maximum |
|----------|---------|----------|
| CPU Cores | 1 | 128 |
| Memory | 512 MB | 1024 GB |
| Disk | 10 GB | 10240 GB |
| Bandwidth | 0 | 10000 GB |

### Server Lifecycle

#### Status States

1. **Installing** - Server is being provisioned
2. **Running** - Server is powered on and accessible
3. **Stopped** - Server is powered off
4. **Suspended** - Server is suspended due to billing/abuse

### Snapshots

Snapshots allow you to capture server state before making changes.

**Benefits:**
- Rollback to previous state if updates fail
- Create pre-update backups
- Test changes safely

**Best Practices:**
- Take snapshot before major upgrades
- Clean up old snapshots regularly
- Name snapshots descriptively (e.g., "before-upgrade-2024-01-15")

### Backups

Jotunheim integrates with Proxmox backup system.

**Features:**
- Schedule automated backups
- Retain backups for configurable duration
- Restore from any backup point
- Download backup archives

### IP Address Management

**Features:**
- IPv4/IPv6 address pools
- Automated IP assignment
- Gateway configuration
- DNS integration support
- Reserved addresses management

---

## Troubleshooting

### Common Issues

#### Server Won't Start
1. Check Proxmox node connection
2. Verify template exists on node
3. Check user has sufficient permissions
4. Verify network configuration

#### Server Crashes
1. Check server logs in `/var/log/jotunheim/`
2. Check Proxmox VE logs
3. Review server resource allocation

#### Connection Timeout
1. Check PROXMOX_API_TIMEOUT in `.env`
2. Check Proxmox node connectivity
3. Verify API token is valid
4. Check network firewall settings

### Support

For issues not covered here, please:

1. Check [Documentation](#documentation)
2. Search [Issues](https://github.com/akumalabs/jotunheim/issues)
3. Review [Troubleshooting Guide](#troubleshooting)

---

## Documentation

For detailed documentation, please refer to:

- [Installation Guide](#installation)
- [API Documentation](#api-documentation)
- [Configuration Guide](#configuration)
- [Troubleshooting](#troubleshooting)

---

## Development

### Running in Development Mode

```bash
# Backend (Terminal 1)
php artisan serve

# Frontend (Terminal 2 - Hot Reload)
npm run dev

# Queue Worker (Terminal 3)
php artisan queue:work
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=ServerTest

# Generate code coverage report
php artisan test --coverage
```

### Code Style

Jotunheim follows these coding standards:

- **PSR-12** for PHP code style
- **PSR-4** for frontend code style
- **Laravel Best Practices** for backend architecture

---

## Security

### API Authentication

Jotunheim uses Laravel Sanctum for API authentication:

- **Token-based** authentication
- **Bearer tokens** with configurable expiration
- **Token revocation** support
- **Personal access tokens** per user

### Password Security

- Passwords are encrypted using Laravel's built-in encryption
- Minimum password length: 8 characters
- Password requirements enforced
- No weak password storage

### SSL/TLS

- Proxmox API supports SSL verification
- Configurable via `PROXMOX_VERIFY_SSL`
- Recommend SSL enabled in production

### File Permissions

- Application files owned by `www-data` user
- Public directory: 755
- Storage directory: 755
- Cache directory: 755

### SQL Injection Protection

- All database queries use parameterized queries
- Eloquent ORM automatically prevents SQL injection
- Input validation on all API endpoints

---

## License

MIT License

Jotunheim is open-source software licensed under the MIT License.

---

## Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and release notes.

---

## Credits

Built by the [Jotunheim Team](https://github.com/akumalabs/jotunheim/graphs/contributors)

Built with [Laravel](https://laravel.com/)

---

## Support

- **Documentation**: [README.md](#readme)
- **Issues**: [GitHub Issues](https://github.com/akumalabs/jotunheim/issues)
- **Discussions**: [GitHub Discussions](https://github.com/akumalabs/jotunheim/discussions)

---

## Acknowledgments

Jotunheim is built on top of these amazing open-source projects:

- [Laravel](https://laravel.com/)
- [Vue.js](https://vuejs.org/)
- [Tailwind CSS](https://tailwindcss.com/)
- [Pinia](https://pinia.vuejs.org/)
- [TanStack Query](https://tanstack.com/)
- [Proxmox](https://www.proxmox.com/)
