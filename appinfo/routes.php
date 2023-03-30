<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\ScienceMesh\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */

namespace OCA\MyApp\AppInfo;

use OCA\ScienceMesh\AppInfo\ScienceMeshApp;

$routes_array = [
	'routes' => [
		// reva routes
		['name' => 'reva#Authenticate', 'url' => '/~{userId}/api/auth/Authenticate', 'verb' => 'POST'],
		['name' => 'reva#AddGrant', 'url' => '/~{userId}/api/storage/AddGrant', 'verb' => 'POST'],
		['name' => 'reva#CreateDir', 'url' => '/~{userId}/api/storage/CreateDir', 'verb' => 'POST'],
		['name' => 'reva#CreateHome', 'url' => '/~{userId}/api/storage/CreateHome', 'verb' => 'POST'],
		['name' => 'reva#CreateReference', 'url' => '/~{userId}/api/storage/CreateReference', 'verb' => 'POST'],
		['name' => 'reva#CreateStorageSpace', 'url' => '/~{userId}/api/storage/CreateStorageSpace', 'verb' => 'POST'],
		['name' => 'reva#Delete', 'url' => '/~{userId}/api/storage/Delete', 'verb' => 'POST'],
		['name' => 'reva#EmptyRecycle', 'url' => '/~{userId}/api/storage/EmptyRecycle', 'verb' => 'POST'],
		['name' => 'reva#GetMD', 'url' => '/~{userId}/api/storage/GetMD', 'verb' => 'POST'],
		['name' => 'reva#GetPathByID', 'url' => '/~{userId}/api/storage/GetPathByID', 'verb' => 'POST'],
		['name' => 'reva#InitiateUpload', 'url' => '/~{userId}/api/storage/InitiateUpload', 'verb' => 'POST'],
		['name' => 'reva#ListFolder', 'url' => '/~{userId}/api/storage/ListFolder', 'verb' => 'POST'],
		['name' => 'reva#ListGrants', 'url' => '/~{userId}/api/storage/ListGrants', 'verb' => 'POST'],
		['name' => 'reva#ListRecycle', 'url' => '/~{userId}/api/storage/ListRecycle', 'verb' => 'POST'],
		['name' => 'reva#ListRevisions', 'url' => '/~{userId}/api/storage/ListRevisions', 'verb' => 'POST'],
		['name' => 'reva#Move', 'url' => '/~{userId}/api/storage/Move', 'verb' => 'POST'],
		['name' => 'reva#RemoveGrant', 'url' => '/~{userId}/api/storage/RemoveGrant', 'verb' => 'POST'],
		['name' => 'reva#RestoreRecycleItem', 'url' => '/~{userId}/api/storage/RestoreRecycleItem', 'verb' => 'POST'],
		['name' => 'reva#RestoreRevision', 'url' => '/~{userId}/api/storage/RestoreRevision', 'verb' => 'POST'],
		['name' => 'reva#SetArbitraryMetadata', 'url' => '/~{userId}/api/storage/SetArbitraryMetadata', 'verb' => 'POST'],
		['name' => 'reva#UnsetArbitraryMetadata', 'url' => '/~{userId}/api/storage/UnsetArbitraryMetadata', 'verb' => 'POST'],
		['name' => 'reva#UpdateGrant', 'url' => '/~{userId}/api/storage/UpdateGrant', 'verb' => 'POST'],
		['name' => 'reva#Upload', 'url' => '/~{userId}/api/storage/Upload/{path}', 'verb' => 'PUT', 'requirements' => ['path' => '.+']],

		// OCM routes
		['name' => 'ocm#addSentShare', 'url' => '/~{userId}/api/ocm/addSentShare', 'verb' => 'POST'],
		['name' => 'ocm#addReceivedShare', 'url' => '/~{userId}/api/ocm/addReceivedShare', 'verb' => 'POST'],
		['name' => 'ocm#GetSentShare', 'url' => '/~{userId}/api/ocm/GetSentShare', 'verb' => 'POST'],
		['name' => 'ocm#Unshare', 'url' => '/~{userId}/api/ocm/Unshare', 'verb' => 'POST'],
		['name' => 'ocm#UpdateShare', 'url' => '/~{userId}/api/ocm/UpdateShare', 'verb' => 'POST'],
		['name' => 'ocm#ListSentShares', 'url' => '/~{userId}/api/ocm/ListSentShares', 'verb' => 'POST'],
		['name' => 'ocm#ListSentShares', 'url' => '/~{userId}/api/ocm/ListShares', 'verb' => 'POST'], // alias for ListSentShares
		['name' => 'ocm#ListReceivedShares', 'url' => '/~{userId}/api/ocm/ListReceivedShares', 'verb' => 'POST'],
		['name' => 'ocm#GetReceivedShare', 'url' => '/~{userId}/api/ocm/GetReceivedShare', 'verb' => 'POST'],
		['name' => 'ocm#UpdateSentShare', 'url' => '/~{userId}/api/ocm/UpdateSentShare', 'verb' => 'POST'],
		['name' => 'ocm#UpdateReceivedShare', 'url' => '/~{userId}/api/ocm/UpdateReceivedShare', 'verb' => 'POST'],
		['name' => 'ocm#GetUser', 'url' => '/~{dummy}/api/user/GetUser', 'verb' => 'POST'],

		/*
		['name' => 'storage#createHome', 'url' => '/~{userId}/CreateHome', 'verb' => 'POST'],
		['name' => 'storage#listFolder', 'url' => '/~{userId}/ListFolder', 'verb' => 'POST'],
		['name' => 'storage#initiateUpload', 'url' => '/~{userId}/InitiateUpload', 'verb' => 'POST'],
		['name' => 'storage#upload', 'url' => '/~{userId}/Upload', 'verb' => 'POST'],
		['name' => 'storage#handleUpload', 'url' => '/~{userId}/Upload/{path}', 'verb' => 'PUT'],
		['name' => 'storage#getMD', 'url' => '/~{userId}/GetMD', 'verb' => 'POST'],
		*/

		// Files routes
		['name' => 'storage#handleGet', 'url' => '/~{userId}/files/{path}', 'verb' => 'GET', 'requirements' => ['path' => '.+']],
		['name' => 'storage#handlePost', 'url' => '/~{userId}/files/{path}', 'verb' => 'POST', 'requirements' => ['path' => '.+']],
		['name' => 'storage#handlePut', 'url' => '/~{userId}/files/{path}', 'verb' => 'PUT', 'requirements' => ['path' => '.+']],
		['name' => 'storage#handleDelete', 'url' => '/~{userId}/files/{path}', 'verb' => 'DELETE', 'requirements' => ['path' => '.+']],
		['name' => 'storage#handleHead', 'url' => '/~{userId}/files/{path}', 'verb' => 'HEAD', 'requirements' => ['path' => '.+']],


		// Internal app routes
		['name' => 'app#contacts', 'url' => '/', 'verb' => 'GET'],
		['name' => 'app#generate', 'url' => '/generate', 'verb' => 'GET'],
		['name' => 'app#accept', 'url' => '/accept', 'verb' => 'GET'],
		['name' => 'app#contacts', 'url' => '/contacts', 'verb' => 'GET'],
		['name' => 'app#settings', 'url' => '/settings', 'verb' => 'GET'],
		['name' => 'app#invitationsGenerate', 'url' => '/invitations/generate', 'verb' => 'GET'],
		['name' => 'app#contactsAccept', 'url' => '/contacts/accept', 'verb' => 'POST'],
		['name' => 'app#contactsFindUsers', 'url' => '/contacts/users', 'verb' => 'GET'],

		// page routes
		['name' => 'page#get_internal_metrics', 'url' => '/internal_metrics', 'verb' => 'GET'],
		['name' => 'page#get_metrics', 'url' => '/metrics', 'verb' => 'GET'],

		// settings routes
		["name" => "settings#save_settings", "url" => "/ajax/settings/address", "verb" => "PUT"],
		["name" => "settings#get_settings", "url" => "/ajax/settings", "verb" => "GET"],
		["name" => "settings#get_sciencemesh_settings", "url" => "/sciencemesh_settings", "verb" => "GET"],
		["name" => "settings#save_sciencemesh_settings", "url" => "/ajax/sciencemesh_settings/save", "verb" => "GET"],
		["name" => "settings#check_connection_settings", "url" => "/ajax/check_connection_settings", "verb" => "GET"]

	]
];

$application = new ScienceMeshApp();
$application->registerRoutes($this, $routes_array);
