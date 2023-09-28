### Creating a share

## Sender

Search for contacts, click on contact to create a share. this step appends postfix for sm shares to distinguish them 
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
```
array (\n  'type' => 2,\n  'id' => \n  array (\n    'opaque_id' => 'fileid-/home/test',\n  ),\n  'checksum' => \n  array (\n    'type' => 1,\n    'sum' => '',\n  ),\n  'etag' => '65129328493b0',\n  'mime_type' => 'folder',\n  'mtime' => \n  array (\n    'seconds' => 1695716136,\n  ),\n  'path' => '/home/test',\n  'permissions' => 31,\n  'size' => 0,\n  'owner' => \n  array (\n    'opaque_id' => 'einstein',\n    'idp' => 'revaowncloud1.docker',\n  ),\n)
```
4. "POST /index.php/apps/sciencemesh/~einstein/api/storage/GetMD HTTP/1.1" 200
sm response:
```
array (\n  'type' => 2,\n  'id' => \n  array (\n    'opaque_id' => 'fileid-/home/test',\n  ),\n  'checksum' => \n  array (\n    'type' => 1,\n    'sum' => '',\n  ),\n  'etag' => '65129328493b0',\n  'mime_type' => 'folder',\n  'mtime' => \n  array (\n    'seconds' => 1695716136,\n  ),\n  'path' => '/home/test',\n  'permissions' => 31,\n  'size' => 0,\n  'owner' => \n  array (\n    'opaque_id' => 'einstein',\n    'idp' => 'revaowncloud1.docker',\n  ),\n)
```
5. "POST /index.php/apps/sciencemesh/~einstein/api/ocm/addSentShare HTTP/1.1" 201
reva payload:
```
array (\n  'userId' => 'einstein',\n  '_route' => 'sciencemesh.reva.addSentShare',\n  'resourceId' => \n  array (\n    'storageId' => 'nextcloud',\n    'opaqueId' => 'fileid-/home/test',\n  ),\n  'name' => 'test',\n  'token' => 'Y7bWUulmHrhfUJ8LRknNpkZQGcRRkMk7',\n  'grantee' => \n  array (\n    'type' => 'GRANTEE_TYPE_USER',\n    'userId' => \n    array (\n      'idp' => 'revaowncloud2.docker',\n      'opaqueId' => 'marie',\n    ),\n  ),\n  'owner' => \n  array (\n    'idp' => 'revaowncloud1.docker',\n    'opaqueId' => 'einstein',\n    'type' => 'USER_TYPE_PRIMARY',\n  ),\n  'creator' => \n  array (\n    'idp' => 'revaowncloud1.docker',\n    'opaqueId' => 'einstein',\n  ),\n  'ctime' => \n  array (\n    'seconds' => '1695716163',\n    'nanos' => 943856286,\n  ),\n  'mtime' => \n  array (\n    'seconds' => '1695716163',\n    'nanos' => 943856286,\n  ),\n  'shareType' => 'SHARE_TYPE_USER',\n  'accessMethods' => \n  array (\n    0 => \n    array (\n      'webdavOptions' => \n      array (\n        'permissions' => \n        array (\n          'getPath' => true,\n          'getQuota' => true,\n          'initiateFileDownload' => true,\n          'listGrants' => true,\n          'listContainer' => true,\n          'listFileVersions' => true,\n          'listRecycle' => true,\n          'stat' => true,\n        ),\n      ),\n    ),\n    1 => \n    array (\n      'webappOptions' => \n      array (\n        'viewMode' => 'VIEW_MODE_READ_ONLY',\n      ),\n    ),\n  ),\n)
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
