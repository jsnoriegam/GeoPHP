#!/bin/bash

set -ev

# Install geos library dependencies
sudo apt-get install -y libgeos-dev

# Build and install GEOS PHP extension
cd $HOME
sudo git clone https://git.osgeo.org/gogs/geos/php-geos.git
cd php-geos
chmod ugo+x autogen.sh
./autogen.sh
./configure
sudo make install

# add to php.ini
# echo "extension=geos.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
echo "extension=geos.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
