#!/bin/bash
# Meva — first-boot setup for a DigitalOcean droplet (Ubuntu 24.04 LTS).
# Paste into "Startup scripts" when creating the droplet. Runs once as root.
#
# Installs: nginx, PHP 8.4 + FPM, Composer, PostgreSQL 17, Redis, Supervisor,
# certbot, fail2ban, ufw, DO monitoring agent. Lays out /var/www/meva-back
# (Laravel API) and /var/www/meva-client (the Vue build, uploaded from your
# machine — no Node on the server), a deploy user, nginx sites, queue workers
# and the scheduler.
#
# After boot: cat /root/meva-credentials.txt
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
exec > >(tee -a /var/log/meva-setup.log) 2>&1

# ── Edit these ────────────────────────────────────────────────────────────────
API_DOMAIN="api.meva.life"     # Laravel API
APP_DOMAIN="meva.life"         # Vue storefront
DEPLOY_USER="meva"
DB_NAME="meva"
DB_USER="meva"
PHP_VERSION="8.4"
TIMEZONE="Europe/Belgrade"
# ─────────────────────────────────────────────────────────────────────────────

DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
REDIS_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-28)"
BACK=/var/www/meva-back
CLIENT=/var/www/meva-client

echo "== $(date) starting Meva setup"
timedatectl set-timezone "$TIMEZONE"
hostnamectl set-hostname meva

# ── Base packages ─────────────────────────────────────────────────────────────
apt-get update
apt-get -y upgrade
apt-get -y install software-properties-common curl wget git unzip zip gnupg2 \
  ca-certificates lsb-release apt-transport-https ufw fail2ban supervisor \
  unattended-upgrades htop jq acl

# ── Swap (2 GB) ───────────────────────────────────────────────────────────────
if ! swapon --show | grep -q swapfile; then
  fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
  echo '/swapfile none swap sw 0 0' >> /etc/fstab
  sysctl -w vm.swappiness=10 && echo 'vm.swappiness=10' >> /etc/sysctl.conf
fi

# ── Firewall + fail2ban + auto security updates ───────────────────────────────
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
systemctl enable --now fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades

# ── Deploy user (sudo, www-data group, root's SSH keys) ──────────────────────
if ! id "$DEPLOY_USER" &>/dev/null; then
  adduser --disabled-password --gecos "" "$DEPLOY_USER"
  usermod -aG sudo,www-data "$DEPLOY_USER"
  echo "$DEPLOY_USER ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/90-$DEPLOY_USER
  mkdir -p /home/$DEPLOY_USER/.ssh
  cp /root/.ssh/authorized_keys /home/$DEPLOY_USER/.ssh/authorized_keys
  chown -R $DEPLOY_USER:$DEPLOY_USER /home/$DEPLOY_USER/.ssh
  chmod 700 /home/$DEPLOY_USER/.ssh && chmod 600 /home/$DEPLOY_USER/.ssh/authorized_keys
fi

# ── PHP 8.4 + extensions + Composer ───────────────────────────────────────────
add-apt-repository -y ppa:ondrej/php
apt-get update
apt-get -y install php$PHP_VERSION-{cli,fpm,pgsql,mbstring,xml,curl,zip,bcmath,intl,gd,redis,opcache,readline}
sed -i 's/^;\?memory_limit = .*/memory_limit = 512M/;s/^;\?upload_max_filesize = .*/upload_max_filesize = 64M/;s/^;\?post_max_size = .*/post_max_size = 64M/' /etc/php/$PHP_VERSION/fpm/php.ini
cat > /etc/php/$PHP_VERSION/fpm/conf.d/99-meva.ini <<INI
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
realpath_cache_size=4096K
realpath_cache_ttl=600
INI
systemctl enable --now php$PHP_VERSION-fpm
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── PostgreSQL 17 ─────────────────────────────────────────────────────────────
install -d /usr/share/postgresql-common/pgdg
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc
echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list
apt-get update
apt-get -y install postgresql-17
systemctl enable --now postgresql
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '$DB_USER') THEN
    CREATE ROLE $DB_USER LOGIN PASSWORD '$DB_PASS';
  END IF;
END \$\$;
SELECT 'CREATE DATABASE $DB_NAME OWNER $DB_USER' WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '$DB_NAME')\gexec
SQL

# ── Redis (password, memory cap, systemd) ─────────────────────────────────────
apt-get -y install redis-server
sed -i "s/^# requirepass .*/requirepass $REDIS_PASS/;s/^supervised .*/supervised systemd/;s/^# maxmemory <bytes>/maxmemory 256mb/;s/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/" /etc/redis/redis.conf
systemctl enable --now redis-server
systemctl restart redis-server

# ── Directories ───────────────────────────────────────────────────────────────
mkdir -p $BACK $CLIENT/dist
# A placeholder until the first upload, so nginx has something to serve.
echo '<!doctype html><title>Meva</title><p style="font-family:sans-serif;padding:2rem">Meva — uskoro.</p>' > $CLIENT/dist/index.html
chown -R $DEPLOY_USER:www-data /var/www
chmod -R 2775 /var/www
setfacl -R -d -m g:www-data:rwx /var/www

# ── nginx: API + SPA ──────────────────────────────────────────────────────────
apt-get -y install nginx
rm -f /etc/nginx/sites-enabled/default

cat > /etc/nginx/sites-available/meva-back <<NGINX
server {
    listen 80;
    server_name $API_DOMAIN;
    root $BACK/public;
    index index.php;
    client_max_body_size 64m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php$PHP_VERSION-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* { deny all; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    access_log /var/log/nginx/meva-back.access.log;
    error_log  /var/log/nginx/meva-back.error.log;
}
NGINX

cat > /etc/nginx/sites-available/meva-client <<NGINX
server {
    listen 80;
    server_name $APP_DOMAIN www.$APP_DOMAIN;
    root $CLIENT/dist;
    index index.html;

    gzip on;
    gzip_types text/plain text/css application/javascript application/json image/svg+xml;

    # The storefront calls the API same-origin, as the Vite dev proxy does
    # locally; nginx forwards those paths to the API site.
    location /api/     { proxy_pass http://127.0.0.1:80/api/; proxy_set_header Host $API_DOMAIN; proxy_set_header X-Forwarded-For \$remote_addr; }
    location /storage/ { proxy_pass http://127.0.0.1:80/storage/; proxy_set_header Host $API_DOMAIN; }

    location /assets/ { expires 1y; add_header Cache-Control "public, immutable"; }
    location / { try_files \$uri \$uri/ /index.html; }

    access_log /var/log/nginx/meva-client.access.log;
    error_log  /var/log/nginx/meva-client.error.log;
}
NGINX

ln -sf /etc/nginx/sites-available/meva-back /etc/nginx/sites-enabled/meva-back
ln -sf /etc/nginx/sites-available/meva-client /etc/nginx/sites-enabled/meva-client
nginx -t && systemctl enable --now nginx && systemctl reload nginx

# ── Supervisor: queue workers (+ Horizon/Reverb ready to enable) ─────────────
cat > /etc/supervisor/conf.d/meva-worker.conf <<SUP
[program:meva-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $BACK/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=256
directory=$BACK
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/meva-worker.log
stopwaitsecs=3600
SUP

# Enable after `composer require laravel/horizon` (queue dashboard) — replaces meva-worker.
cat > /etc/supervisor/conf.d/meva-horizon.conf.disabled <<SUP
[program:meva-horizon]
command=php $BACK/artisan horizon
directory=$BACK
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/meva-horizon.log
stopwaitsecs=3600
SUP

# Enable after `php artisan install:broadcasting` (Reverb websockets for live updates in the iOS app).
cat > /etc/supervisor/conf.d/meva-reverb.conf.disabled <<SUP
[program:meva-reverb]
command=php $BACK/artisan reverb:start --host=127.0.0.1 --port=8080
directory=$BACK
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/meva-reverb.log
SUP

mkdir -p /var/log/supervisor
systemctl enable --now supervisor

# ── Scheduler ─────────────────────────────────────────────────────────────────
echo "* * * * * www-data cd $BACK && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/meva-scheduler

# ── Log rotation for Laravel ──────────────────────────────────────────────────
cat > /etc/logrotate.d/meva <<ROT
$BACK/storage/logs/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
ROT

# ── certbot (issue certs once DNS points here) ────────────────────────────────
snap install core && snap refresh core
snap install --classic certbot
ln -sf /snap/bin/certbot /usr/bin/certbot

# ── DigitalOcean monitoring agent (CPU/RAM/disk graphs + alerts in the panel) ─
curl -sSL https://repos.insights.digitalocean.com/install.sh | bash || true

# ── Deploy helper ─────────────────────────────────────────────────────────────
cat > /usr/local/bin/meva-deploy <<'DEPLOY'
#!/bin/bash
# Deploys the Laravel API from git. The Vue build is uploaded from your own
# machine with `deploy/push.sh` in the Vue repo — there is no Node here.
set -euo pipefail
BACK=/var/www/meva-back

cd "$BACK"
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan storage:link || true

# Cache config, events and views -- but never routes: Dingo registers the API
# on its own router from the withRouting(then:) callback, which Laravel's route
# cache skips, so a cached route table answers every API call with
# "The version given was unknown or has no registered routes." (400).
php artisan config:cache
php artisan event:cache
php artisan view:cache
php artisan route:clear

php artisan queue:restart
sudo supervisorctl restart all
sudo systemctl reload php8.4-fpm
echo "API deployed: $(git rev-parse --short HEAD)"
DEPLOY
chmod +x /usr/local/bin/meva-deploy

# ── Credentials + next steps ──────────────────────────────────────────────────
cat > /root/meva-credentials.txt <<TXT
Meva droplet — generated $(date)

Deploy user:   $DEPLOY_USER   (ssh $DEPLOY_USER@<ip>, sudo without password)
Laravel:       $BACK          (nginx: $API_DOMAIN)
Vue:           $CLIENT/dist   (nginx: $APP_DOMAIN) — uploaded from your machine, no Node here

PostgreSQL 17
  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=5432
  DB_DATABASE=$DB_NAME
  DB_USERNAME=$DB_USER
  DB_PASSWORD=$DB_PASS

Redis
  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=$REDIS_PASS
  QUEUE_CONNECTION=redis
  CACHE_STORE=redis
  SESSION_DRIVER=redis

Next steps
  1. DNS: A records $API_DOMAIN and $APP_DOMAIN -> this droplet's IP
  2. As $DEPLOY_USER:
       git clone <laravel repo> $BACK && cd $BACK
       cp .env.example .env  # fill in the values above, APP_URL=https://$API_DOMAIN, APP_ENV=production
       composer install --no-dev --optimize-autoloader
       php artisan key:generate && php artisan jwt:secret
       php artisan migrate --force --seed && php artisan storage:link && php artisan optimize
     On your machine, in the Vue repo:  deploy/push.sh $DEPLOY_USER@<ip>   (builds and uploads dist)
  3. TLS:  certbot --nginx -d $API_DOMAIN -d $APP_DOMAIN -d www.$APP_DOMAIN
  4. Workers: sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl status
  5. Later deploys:  API: meva-deploy   |   Vue: deploy/push.sh $DEPLOY_USER@<ip> (from your machine)
  6. Queue dashboard: composer require laravel/horizon, then
       mv /etc/supervisor/conf.d/meva-horizon.conf.disabled /etc/supervisor/conf.d/meva-horizon.conf
       (and remove meva-worker.conf) + supervisorctl reread/update
  7. Realtime for the iOS app: php artisan install:broadcasting (Reverb), enable meva-reverb.conf the same way
     and add an nginx location /app -> 127.0.0.1:8080 with websocket headers.

Log of this setup: /var/log/meva-setup.log
TXT
chmod 600 /root/meva-credentials.txt

echo "== $(date) Meva setup done. See /root/meva-credentials.txt"
