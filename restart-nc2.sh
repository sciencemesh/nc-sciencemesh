docker stop `docker ps -q`
docker rm `docker ps -qa`
docker run -d --network=testnet -e MARIADB_ROOT_PASSWORD=eilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek --name=maria2.docker --restart unless-stopped mariadb --transaction-isolation=READ-COMMITTED --binlog-format=ROW --innodb-file-per-table=1 --skip-innodb-read-only-compressed
docker run --network=testnet --publish 443:443 -d --name=nc2.docker -v /root/nc-sciencemesh:/var/www/html/apps/sciencemesh --restart unless-stopped nc2
echo sleeping before running initial Nextcloud install script
sleep 10

docker exec -it -u www-data nc2.docker /bin/bash /init.sh
docker exec -it maria2.docker mysql -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek nextcloud -e "insert into oc_appconfig (appid, configkey, configvalue) values ('sciencemesh', 'iopUrl', 'https://reva2.pondersource.net/');"
docker exec -it maria2.docker mysql -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek nextcloud -e "insert into oc_appconfig (appid, configkey, configvalue) values ('sciencemesh', 'revaSharedSecret', 'shared-secret-2');"
docker exec -it maria2.docker mysql -u root -peilohtho9oTahsuongeeTh7reedahPo1Ohwi3aek nextcloud -e "select * from oc_appconfig where appid='sciencemesh';"
docker exec -it nc2.docker /bin/bash -c "cd apps/sciencemesh && make build"
docker exec -it nc2.docker sed -i "267 i\ \t\treturn \$share;" apps/files_sharing/lib/External/Manager.php
docker ps
