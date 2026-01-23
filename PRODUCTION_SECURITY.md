# Production Security & Hardening Guide

## Environment Configuration

### Required .env Settings

```env
# Security Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database (use MySQL in production)
DB_CONNECTION=mysql
DB_DATABASE=midgard_prod
DB_USERNAME=midgard_user
DB_PASSWORD=YOUR_SECURE_PASSWORD

# Cache & Session (use Redis in production)
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Force HTTPS
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_MAX_ATTEMPTS=5
RATE_LIMIT_DECAY_MINUTES=15

# Proxmox API credentials
PROXMOX_API_HOST=https://proxmox.yourdomain.com:8006
PROXMOX_API_TOKEN_ID=YOUR_TOKEN_ID
PROXMOX_API_TOKEN_SECRET=YOUR_TOKEN_SECRET
```

## Security Headers

Add to your Nginx/Apache configuration:

### Nginx

```nginx
server {
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; frame-ancestors 'self';" always;

    # HSTS (only after SSL is configured)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Hide version information
    server_tokens off;

    # File upload limits
    client_max_body_size 100M;
}
```

### Apache (.htaccess)

```apache
# Security Headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "no-referrer-when-downgrade"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Prevent directory listing
Options -Indexes

# Prevent access to sensitive files
<FilesMatch "\.(env|log|sql|git)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

## File Permissions

### Production Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/midgard

# Set proper permissions
sudo chmod -R 755 /var/www/midgard
sudo chmod -R 775 /var/www/midgard/storage
sudo chmod -R 775 /var/www/midgard/bootstrap/cache
sudo chmod -R 644 /var/www/midgard/.env

# Make directories writable
sudo chmod -R 777 storage/app storage/framework storage/logs
```

### Secure .env File

```bash
# Restrict .env to only owner
chmod 600 /var/www/midgard/.env
chown www-data:www-data /var/www/midgard/.env

# Add to .gitignore if not already
echo ".env" >> .gitignore
echo "database/midgard.sqlite" >> .gitignore
```

## SSL/TLS Configuration

### Use Let's Encrypt (Recommended)

```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d midgard.yourdomain.com

# Auto-renewal (certbot does this automatically)
sudo certbot renew --dry-run
```

### Manual SSL Certificate

```nginx
server {
    listen 443 ssl http2;
    server_name midgard.yourdomain.com;

    ssl_certificate /etc/ssl/midgard/cert.pem;
    ssl_certificate_key /etc/ssl/midgard/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384';
    ssl_prefer_server_ciphers on;

    # Force SSL redirect
    if ($scheme = http) {
        return 301 https://$host$request_uri;
    }
}
```

## Database Security

### MySQL Security

```bash
# Run MySQL secure installation
sudo mysql_secure_installation

# Create dedicated database user with limited permissions
mysql -u root -p
```

```sql
CREATE USER 'midgard_app'@'localhost' IDENTIFIED BY 'strong_password_here';

GRANT SELECT, INSERT, UPDATE, DELETE ON midgard_prod.* TO 'midgard_app'@'localhost';

FLUSH PRIVILEGES;
```

### Disable Remote Root Access

```bash
# Configure MySQL to only listen on localhost
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Add or modify:
bind-address = 127.0.0.1

# Restart MySQL
sudo systemctl restart mysql
```

## Firewall Configuration

### UFW (Uncomplicated Firewall)

```bash
# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow Proxmox API access (if needed)
sudo ufw allow 8006/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

### Limit Access to Specific IPs

```bash
# Restrict admin panel access to specific IPs
sudo ufw allow from YOUR_OFFICE_IP to any port 80
sudo ufw allow from YOUR_OFFICE_IP to any port 443
```

## Application Security

### Disable Debug Mode

```bash
# In production .env
APP_DEBUG=false
```

### Secure Artisan

```bash
# Remove execute permissions from storage
find storage -type f -exec chmod 644 {} \;
find storage -type d -exec chmod 755 {} \;
find bootstrap/cache -type f -exec chmod 644 {} \;
find bootstrap/cache -type d -exec chmod 755 {} \;

# Verify artisan is not executable by others
chmod 744 artisan
```

### Secure Queue Workers

```ini
# /etc/supervisor/conf.d/midgard-worker.conf
[program:midgard-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/midgard/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/midgard/storage/logs/worker.log
stopwaitsecs=3600

# Run as non-root user
```

## Monitoring & Logging

### Enable Laravel Log

```bash
# Configure log channel in .env
LOG_CHANNEL=stack
LOG_LEVEL=warning

# Set up log rotation
sudo nano /etc/logrotate.d/midgard
```

### Log Rotation Config

```
/var/www/midgard/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload nginx > /dev/null
    endscript
}
```

## Proxmox Security

### Use Limited API Tokens

1. Go to Datacenter → Permissions → API Tokens
2. Create token with specific privileges only:
   - `VM.Allocate`
   - `VM.Config`
   - `VM.PowerMgmt`
3. **Avoid** using root@pam tokens in production

### Token Management

```bash
# Never commit Proxmox tokens to version control
echo "config/proxmox.php" >> .gitignore
```

## Rate Limiting

### Configure Throttling

```php
// config/throttle.php
return [
    'by_ip' => env('RATE_LIMIT_BY_IP', 60),
    'by_credentials' => env('RATE_LIMIT_BY_CREDENTIALS', 5),
    'decay_minutes' => env('RATE_LIMIT_DECAY_MINUTES', 1),
];
```

## Backup Strategy

### Database Backups

```bash
# Cron job for daily backups
0 2 * * * /usr/bin/mysqldump -u midgard_user -p'PASSWORD' midgard_prod | gzip > /backups/midgard_$(date +\%Y\%m\%d).sql.gz

# Retain 30 days
find /backups -name "midgard_*.sql.gz" -mtime +30 -delete
```

### File System Backups

```bash
# Backup application files
rsync -avz --delete /var/www/midgard /backups/midgard_files_$(date +\%Y\%m\%d)

# Backup storage directory
tar -czf /backups/storage_$(date +\%Y\%m\%d).tar.gz /var/www/midgard/storage
```

## Regular Security Tasks

### Weekly Checklist

- [ ] Review access logs: `tail -n 100 storage/logs/laravel.log`
- [ ] Check for failed login attempts: `grep "401" storage/logs/laravel.log`
- [ ] Review user accounts and remove unused ones
- [ ] Check Proxmox API token usage
- [ ] Verify SSL certificates are valid
- [ ] Update system packages: `apt update && apt upgrade -y`
- [ ] Review firewall rules
- [ ] Check for suspicious activity in admin panel
- [ ] Test backup restoration procedure
- [ ] Review rate limiting effectiveness

### Monthly Checklist

- [ ] Rotate database passwords
- [ ] Update Proxmox API tokens
- [ ] Review and update `.env` secrets
- [ ] Run full database integrity check
- [ ] Audit server resources and usage
- [ ] Review and update monitoring/alerts

## Incident Response Plan

### Data Breach

1. Immediately revoke all API tokens
2. Change all database passwords
3. Review access logs
4. Notify affected users
5. Enable 2FA enforcement
6. Review Proxmox token usage
7. Conduct security audit

### Service Outage

1. Check Proxmox node connectivity
2. Review queue workers: `supervisorctl status`
3. Check resource usage
4. Review error logs: `tail -f storage/logs/laravel.log`
5. Restart services if needed
6. Notify users with ETA

### Performance Issues

1. Check Redis connection
2. Check database query performance
3. Review slow query logs
4. Check queue backlog
5. Review Proxmox API response times
6. Consider scaling resources

## Security Scanning

### Recommended Tools

```bash
# Install security scanner
sudo apt install -y nikto

# Scan your domain
nikto -h https://midgard.yourdomain.com

# Install vulnerability scanner
sudo apt install -y lynis
lynis https://midgard.yourdomain.com
```

## Compliance

### GDPR Considerations

- Implement data export functionality
- Provide data deletion on account closure
- Maintain audit logs for 6 months minimum
- Clear cookie consent
- Document data processing activities

### PCI DSS Considerations (if processing payments)

- Use TLS 1.2+
- Encrypt sensitive data at rest
- Strong password policies
- Regular security audits
- Network segmentation
- File integrity monitoring
