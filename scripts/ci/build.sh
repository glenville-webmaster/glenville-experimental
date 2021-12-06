#!/bin/bash

##########
# Experimental Build Script To be used across all Deployments
##########

# Abort if anything fails
set -e

# Change user to root
USER root

# Add to base build (thecodingmachine/php:7.4-v4-apache-node14)

apt-get update
apt -y install software-properties-common
add-apt-repository ppa:ondrej/php
apt-get update
apt-get install -y libpng-dev libjpeg-dev libpq-dev libzip-dev libonig-dev libicu-dev default-mysql-client libbz2-dev libgmp-dev acl bc bzip2 wget

apt-get update
apt-get install -y automake nasm libtool fontforge ttfautohint rsync

sudo rm -rf /var/lib/apt/lists/*

export PKG_CONFIG_PATH=$PKG_CONFIG_PATH:/usr

apt-get update
apt-get install -y libgnutls28-dev
apt-get install -y libcurl4-gnutls-dev

apt-get install -y php-raphf php-propro
pecl install pecl_http-3.2.4

echo "extension=raphf.so" >> /usr/local/etc/php/conf.d/php.ini
echo "extension=propro.so" >> /usr/local/etc/php/conf.d/php.ini
echo "extension=http.so" >> /usr/local/etc/php/conf.d/php.ini

apt-get update
apt-get install -y libmagickwand-dev --no-install-recommends
sudo rm -rf /var/lib/apt/lists*
pecl install imagick
echo "extension=imagick.so" >> /usr/local/etc/php/conf.d/php.ini

echo 'sendmail_path=/bin/true' > /usr/local/etc/php/conf.d/sendmail.ini

# Install phantomJS
apt-get update
apt-get install -y build-essential chrpath libssl-dev libxft-dev libfreetype6-dev libfreetype6 libfontconfig1-dev libfontconfig1
wget https://bitbucket.org/ariya/phantomjs/downloads/phantomjs-2.1.1-linux-x86_64.tar.bz2
tar jxf phantomjs-2.1.1-linux-x86_64.tar.bz2
mv phantomjs-2.1.1-linux-x86_64/bin/phantomjs /usr/local/bin/phantomjs
chmod +x /usr/local/bin/phantomjs

# Install Chrome (for reasons?)
curl -sS -o - https://dl-ssl.google.com/linux/linux_signing_key.pub | apt-key add -
echo "deb http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google-chrome.list
apt-get update
apt-get install -y google-chrome-stable

CHROME_DRIVER_VERSION=`curl -sS chromedriver.storage.googleapis.com/LATEST_RELEASE`
wget -N http://chromedriver.storage.googleapis.com/$CHROME_DRIVER_VERSION/chromedriver_linux64.zip -P ~/
unzip ~/chromedriver_linux64.zip -d ~/
rm ~/chromedriver_linux64.zip
mv -f ~/chromedriver /usr/local/bin/chromedriver
chmod 0755 /usr/local/bin/chromedriver

# Install Composer and base composer modules
cd /composer
composer require pantheon-systems/terminus
composer global require "drupal/console:^1.9"
composer global require "drush/drush:^10.4"
composer global require phpmd/phpmd
composer global require "sebastian/phpcpd:^4.0"

# Install PHPDebt
RUN wget https://github.com/smmccabe/phpdebt/releases/download/1.0.2/phpdebt.phar
chmod +x phpdebt.phar
mv phpdebt.phar /usr/local/bin/phpdebt

# Install ReadmeCheck
wget https://raw.githubusercontent.com/smmccabe/readmecheck/master/readmecheck
chmod +x readmecheck
mv readmecheck /usr/local/bin/readmecheck

# Install NodeSource
curl -fsSL https://deb.nodesource.com/setup_15.x -o nodesource_setup.sh
bash nodesource_setup.sh
rm nodesource_setup.sh
apt-get install -y nodejs
npm i -g npm@latest

# Install PubKey
RUN curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add -
echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list
apt-get update
apt-get install yarn
yarn global add grunt

# Install Security Checker
curl -sL https://github.com/fabpot/local-php-security-checker/releases/download/v1.0.0/local-php-security-checker_1.0.0_linux_amd64 -o security-checker.phar
chmod +x security-checker.phar
mkdir /usr/local/bin/security-checker
mv security-checker.phar /usr/local/bin/security-checker

# Done
echo "============"
echo "YAY! The build is complete"
echo "============"
