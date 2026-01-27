# Codebase Restructure Plan - Convoy Style

## Goals
- Organize code following Convoy's domain-driven design patterns
- Maintain Jotunheim naming and style (avoid license issues)
- Improve maintainability and code organization

## Changes Required

### 1. Actions (NEW)
```
app/Actions/
├── Server/
│   ├── BuildServerAction.php
│   ├── DeleteServerAction.php
│   └── RebuildServerAction.php
```
- Encapsulate complex multi-step operations
- Used by Jobs to orchestrate multiple repository calls

### 2. Exceptions (REORGANIZE)
```
app/Exceptions/
├── Http/
│   └── ProxmoxApiException.php
├── Model/
│   └── DataValidationException.php
├── Repository/
│   └── ProxmoxRequestException.php
└── Service/
    └── ServerDeploymentException.php
```
- Organize by layer (Http, Model, Repository, Service)
- Create proper hierarchy

### 3. Jobs (REORGANIZE BY DOMAIN)
```
app/Jobs/
├── Backup/
├── Node/
└── Server/
    ├── BuildServerJob.php
    ├── ConfigureVmJob.php
    ├── CreateServerJob.php
    ├── DeleteServerJob.php
    ├── RebuildServerJob.php
    └── UpdatePasswordJob.php
```
- Move Rebuild jobs into Server folder
- Keep job domain separation

### 4. Routes (SPLIT BY SCOPE)
```
routes/
├── api-admin.php      # Admin endpoints
├── api-client.php      # Client/end-user endpoints
└── api-auth.php       # Authentication endpoints
```
- Split single api.php into domain-specific files
- Better organization and maintainability

### 5. Services (KEEP EXISTING)
```
app/Services/
├── Servers/
├── Proxmox/
├── Backups/
├── Nodes/
└── ...
```
- Already well-organized by domain
- Keep current structure

### 6. Repositories (KEEP EXISTING)
```
app/Repositories/
├── Eloquent/
└── Proxmox/
```
- Already following Convoy pattern
- Keep current structure

### 7. Data (KEEP EXISTING)
```
app/Data/
├── Server/
├── Node/
└── ...
```
- Already organized by domain
- Keep current structure

## License Compliance
- All original naming retained
- Original code structure where possible
- No direct code copying
- Own implementation of similar patterns
