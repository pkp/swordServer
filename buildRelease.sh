#!/bin/sh
PRODUCT_NAME=swordServer

[ -d $PRODUCT_NAME ] && echo "Directory $PRODUCT_NAME exits, remove and try again" && exit 0;

mkdir $PRODUCT_NAME
cp *.php $PRODUCT_NAME/
cp -r swordappv2-php-library $PRODUCT_NAME/
cp -r *.xml $PRODUCT_NAME/
cp -r locale $PRODUCT_NAME/

COPYFILE_DISABLE=1 tar -cv --exclude test --exclude \\.* $PRODUCT_NAME | gzip > $PRODUCT_NAME.tar.gz

[ ! -e $PRODUCT_NAME.tar.gz ] && "Failed to create $PRODUCT_NAME.tar.gz" && exit 1;

CHECKSUM=$(md5 $PRODUCT_NAME.tar.gz)

echo $CHECKSUM


