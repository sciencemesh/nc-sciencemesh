mkdir -p build/sciencemesh
rm -rf build/sciencemesh/*
cp -r appinfo build/sciencemesh/
cp -r css build/sciencemesh/
cp -r img build/sciencemesh/
cp -r js build/sciencemesh/
cp -r lib build/sciencemesh/
cp -r templates build/sciencemesh/
cp -r composer.* build/sciencemesh/
cd build/sciencemesh/
composer install
cd ../../release
rm -f -- sciencemesh.tar.gz
tar -cf sciencemesh.tar ../build/sciencemesh
gzip sciencemesh.tar
cd ..
