# ScienceMesh
Place this app in **nextcloud/apps/**

## Building the app

The app can be built by using the provided Makefile by running:

    make

This requires the following things to be present:
* make
* which
* tar: for building the archive
* curl: used if phpunit and composer are not installed to fetch them from the web
* npm: for building and testing everything JS, only required if a package.json is placed inside the **js/** folder

The make command will install or update Composer dependencies if a composer.json is present and also **npm run build** if a package.json is present in the **js/** folder. The npm **build** script should use local paths for build systems and package managers, so people that simply want to build the app won't need to install npm libraries globally, e.g.:

**package.json**:
```json
"scripts": {
    "test": "node node_modules/gulp-cli/bin/gulp.js karma",
    "prebuild": "npm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
    "build": "node node_modules/gulp-cli/bin/gulp.js"
}
```
# Before running tests 

* add this app to your Nextcloud instance as /apps/sciencemesh
* run `php -S localhost:8080` in the root of your nextcloud folder (or run it with Apache / nginx / MAMP / etc)
* create a user 'tester' (password e.g. '123')
* log in and enable the 'sciencemesh' app
* in your local reva checkout, run:
```sh
git remote add michielbdejong https://github.com/michielbdejong/reva
git fetch michielbdejong
```

# How to run the Linter

* We just check the code base

* Path: /server/apps/sciencemesh

```
make lint-check
```

* The second one for fixing our codebase

```
make lint-fix
```

## Run Reva integration tests

Path: /reva
```sh
git checkout nextcloud-test-improvements
NEXTCLOUD=http://tester:123@localhost:8080/index.php go test -v github.com/cs3org/reva/pkg/storage/fs/nextcloud/...
```
* you should see it run lots of tests, most of which fail in various ways
* look at [this mock](https://github.com/cs3org/reva/blob/de30aee/pkg/storage/fs/nextcloud/nextcloud_server_mock.go#L140-L169) to see the correct params and responses

## Run Nextcloud Unit tests

Path: /server/apps/sciencemesh

You can use the provided Makefile to run all tests by using:

   ```
   XDEBUG=coverage make test
   ```
   or 
   ```
   XDEBUG_MODE=coverage ./vendor/bin/phpunit --coverage-text
   ```
   
This will run the PHP unit and integration tests and if a package.json is present in the **js/** folder will execute **npm run test**

Of course you can also install [PHPUnit](http://phpunit.de/getting-started.html) and use the configurations directly:

     phpunit -c phpunit.xml

or:

    phpunit -c phpunit.integration.xml

## Publish to App Store

First get an account for the [App Store](http://apps.nextcloud.com/) then run:

    make && make appstore

The archive is located in build/artifacts/appstore and can then be uploaded to the App Store.

# [OCM share functionality in Reva](https://reva.link/docs/tutorials/share-tutorial)

To check the share functionality we will need **4 terminals**.

First make sure to `git checkout rrn-testing`.

### 1) Terminal 1 

* `cd reva/`

* ` ./cmd/reva/reva -host localhost:17000 -insecure`

### 2)  Terminal 2 

 * `cd server/`
 
 * ` php -S localhost:8080`

### 3) Terminal 3 

* ` cd /reva/examples/ocmd`

* ` ../../cmd/revad/revad -c ./ocmd-server-2-with-nextcloud.toml`

Note that this is the only difference with the [Reva bulding instructions](https://reva.link/docs/tutorials/share-tutorial/#3-run-reva)

### 4)  Terminal 4 

* ` cd /reva/examples/ocmd`

* `../../cmd/revad/revad -c ocmd-server-1.toml`

# CURL commands

To check the [RevaCotroller.php](https://github.com/pondersource/nc-sciencemesh/blob/6215c61/lib/Controller/RevaController.php) methods you can use these Curl commands:

## Sharing methods: 

### addShare()

    curl -v -H  'Content-Type:application/json' -X POST -d '{"md":{"opaque_id":"fileid-einstein%2Fmy-folder"},"g":{"grantee":{"type":1,"Id":{"UserId":{"idp":"cesnet.cz","opaque_id":"marie","type":1}}}},"provider_domain":"cern.ch","resource_type":"file","provider_id":2,"owner_display_name":"Albert Einstein","protocol":{"name":"webdav","options":{"sharedSecret":"secret","permissions":"webdav-property"}}}' http://marie:radioactivity@localhost:8080/index.php/apps/sciencemesh/~marie/api/ocm/addShare

    
### ListReceivedShares()

    curl -X POST http://marie:radioactivity@localhost:8080/index.php/apps/sciencemesh/~marie/api/ocm/ListReceivedShares
    
### Share()

    curl -v -H  'Content-Type:application/json'  -X POST -d '{"md":{"storage_id":"123e4567-e89b-12d3-a456-426655440000","opaque_id":"fileid-marie%2FtestFile.json"},"g":{"grantee":{"type":1,"Id":{"UserId":{"idp":"cernbox.cern.ch","opaque_id":"einstein","type":1}}},"permissions":{"permissions":{"get_path":true,"initiate_file_download":true,"list_container":true,"list_file_versions":true,"stat":true}}}}'  http://marie:radioactivity@localhost:8080/index.php/apps/sciencemesh/~marie/api/ocm/Share


    
