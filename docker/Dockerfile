FROM php:5

COPY docker/instantclient-basiclite-linux.x64-12.2.0.1.0.zip /tmp/
COPY docker/instantclient-sdk-linux.x64-12.2.0.1.0.zip /tmp/

RUN mkdir -m 777 -p /srv/test

COPY src /srv/test/
COPY test /srv/test/

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		unzip \
		libaio-dev \
		firebird-dev \
		libzip-dev \
		libfreetype6-dev \
		libjpeg62-turbo-dev \
		libmcrypt-dev \
		libpng-dev \
		libcurl4-openssl-dev \
	&& docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
	&& docker-php-ext-configure interbase --with-interbase \
	&& docker-php-ext-configure zip --with-libzip=/usr/include/ \
	&& docker-php-ext-configure curl --with-curl=/usr/include/ \
	&& docker-php-ext-configure bcmath --enable-bcmath \
	&& docker-php-ext-install -j$(nproc) interbase zip gd iconv mcrypt curl bcmath \
	&& apt-get remove -y firebird2.5-server-common \
	&& apt-get autoremove -y \
	&& apt-get install -y --no-install-recommends libfbclient2 \
	&& pecl channel-update pecl.php.net \
	&& pecl install xdebug-2.5.5 && docker-php-ext-enable xdebug \
	&&	mkdir -p /opt/oracle \
	&& unzip /tmp/instantclient-basiclite-linux.x64-12.2.0.1.0.zip -d /opt/oracle/ \
	&& unzip /tmp/instantclient-sdk-linux.x64-12.2.0.1.0.zip -d /opt/oracle/ \
	&& ln -s /opt/oracle/instantclient_12_2/libclntsh.so.12.1 /opt/oracle/instantclient_12_2/libclntsh.so \
	&& ln -s /opt/oracle/instantclient_12_2/libocci.so.12.1 /opt/oracle/instantclient_12_2/libocci.so \
	&& echo "/opt/oracle/instantclient_12_2" > /etc/ld.so.conf.d/oracle-instantclient.conf \
	&& ldconfig \
	&& echo 'instantclient,/opt/oracle/instantclient_12_2' | pecl install oci8-2.0.12 \
	&& docker-php-ext-enable oci8

COPY docker/php.ini /usr/local/etc/php/php.ini

EXPOSE 80

CMD ["php", "-S", "0.0.0.0:80", "-t", "/srv/test/test"]
