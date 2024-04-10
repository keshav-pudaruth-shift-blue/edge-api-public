# Arguments
ARG BASE_IMAGE_VERSION

FROM keshavpudaruthshift/shift-edge:${BASE_IMAGE_VERSION}

ARG DOMAIN
ARG VERSION
ARG ENVIRONMENT
ARG DEV

ENV API_VERSION=${VERSION}
ENV PHP_OPCACHE_ENABLED=1

LABEL za.co.hellopay.image.authors="Keshav Pudaruth <keshav.pudaruth@shift.blue>" \
          service.title="Shift Edge API Container" \
          service.name="${DD_SERVICE_NAME}" \
          service.description="PHP-FPM-NGINX Container for Shift Edge API" \
          service.version="${VERSION}"

#--------------------------------------------
# Xdebug configuration
#--------------------------------------------

RUN if [ "$DEV" = "true" ]; then \
        rm -f /usr/local/etc/php/conf.d/98-ddtrace.ini \
        && rm -f /usr/local/etc/php/conf.d/99-ddtrace-custom.ini \
        && install-php-extensions xdebug \
        && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.discover_client_host=false" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.client_host=\${XDEBUG_REMOTE_HOST}" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.remote_handler=dbgp" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini; \
    fi

#
#--------------------------------------------------------------------------
# Supervisord Configuration
#--------------------------------------------------------------------------
#
COPY docker/supervisor/conf.d /etc/supervisor/conf.d

#
#--------------------------------------------------------------------------
# Crontab Configuration
#--------------------------------------------------------------------------
#
# Add crontab file in the cron directory
ADD docker/crontab/laravel-cron /etc/cron.d/laravel-cron
# Give execution rights on the cron job
RUN chmod 0644 /etc/cron.d/laravel-cron

#
#--------------------------------------------------------------------------
# Nginx configuration
#--------------------------------------------------------------------------
#

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/app.conf /etc/nginx/conf.d/app.conf
RUN chown -R www-data:www-data /var/lib/nginx

#
#--------------------------------------------------------------------------
# Repository Setup
#--------------------------------------------------------------------------
#
#RUN mkdir -p /var/www/html
WORKDIR /var/www/html
COPY . /var/www/html
RUN rm /var/www/html/.env*


# install production dependencies
# RUN ln -sf /proc/1/fd/1 /var/log/test.log -> fixes the datadog issue -> make sure laravel's LOG_CHANNEL=single
RUN chown -R www-data:www-data /var/www/html/storage/logs/ \
    && chown -R www-data:www-data /var/www/html/storage/framework \
    && chown -R www-data:www-data /var/www/html/storage/app/public/ \
    && TELESCOPE_ENABLED=false php /usr/bin/composer install --no-interaction --optimize-autoloader;


COPY .env.${ENVIRONMENT} /var/www/html/.env

# Configure a healthcheck to validate that everything is up&running
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/kube/health-check
