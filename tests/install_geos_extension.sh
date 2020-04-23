#!/bin/bash

# Install geos library dependencies
sudo apt-get install -y libgeos-dev/stable

# Build and install GEOS PHP extension
sudo git clone https://git.osgeo.org/gogs/geos/php-geos.git
cd php-geos
./configure
sudo make install

# add to php.ini
# echo "extension=geos.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
echo "extension=geos.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

