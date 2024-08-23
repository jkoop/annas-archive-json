FROM trafex/php-nginx
COPY default.conf /etc/nginx/conf.d/default.conf
COPY html/ /var/www/html/
