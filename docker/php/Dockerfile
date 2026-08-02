FROM php:8.3-fpm-alpine

# intl  = CodeIgniter 4 บังคับ
# gd/zip = PhpSpreadsheet บังคับ (อ่านไฟล์ .xlsx)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache icu-dev libzip-dev oniguruma-dev tzdata \
        libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl mysqli pdo_mysql zip gd opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY ./docker/php/fpm-env.conf /usr/local/etc/php-fpm.d/zz-env.conf

WORKDIR /var/www/html

# ติดตั้ง vendor ตอน build image — anonymous volume ใน docker-compose.yml
# ปกป้อง /var/www/html/vendor ไม่ให้ถูก bind mount ของ ./src ทับ
COPY ./src/composer.json ./composer.json
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    && chown -R www-data:www-data /var/www/html

# writable/ ต้องเขียนได้เสมอ (bind mount มาจาก host)
COPY ./docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
# กัน CRLF ที่ติดมาจาก Windows host ทำให้ shebang พัง
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
