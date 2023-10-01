### Creating a share



## Sender

Search for contacts, it goes through the search plugin and finds all the contacts you have made.

example output:
```json
{
  "display_name": "marie",
  "idp": "revaowncloud2.docker",
  "user_id": "marie",
  "mail": ""
}
```

click on contact to create a share. this step appends postfix for sm shares to distinguish them 
from regular federated shares.

Call share provider `create`, this is called by share manager, share manager calls share provider factory which has
been overridden to be sm share provider.

sm share provider will route sm share and regular federated shares based on the appended postfix.

sm share goes to: revaHttpClient->createShare

example request:
```json
{
  "sourcePath": "\\/home\\/test\\/",
  "targetPath": "\\/test\\/",
  "type": "dir",
  "recipientUsername": "marie",
  "recipientHost": "revaowncloud2.docker",
  "role": "viewer"
}
```

reva received the request and does these calls in order:
1. "POST /index.php/apps/sciencemesh/~einstein/api/auth/Authenticate HTTP/1.1"
2. "POST /index.php/apps/sciencemesh/~einstein/api/storage/CreateHome HTTP/1.1"
3. "POST /index.php/apps/sciencemesh/~einstein/api/storage/GetMD HTTP/1.1" 200
sm response:
```php
array (
    'type' => 2,
    'id' => 
        array (
          'opaque_id' => 'fileid-/home/test',
        ),
    'checksum' => 
        array (
          'type' => 1,
          'sum' => '',
        ),
    'etag' => '65129328493b0',
    'mime_type' => 'folder',
    'mtime' => 
        array (
          'seconds' => 1695716136,
        ),
    'path' => '/home/test',
    'permissions' => 31,
    'size' => 0,
    'owner' => 
        array (
          'opaque_id' => 'einstein',
          'idp' => 'revaowncloud1.docker',
        ),
)
```
4. "POST /index.php/apps/sciencemesh/~einstein/api/storage/GetMD HTTP/1.1" 200
sm response:
```php
array (
    'type' => 2,
    'id' => 
        array (
          'opaque_id' => 'fileid-/home/test',
        ),
    'checksum' => 
        array (
          'type' => 1,
          'sum' => '',
        ),
    'etag' => '65129328493b0',
    'mime_type' => 'folder',
    'mtime' => 
        array (
          'seconds' => 1695716136,
        ),
    'path' => '/home/test',
    'permissions' => 31,
    'size' => 0,
    'owner' => 
        array (
          'opaque_id' => 'einstein',
          'idp' => 'revaowncloud1.docker',
        ),
)
```
5. "POST /index.php/apps/sciencemesh/~einstein/api/ocm/addSentShare HTTP/1.1" 201
reva payload:
```php
array (
    'userId' => 'einstein',
    '_route' => 'sciencemesh.reva.addSentShare',
    'resourceId' => 
        array (
          'storageId' => 'nextcloud',
          'opaqueId' => 'fileid-/home/test',
        ),
    'name' => 'test',
    'token' => 'Y7bWUulmHrhfUJ8LRknNpkZQGcRRkMk7',
    'grantee' => 
        array (
          'type' => 'GRANTEE_TYPE_USER',
          'userId' => 
              array (
                'idp' => 'revaowncloud2.docker',
                'opaqueId' => 'marie',
              ),
        ),
    'owner' => 
        array (
          'idp' => 'revaowncloud1.docker',
          'opaqueId' => 'einstein',
          'type' => 'USER_TYPE_PRIMARY',
        ),
    'creator' => 
        array (
          'idp' => 'revaowncloud1.docker',
          'opaqueId' => 'einstein',
        ),
    'ctime' => 
        array (
          'seconds' => '1695716163',
          'nanos' => 943856286,
        ),
    'mtime' => 
        array (
          'seconds' => '1695716163',
          'nanos' => 943856286,
        ),
    'shareType' => 'SHARE_TYPE_USER',
    'accessMethods' => 
    array (
      0 => 
      array (
        'webdavOptions' => 
            array (
              'permissions' => 
                  array (
                    'getPath' => true,
                    'getQuota' => true,
                    'initiateFileDownload' => true,
                    'listGrants' => true,
                    'listContainer' => true,
                    'listFileVersions' => true,
                    'listRecycle' => true,
                    'stat' => true,
                  ),
            ),
      ),
      1 => 
          array (
            'webappOptions' => 
                array (
                  'viewMode' => 'VIEW_MODE_READ_ONLY',
                ),
          ),
    ),
)
```

sm app creates share object via sm share provider `createInternal` that inserts share into efss native db.

probably owncloud would inform the recepient by these request (should dig deeper to be sure):
"POST /ocs/v2.php/apps/files_sharing/api/v1/shares?format=json HTTP/1.1" 200
"GET /ocs/v2.php/apps/files_sharing/api/v1/shares?format=json&path=%2Ftest&reshares=true


## Receiver

Reva will call these in order:
1. "POST /index.php/apps/sciencemesh/~unauthenticated/api/user/GetUser HTTP/1.1" 200
2. "POST /index.php/apps/sciencemesh/~marie/api/ocm/addReceivedShare HTTP/1.1" 400

FIX: 400 in addReceivedShare
