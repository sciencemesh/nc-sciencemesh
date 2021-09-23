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

## Test with Reva
* add this app to your Nextcloud instance as /apps/sciencemesh
* run `php -S localhost:8080` in the root of your nextcloud folder (or run it with Apache / nginx / MAMP / etc)
* create a user 'tester' (password e.g. '123')
* log in and enable the 'sciencemesh' app
* in your local reva checkout, run:
```sh
NEXTCLOUD=http://tester:123@localhost:8080/index.php go test -v github.com/cs3org/reva/pkg/storage/fs/nextcloud/...
```
* you should see it run lots of tests, most of which fail in various ways
* look at [this mock](https://github.com/cs3org/reva/blob/de30aee/pkg/storage/fs/nextcloud/nextcloud_server_mock.go#L140-L169) to see the correct params and responses

## Publish to App Store

First get an account for the [App Store](http://apps.nextcloud.com/) then run:

    make && make appstore

The archive is located in build/artifacts/appstore and can then be uploaded to the App Store.

## Running tests
You can use the provided Makefile to run all tests by using:

    make test

This will run the PHP unit and integration tests and if a package.json is present in the **js/** folder will execute **npm run test**

Of course you can also install [PHPUnit](http://phpunit.de/getting-started.html) and use the configurations directly:

    phpunit -c phpunit.xml

or:

    phpunit -c phpunit.integration.xml

for integration tests
