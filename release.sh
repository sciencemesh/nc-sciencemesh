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
echo copy sciencemesh.key into an oc1 container and run:
echo docker exec -it oc1.docker bash
echo -> chown -R www-data apps/sciencemesh
echo -> exit
echo docker exec -it -u www-data oc1.docker bash
echo -> ./occ integrity:sign-app --privateKey=/var/www/sciencemesh.key --certificate=apps/sciencemesh/sciencemesh.crt --path=apps/sciencemesh
echo now commit the appinfo/signature.json file inside apps/sciencemesh
