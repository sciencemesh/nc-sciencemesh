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
cd ..
tar -cf sciencemesh.tar sciencemesh
cd ../release
mv ../build/sciencemesh.tar .
rm -f -- sciencemesh.tar.gz
gzip sciencemesh.tar
cd ..
echo `openssl dgst -sha512 -sign ~/sciencemesh.key ./sciencemesh.tar.gz | openssl base64`
echo visit https://apps.nextcloud.com/developer/apps/releases/new
echo go into the developer tools browser console to change the `<a>` element around the form to a `<p>` element. This makes it possible to fill in values in this form without being redirected.
echo fill in for instance `https://github.com/pondersource/nc-sciencemesh/raw/sciencemesh/release/sciencemesh.tar.gz` and the base64 signature from the openssl command
echo click 'uploaden'
echo good luck!
