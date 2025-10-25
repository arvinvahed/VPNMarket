#!/bin/bash

# ==============================================================================
# ===              اسکریپت آپدیت هوشمند و امن پروژه VPNMarket                ===
# ==============================================================================

set -e # توقف اسکریپت در صورت بروز هرگونه خطا

# --- تعریف متغیرها و رنگ‌ها ---
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
RED='\033[0;31m'
NC='\033[0m'

PROJECT_PATH="/var/www/vpnmarket"
WEB_USER="www-data"

# --- مرحله ۰: بررسی‌های اولیه ---
echo -e "${CYAN}--- شروع فرآیند آپدیت پروژه VPNMarket ---${NC}"

if [ "$PWD" != "$PROJECT_PATH" ]; then
  echo -e "${RED}خطا: این اسکریپت باید از داخل پوشه پروژه ('cd $PROJECT_PATH') اجرا شود.${NC}"
  exit 1
fi

if [ ! -f ".env" ]; then
    echo -e "${RED}خطا: فایل .env یافت نشد!${NC}"
    exit 1
fi

echo

# --- مرحله ۱: آماده‌سازی محیط و حالت تعمیر ---
echo -e "${YELLOW}مرحله ۱ از ۷: آماده‌سازی محیط و فعال‌سازی حالت تعمیر...${NC}"

# --->>> تغییر کلیدی: حل مشکل دسترسی npm در ابتدای کار <<<---
echo "ایجاد و تنظیم دسترسی پوشه کش NPM..."
sudo mkdir -p /var/www/.npm
sudo chown -R $WEB_USER:$WEB_USER /var/www/.npm

# ایجاد نسخه پشتیبان از .env
sudo cp .env .env.bak.$(date +%Y-%m-%d_%H-%M-%S)
echo "یک نسخه پشتیبان از فایل .env شما در همین مسیر ساخته شد."

# فعال‌سازی حالت تعمیر
sudo -u $WEB_USER php artisan down || true

# --- مرحله ۲: دریافت آخرین کدها از گیت‌هاب ---
echo -e "${YELLOW}مرحله ۲ از ۷: دریافت آخرین تغییرات از گیت‌هاب...${NC}"
sudo git fetch origin
sudo git reset --hard origin/main

# --- مرحله ۳: تنظیم دسترسی‌های صحیح فایل‌ها ---
echo -e "${YELLOW}مرحله ۳ از ۷: تنظیم مجدد دسترسی‌های فایل...${NC}"
sudo chown -R $WEB_USER:$WEB_USER .
sudo chmod -R 775 storage bootstrap/cache

# --- مرحله ۴: آپدیت وابستگی‌های PHP (Composer) ---
echo -e "${YELLOW}مرحله ۴ از ۷: به‌روزرسانی پکیج‌های PHP...${NC}"
sudo -u $WEB_USER composer install --no-dev --optimize-autoloader

# --- مرحله ۵: آپدیت وابستگی‌های Frontend (NPM) ---
echo -e "${YELLOW}مرحله ۵ از ۷: به‌روزرسانی پکیج‌های Node.js و کامپایل assets...${NC}"
# --->>> تغییر کلیدی: اجرای npm با پوشه خانگی صحیح <<<---
sudo -u $WEB_USER HOME=/var/www npm install
sudo -u $WEB_USER HOME=/var/www npm run build
echo "فایل‌های JS/CSS برای محیط Production کامپایل شدند."

# --- مرحله ۶: آپدیت دیتابیس و ری‌استارت سرویس‌ها ---
echo -e "${YELLOW}مرحله ۶ از ۷: آپدیت دیتابیس و ری‌استارت سرویس‌ها...${NC}"
sudo -u $WEB_USER php artisan migrate --force
# ری‌استارت کردن worker های صف برای بارگذاری کد جدید
sudo supervisorctl restart vpnmarket-worker:*
echo "سرویس‌های صف (Queue) با موفقیت ری‌استارت شدند."

# --- مرحله ۷: پاکسازی کش‌ها و خروج از حالت تعمیر ---
echo -e "${YELLOW}مرحله ۷ از ۷: پاکسازی کش‌ها و فعال‌سازی مجدد سایت...${NC}"
# این دستور تمام کش‌ها را به صورت امن پاک می‌کند
sudo -u $WEB_USER php artisan optimize:clear
sudo -u $WEB_USER php artisan up

echo
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}✅ پروژه با موفقیت به آخرین نسخه آپدیت شد!${NC}"
echo -e "${GREEN}=====================================================${NC}"
