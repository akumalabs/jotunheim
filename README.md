# Midgard Panel

A modern, premium Proxmox VE control panel built with **Laravel 12** and **Vue 3**.  
Midgard provides a simplified, Modern-premium interface for managing KVM virtual machines, featuring powerful automation, cloud-init integration, and a sleek user experience.

**Repository**: https://github.com/akumalabs/midgard-panel  
**Live Demo**: https://midgard.akumalabs.com

## Key Features

- **Modern UI/UX** - Premium, responsive interface inspired by VirtFusion.
  - **Data-Dense Layouts**: Information-rich tables and dashboards.
  - **Real-Time Updates**: Live status pillars and metric polling.
- **Advanced Server Management**:
  - **Async Creation**: Background provisioning for resilience.
  - **Cloud-Init Integration**: Automated configuration of Users, Passwords, SSH Keys, and Networking.
  - **Power Controls**: Start, Stop, Restart, Kill, Shutdown.
  - **Resource Management**: Dynamic resizing of Disk, CPU, and RAM.
- **Data Protection**:
  - **Snapshots**: Create, Rollback, and Delete VM snapshots.
  - **Backups**: Integrated Proxmox backup management.
- **Node Management** - Add, configure, and monitor Proxmox VE nodes.
- **IP Management** - IPv4/IPv6 address pools with automated assignment.
- **ISO Management** - Mount/Unmount ISOs directly from the panel.
- **NoVNC Console** - Secure, embedded browser-based VM access.

## Tech Stack

### Backend
- **Laravel 12** (Latest)
- **MySQL 8** (or MariaDB 10.6+) / **SQLite**
- **Laravel Sanctum** (API Authentication)
- **Redis** (Queue & Caching)

### Frontend
- **Vue 3** (Composition API)
- **TypeScript**
- **Tailwind CSS 4**
- **Pinia** (State Management)
- **TanStack Query** (Smart Data Fetching)
- **Shadcn UI** (Components)

## Server Requirements

### Minimum Hardware
| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 2 core | 4+ cores |
| RAM | 2 GB | 4+ GB |
| Disk | 30 GB | 50+ GB SSD |

### Software Requirements
| Software | Version | Notes |
|----------|---------|-------|
| OS | Ubuntu 22.04 / Debian 12 | Systemd required |
| PHP | 8.2+ | Extensions: bcmath, curl, dom, mbstring, mysql, redis, xml, zip |
| MySQL | 8.0+ | Or MariaDB 10.6+ |
| Nginx/Apache | - | Web Server |
| Redis | 6.0+ | **Required** for Async Jobs |
| Node.js | 18+ | For frontend build |

### Proxmox VE Requirements
- **Proxmox VE 7.0+** (8.0+ Recommended)
- **API Token**: User needs `VM.*`, `Datacenter.*` privileges.
- **Cloud-Init**: Templates must have Cloud-Init installed.

## Installation

### One-Line Install (Ubuntu/Debian)
```bash
curl -sSL https://raw.githubusercontent.com/akumalabs/midgard-panel/main/install.sh | sudo bash
```

### Manual Install
<details>
<summary>Click to view manual installation steps</summary>

#### 1. Clone & Install Dependencies
```bash
git clone https://github.com/akumalabs/midgard-panel.git
cd midgard-panel
composer install
npm install
```

#### 2. Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```
Edit `.env` to configure Database, Redis, and App URL.

#### 3. Setup Database & User
```bash
php artisan migrate --seed
# Creates admin@midgard.local / password
```

#### 4. Build Frontend & Run
```bash
npm run build
php artisan serve
```
</details>

## Development

```bash
# Start Laravel Backend
php artisan serve

# Start Vite Frontend (Hot Reload)
npm run dev

# Run Queue Worker (Crucial for Server Creation)
php artisan queue:work
```

## API Documentation

Midgard exposes a RESTful API authorized via Sanctum Bearer tokens.

### Core Endpoints

| Resource | Admin `(Metrics, Mgmt)` | Client `(User Actions)` |
|----------|-------------------------|-------------------------|
| **Servers** | CRUD, Power, Migrations | List, Power, Console |
| **Snapshots** | List, Create, Rollback, Delete | List, Create, Rollback, Delete |
| **Settings** | ISO Mount/Unmount, Reinstall | ISO Mount/Unmount, Reinstall |
| **Nodes** | Sync, Stats, Config | - |
| **IPs** | Manage Pools, Assign | View Assigned |

## License
MIT License
