#!/bin/bash

# ==================================================================================
# === اسکریپت نصب نهایی، هوشمند و ضد خطا برای پروژه VPNMarket روی Ubuntu 22.04 ===
# === نویسنده: Arvin Vahed                                                       ===
# === https://github.com/arvinvahed/VPNMarket                                    ===
# ==================================================================================

 set -e

 # Colors
 GREEN='\033[0;32m'
 YELLOW='\033[1;33m'
 CYAN='\033[0;36m'
 RED='\033[0;31m'
 NC='\033[0m'

 PROJECT_PATH="/var/www/vpnmarket"
 GITHUB_REPO="https://github.com/arvinvahed/VPNMarket.git"
 PHP_VERSION="8.3"

 echo -e "${CYAN}--- شروع نصب پروژه VPNMarket ---${NC}"
 echo

 # === دریافت اطلاعات ===
 read -p "🌐 دامنه: " DOMAIN
 DOMAIN=$(echo $DOMAIN | sed 's|http[s]*://||g' | sed 's|/.*||g')

 read -p "🗃 نام دیتابیس: " DB_NAME
 read -p "👤 نام کاربری دیتابیس: " DB_USER

 while true; do
     read -s -p "🔑 رمز عبور دیتابیس: " DB_PASS
     echo
     [ ! -z "$DB_PASS" ] && break
     echo -e "${RED}رمز عبور نباید خالی باشد.${NC}"
 done

 read -p "✉️ ایمیل SSL: " ADMIN_EMAIL
 echo

 # === حذف PHP های قبلی ===
 echo -e "${YELLOW}🧹 حذف نسخه‌های قدیمی PHP ...${NC}"
 sudo apt-get remove -y php* || true
 sudo apt autoremove -y

 # === پیش‌نیازها ===
 echo -e "${YELLOW}📦 نصب ابزارها ...${NC}"
 export DEBIAN_FRONTEND=noninteractive
 sudo apt-get update -y
 sudo apt-get install -y git curl unzip software-properties-common gpg nginx mysql-server redis-server supervisor ufw

 # === نصب Node.js LTS ===
 echo -e "${YELLOW}📦 نصب Node.js ...${NC}"
 curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
 sudo apt-get install -y nodejs

 # === نصب PHP 8.3 ===
 echo -e "${YELLOW}☕ نصب PHP ${PHP_VERSION} ...${NC}"
 sudo add-apt-repository -y ppa:ondrej/php
 sudo apt-get update -y

 sudo apt-get install -y \
     php${PHP_VERSION} php${PHP_VERSION}-fpm php${PHP_VERSION}-cli \
     php${PHP_VERSION}-mysql php${PHP_VERSION}-mbstring php${PHP_VERSION}-xml \
     php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
     php${PHP_VERSION}-intl php${PHP_VERSION}-gd php${PHP_VERSION}-dom \
     php${PHP_VERSION}-redis

 # Composer با PHP 8.3
 sudo apt-get remove -y composer || true
 php${PHP_VERSION} -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
 php${PHP_VERSION} composer-setup.php --install-dir=/usr/local/bin --filename=composer
 rm composer-setup.php

 echo -e "${GREEN}✔ Composer با PHP ${PHP_VERSION} فعال شد.${NC}"

 # === فعال‌سازی سرویس‌ها ===
 sudo systemctl enable --now php${PHP_VERSION}-fpm nginx mysql redis-server supervisor

 # === فایروال ===
 sudo ufw allow 'OpenSSH'
 sudo ufw allow 'Nginx Full'
 echo "y" | sudo ufw enable

 # === دانلود پروژه ===
 echo -e "${YELLOW}⬇️ دانلود سورس ...${NC}"
 sudo rm -rf "$PROJECT_PATH"
 sudo git clone $GITHUB_REPO $PROJECT_PATH
 sudo chown -R www-data:www-data $PROJECT_PATH
 cd $PROJECT_PATH

 # === ساخت دیتابیس ===
 sudo mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
 sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
 sudo mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
 sudo mysql -e "FLUSH PRIVILEGES;"

 # === تنظیم ENV ===
 sudo -u www-data cp .env.example .env
 sudo sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
 sudo sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
 sudo sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
 sudo sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
 sudo sed -i "s|APP_ENV=.*|APP_ENV=production|" .env
 sudo sed -i "s|QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|" .env

 # === نصب وابستگی‌ها ===
 echo -e "${YELLOW}🧰 نصب پکیج‌ها ...${NC}"
 sudo -u www-data composer install --no-dev --optimize-autoloader
 sudo -u www-data npm install
 sudo -u www-data npm run build

 sudo -u www-data php artisan key:generate
 sudo -u www-data php artisan migrate --seed --force
 sudo -u www-data php artisan storage:link

 # === پیکربندی Nginx ===
 PHP_FPM_SOCK_PATH="/run/php/php${PHP_VERSION}-fpm.sock"

 sudo tee /etc/nginx/sites-available/vpnmarket >/dev/null <<EOF
 server {
     listen 80;
     server_name $DOMAIN;
     root $PROJECT_PATH/public;

     index index.php;
     location / {
         try_files \$uri \$uri/ /index.php?\$query_string;
     }
     location ~ \.php$ {
         fastcgi_pass unix:$PHP_FPM_SOCK_PATH;
         fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
         include fastcgi_params;
     }
 }
 EOF

 sudo ln -sf /etc/nginx/sites-available/vpnmarket /etc/nginx/sites-enabled/
 sudo rm -f /etc/nginx/sites-enabled/default
 sudo nginx -t && sudo systemctl restart nginx

 # === Supervisor ===
 sudo tee /etc/supervisor/conf.d/vpnmarket-worker.conf >/dev/null <<EOF
 [program:vpnmarket-worker]
 command=php $PROJECT_PATH/artisan queue:work redis --sleep=3 --tries=3
 autostart=true
 autorestart=true
 user=www-data
 numprocs=2
 redirect_stderr=true
 stdout_logfile=/var/log/supervisor/vpnmarket-worker.log
 EOF

 sudo supervisorctl reread
 sudo supervisorctl update
 sudo supervisorctl start vpnmarket-worker:*

 # === Cache ===
 sudo -u www-data php artisan config:cache
 sudo -u www-data php artisan route:cache
 sudo -u www-data php artisan view:cache

 # === SSL ===
 read -p "🔒 فعال‌سازی SSL؟ (y/n): " ENABLE_SSL
 if [[ "$ENABLE_SSL" =~ ^[Yy]$ ]]; then
     sudo certbot --nginx -d $DOMAIN --non-interactive --agree-tos -m $ADMIN_EMAIL
 fi

 echo -e "${GREEN}=====================================================${NC}"
 echo -e "${GREEN}✅ نصب با موفقیت انجام شد!${NC}"
 echo -e "🌐 https://$DOMAIN"
 echo -e "🔑 پنل مدیریت: https://$DOMAIN/admin"
 echo -e "${GREEN}=====================================================${NC}"

