#!/bin/bash

# ==============================================================================
# ===              اسکریپت آپدیت نهایی و امن پروژه VPNMarket                ===
# ==============================================================================

set -e # توقف اسکریپت در صورت بروز هرگونه خطا

# --- تعریف متغیرها و رنگ‌ها ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m' # No Color

PROJECT_PATH="/var/www/vpnmarket"
WEB_USER="www-data"
PHP_VERSION="8.3" # این نسخه باید با اسکریپت نصب شما هماهنگ باشد

# --- مرحله ۰: بررسی‌های اولیه ---
echo -e "${CYAN}--- شروع فرآیند آپدیت پروژه VPNMarket ---${NC}"

if [ "$PWD" != "$PROJECT_PATH" ]; then
  echo -e "${RED}خطا: این اسکریپت باید از داخل پوشه پروژه ('cd $PROJECT_PATH') اجرا شود.${NC}"
  exit 1
fi

echo

# --- مرحله ۱: پشتیبان‌گیری و حالت تعمیر ---
echo -e "${YELLOW}مرحله ۱ از ۹: فعال‌سازی حالت تعمیر و ایجاد نسخه پشتیبان...${NC}"
sudo -u $WEB_USER php artisan down || echo "سایت از قبل در حالت تعمیر است."
sudo cp .env .env.bak.$(date +%Y-%m-%d_%H-%M-%S)
echo "یک نسخه پشتیبان از فایل .env ساخته شد."

# --- مرحله ۲: دریافت آخرین کدها از گیت‌هاب ---
echo -e "${YELLOW}مرحله ۲ از ۹: دریافت آخرین تغییرات از گیت‌هاب...${NC}"
# استفاده از reset --hard برای اطمینان از یکسان بودن کامل با مخزن
sudo git fetch origin main
sudo git reset --hard origin/main

# --- مرحله ۳: تنظیم دسترسی‌های صحیح ---
echo -e "${YELLOW}مرحله ۳ از ۹: تنظیم مجدد دسترسی‌های فایل...${NC}"
sudo chown -R $WEB_USER:$WEB_USER .
sudo chmod -R 775 storage bootstrap/cache

# --- مرحله ۴: آپدیت وابستگی‌های PHP (Composer) ---
echo -e "${YELLOW}مرحله ۴ از ۹: به‌روزرسانی پکیج‌های PHP...${NC}"
sudo -u $WEB_USER composer install --no-dev --optimize-autoloader

# --- مرحله ۵: آپدیت Frontend (Node.js/NPM) ---
echo -e "${YELLOW}مرحله ۵ از ۹: به‌روزرسانی پکیج‌های Node.js و کامپایل assets...${NC}"
# استفاده از npm ci برای نصب سریع و قابل اعتماد از روی فایل lock
sudo -u $WEB_USER npm ci
sudo -u $WEB_USER npm run build

# --- مرحله ۶: آپدیت دیتابیس ---
echo -e "${YELLOW}مرحله ۶ از ۷: اجرای مایگریشن‌های دیتابیس...${NC}"
sudo -u $WEB_USER php artisan migrate --force

# --- مرحله ۷: پاکسازی و ایجاد مجدد کش‌ها ---
echo -e "${YELLOW}مرحله ۷ از ۹: پاکسازی و ایجاد مجدد کش‌ها برای محیط production...${NC}"
sudo -u $WEB_USER php artisan optimize:clear
sudo -u $WEB_USER php artisan config:cache
sudo -u $WEB_USER php artisan route:cache
sudo -u $WEB_USER php artisan view:cache

# --- مرحله ۸: ری‌استارت سرویس‌های حیاتی ---
echo -e "${YELLOW}مرحله ۸ از ۹: ری‌استارت سرویس‌های PHP و Queue...${NC}"
sudo systemctl restart php${PHP_VERSION}-fpm
sudo -u $WEB_USER php artisan queue:restart # این دستور به Supervisor می‌گوید که worker ها را با کد جدید ری‌استارت کند

# --- مرحله ۹: خروج از حالت تعمیر ---
echo -e "${YELLOW}مرحله ۹ از ۹: فعال‌سازی مجدد سایت...${NC}"
sudo -u $WEB_USER php artisan up

echo
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}✅ پروژه با موفقیت به آخرین نسخه آپدیت شد!${NC}"
echo -e "${GREEN}=====================================================${NC}"
