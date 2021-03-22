@echo off
cd ..
php -dphar.readonly=0 DevTools.phar --make Esoteric --entry Esoteric/src/ethaniccc/Esoteric/Esoteric.php --out Esoteric.phar
cd Esoteric/upload
php Upload.php
cd ..