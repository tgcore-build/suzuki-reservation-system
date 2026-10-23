#!/bin/sh
# 本番（Render）でコンテナが起動するたびに動く
set -e
cd /var/www/html

# Render が決めるポート（PORT）で待ち受ける
PORT="${PORT:-10000}"
sed -i "s/Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Aiven の CA 証明書（環境変数 DB_SSL_CA_CERT に、証明書の中身をそのまま入れる）をファイルにする
if [ -n "${DB_SSL_CA_CERT:-}" ]; then
    printf '%s\n' "$DB_SSL_CA_CERT" > /tmp/db-ca.pem
    export MYSQL_ATTR_SSL_CA=/tmp/db-ca.pem
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force
# 最初の管理者（ADMIN_EMAIL / ADMIN_PASSWORD があるときだけ作る。すでにいれば、パスワードは変えない）
php artisan db:seed --class=AdminUserSeeder --force

# 任意：コンテナが起きているあいだ、スケジューラー（リマインドの自動送信）を動かす
# 無料プランは使われないと止まる（スリープする）ため、確実に送るなら README の「リマインドの自動送信」を参照
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    php artisan schedule:work &
fi

exec apache2-foreground
