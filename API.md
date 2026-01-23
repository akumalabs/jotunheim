# Midgard API Documentation

## Overview

Midgard provides a RESTful API for managing Proxmox VE virtual machines, nodes, users, and infrastructure.

## Base URL

```
https://your-domain.com/api/v1
```

## Authentication

All API endpoints require authentication via Laravel Sanctum Bearer tokens.

### Login

**POST** `/api/v1/auth/login`

Request:
```json
{
    "email": "admin@midgard.local",
    "password": "password"
}
```

Response:
```json
{
    "access_token": "1|xxxxxxxxxxxxxxxxxxxxx",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@midgard.local"
    }
}
```

### Logout

**POST** `/api/v1/auth/logout`

Headers:
```
Authorization: Bearer YOUR_TOKEN
```

### Get Current User

**GET** `/api/v1/user`

Headers:
```
Authorization: Bearer YOUR_TOKEN
```

## Admin Endpoints

### Nodes

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/admin/nodes` | List all nodes | Admin |
| POST | `/admin/nodes` | Create a node | Admin |
| GET | `/admin/nodes/{id}` | Get node details | Admin |
| PUT | `/admin/nodes/{id}` | Update node | Admin |
| DELETE | `/admin/nodes/{id}` | Delete node | Admin |
| POST | `/admin/nodes/{id}/test` | Test node connection | Admin |
| POST | `/admin/nodes/{id}/sync` | Sync server data | Admin |

### Servers

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/admin/servers` | List all servers | Admin |
| POST | `/admin/servers` | Create a server | Admin |
| GET | `/admin/servers/{id}` | Get server details | Admin |
| PUT | `/admin/servers/{id}` | Update server | Admin |
| DELETE | `/admin/servers/{id}` | Delete server | Admin |
| POST | `/admin/servers/{id}/start` | Start server | Admin |
| POST | `/admin/servers/{id}/stop` | Stop server | Admin |
| POST | `/admin/servers/{id}/restart` | Restart server | Admin |

### Users

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/admin/users` | List all users | Admin |
| POST | `/admin/users` | Create a user | Admin |
| GET | `/admin/users/{id}` | Get user details | Admin |
| PUT | `/admin/users/{id}` | Update user | Admin |
| DELETE | `/admin/users/{id}` | Delete user | Admin |

### Locations

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/admin/locations` | List all locations | Admin |
| POST | `/admin/locations` | Create a location | Admin |
| PUT | `/admin/locations/{id}` | Update location | Admin |
| DELETE | `/admin/locations/{id}` | Delete location | Admin |

## Client Endpoints

### My Servers

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/client/servers` | List my servers | Client |
| GET | `/client/servers/{uuid}` | Get server details | Client |
| POST | `/client/servers/{uuid}/start` | Start server | Client |
| POST | `/client/servers/{uuid}/stop` | Stop server | Client |
| POST | `/client/servers/{uuid}/restart` | Restart server | Client |
| GET | `/client/servers/{uuid}/console` | Get VNC console credentials | Client |

### Backups

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/client/servers/{uuid}/backups` | List server backups | Client |
| POST | `/client/servers/{uuid}/backups` | Create backup | Client |
| POST | `/client/servers/{uuid}/backups/{id}/restore` | Restore from backup | Client |
| DELETE | `/client/servers/{uuid}/backups/{id}` | Delete backup | Client |

### SSH Keys

| Method | Endpoint | Description | Auth Required |
|---------|----------|-------------|---------------|
| GET | `/client/ssh-keys` | List SSH keys | Client |
| POST | `/client/ssh-keys` | Add SSH key | Client |
| DELETE | `/client/ssh-keys/{id}` | Delete SSH key | Client |

## Error Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (successful deletion) |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (invalid or missing token) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Internal Server Error |

## Rate Limiting

API requests are rate-limited to 60 requests per minute per IP address.

Headers returned with rate-limited responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 42
X-RateLimit-Reset: 1690896000
```

## Example Usage

### Create a Server

```bash
curl -X POST https://midgard.example.com/api/v1/admin/servers \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "My Web Server",
    "user_id": 5,
    "node_id": 3,
    "memory": 2048,
    "disk": 50,
    "cpu": 2,
    "ip_address": "192.168.1.10"
  }'
```

### Start a Server

```bash
curl -X POST https://midgard.example.com/api/v1/client/servers/{uuid}/start \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

### List Backups

```bash
curl -X GET https://midgard.example.com/api/v1/client/servers/{uuid}/backups \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## WebSocket Events

Midgard provides real-time updates via WebSocket for:

- Server status changes
- Task progress
- Resource usage updates

Connect to:
```
wss://midgard.example.com/ws
```

With authentication header:
```
Authorization: Bearer YOUR_TOKEN
```

## Pagination

List endpoints support pagination:

```json
{
    "data": [...],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 42,
        "last_page": 3
    }
}
```

Use query parameters:
```
?page=1&per_page=25
```
