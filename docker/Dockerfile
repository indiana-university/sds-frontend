#FROM php:8.2-apache-bookworm
FROM php:8.4-apache-trixie


# Omeka-S web publishing platform for digital heritage collections (https://omeka.org/s/)
# Previous maintainers: Oldrich Vykydal (o1da) - Klokan Technologies GmbH  / Eric Dodemont <eric.dodemont@skynet.be>
# MAINTAINER Giorgio Comai <g@giorgiocomai.eu>

RUN a2enmod rewrite

# Set default environment variables
ENV DEBIAN_FRONTEND=noninteractive \
    APPLICATION_ENV=production

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Redirect Apache logs to stdout/stderr
RUN ln -sf /dev/stdout /var/log/apache2/access.log && \
    ln -sf /dev/stderr /var/log/apache2/error.log

RUN apt-get -qq update && apt-get -qq -y upgrade
RUN apt-get -qq update && apt-get -qq -y --no-install-recommends install \
        # Utils needed later
        unzip \
        wget \
        jq \
        ghostscript \
        poppler-utils \
        netcat-openbsd \
        # PHP Extension Runtime/Build-time Libs (-dev packages needed for compilation)
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        # libjpeg-dev # Likely redundant with libjpeg62-turbo-dev
        libpng-dev \
        zlib1g-dev \
        libicu-dev \
        libsodium-dev \
        # For Imagick extension
        imagemagick \
        libmagickwand-dev \
        zip \
        unzip \
    # Cleanup apt cache
    && apt-get clean


# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
# Install Composer dependencies
# error: Composer could not find a composer.json file in /var/www/html
#To initialize a project, please create a composer.json file. See https://getcomposer.org/basic-usage
#RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install PHP extension installer tool
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install PHP extensions using the tool
# It will handle installing/removing temporary build dependencies (like build-essential)
RUN install-php-extensions \
    gd \
    iconv \
    pdo \
    pdo_mysql \
    mysqli \
    imagick \
    intl \
    xsl \
    zip

# Install Omeka-S-CLI
ADD https://github.com/GhentCDH/Omeka-S-Cli/releases/latest/download/omeka-s-cli.phar /usr/local/bin/omeka-s-cli
RUN chmod +x /usr/local/bin/omeka-s-cli

# Set working directory
WORKDIR /var/www/html

# Omeka S version configuration
ARG OMEKA_VERSION=4.2.0

# Download, extract, and configure Omeka S in a single layer
RUN set -x && \
    \
    # 1. Download and extract Omeka S
    if [ "$OMEKA_VERSION" = "develop" ]; then \
      OMEKA_S_URL="https://github.com/omeka/omeka-s/archive/develop.tar.gz"; \
    else \
      OMEKA_S_URL="https://github.com/omeka/omeka-s/tarball/refs/tags/${OMEKA_VERSION}"; \
    fi && \
    echo "Downloading Omeka S from: $OMEKA_S_URL" && \
    curl -L "$OMEKA_S_URL" | tar xz --strip-components=1 -C . && \
    \
    # 2. Create the volume structure for persistent data
    mkdir -p volume/config \
             volume/files \
             volume/modules \
             volume/themes \
             volume/logs && \
    \
    # 3. Create symbolic links to the volume directories
    rm -rf config files modules themes logs && \
    ln -s volume/config . && \
    ln -s volume/files . && \
    ln -s volume/modules . && \
    ln -s volume/themes . && \
    ln -s volume/logs . && \
    \
    # 4. Set final permissions
    chown -R  www-data:www-data volume . /usr/local/bin/omeka-s-cli

# Overwrite the original Docker PHP entrypoint
COPY docker-php-entrypoint /usr/local/bin/

# Copy the automatic installation script
COPY install_cli.php /var/www/html/

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
