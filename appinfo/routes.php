<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\ScienceMesh\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */

namespace OCA\ScienceMesh\AppInfo;

$routes_array = [
    'routes' => [
        // app routes.
        ['name' => 'app#contacts', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'app#generate', 'url' => '/generate', 'verb' => 'GET'],
        ['name' => 'app#accept', 'url' => '/accept', 'verb' => 'GET'],
        ['name' => 'app#settings', 'url' => '/settings', 'verb' => 'GET'],
        ['name' => 'app#invitationsSends', 'url' => '/invitations/emailsend', 'verb' => 'POST'],
        ['name' => 'app#invitationsGenerate', 'url' => '/invitations/generate', 'verb' => 'GET'],

        // auth routes.
        ['name' => 'auth#Authenticate', 'url' => '/~{userId}/api/auth/Authenticate', 'verb' => 'POST'],

        // contacts routes.
        ['name' => 'contacts#contacts', 'url' => '/contacts', 'verb' => 'GET'],
        ['name' => 'contacts#contactsAccept', 'url' => '/contacts/accept', 'verb' => 'POST'],
        ['name' => 'contacts#deleteContact', 'url' => '/contact/deleteContact', 'verb' => 'POST'],
        ['name' => 'contacts#contactsFindUsers', 'url' => '/contacts/users', 'verb' => 'GET'],

        // ocm routes.
        ['name' => 'ocm#addReceivedShare', 'url' => '/~{userId}/api/ocm/addReceivedShare', 'verb' => 'POST'],
        ['name' => 'ocm#addSentShare', 'url' => '/~{userId}/api/ocm/addSentShare', 'verb' => 'POST'],
        ['name' => 'ocm#getReceivedShare', 'url' => '/~{userId}/api/ocm/GetReceivedShare', 'verb' => 'POST'],
        // See: https://github.com/cs3org/reva/pull/4115#discussion_r1308371946
        // we need to handle this route for both nobody and userId.
        ['name' => 'ocm#getSentShare', 'url' => '/~{userId}/api/ocm/GetSentShare', 'verb' => 'POST'],
        ['name' => 'ocm#getSentShareByToken', 'url' => '/~{userId}/api/ocm/GetSentShareByToken', 'verb' => 'POST'],
        ['name' => 'ocm#listReceivedShares', 'url' => '/~{userId}/api/ocm/ListReceivedShares', 'verb' => 'POST'],
        // TODO: @Mahdi why do we have alias here? check with @Giuseppe and Reva EFSS code.
        // check in reva code, and make it to use clear names like ListSentShares and ListRxShares
        ['name' => 'ocm#listSentShares', 'url' => '/~{userId}/api/ocm/ListSentShares', 'verb' => 'POST'],
        // alias for ListSentShares. https://github.com/cs3org/reva/blob/76d29f92b4872df37d7c3ac78f6a1574df1d320d/pkg/ocm/share/repository/nextcloud/nextcloud.go#L267
        ['name' => 'ocm#listSentShares', 'url' => '/~{userId}/api/ocm/ListShares', 'verb' => 'POST'],
        ['name' => 'ocm#updateReceivedShare', 'url' => '/~{userId}/api/ocm/UpdateReceivedShare', 'verb' => 'POST'],
        ['name' => 'ocm#updateSentShare', 'url' => '/~{userId}/api/ocm/UpdateSentShare', 'verb' => 'POST'],
        # TODO: @Mahdi where is UpdateShare endpoint controller function? not implemented?
        ['name' => 'ocm#updateShare', 'url' => '/~{userId}/api/ocm/UpdateShare', 'verb' => 'POST'],
        ['name' => 'ocm#unshare', 'url' => '/~{userId}/api/ocm/Unshare', 'verb' => 'POST'],

        // page routes.
        ['name' => 'page#get_metrics', 'url' => '/metrics', 'verb' => 'GET'],
        ['name' => 'page#get_internal_metrics', 'url' => '/internal_metrics', 'verb' => 'GET'],

        // settings routes.
        ["name" => "settings#saveSettings", "url" => "/ajax/settings/address", "verb" => "PUT"],
        ["name" => "settings#saveSciencemeshSettings", "url" => "/ajax/sciencemesh_settings/save", "verb" => "GET"],
        ["name" => "settings#checkConnectionSettings", "url" => "/ajax/check_connection_settings", "verb" => "GET"],

        // storage routes.
        ['name' => 'storage#addGrant', 'url' => '/~{userId}/api/storage/AddGrant', 'verb' => 'POST'],
        ['name' => 'storage#createDir', 'url' => '/~{userId}/api/storage/CreateDir', 'verb' => 'POST'],
        ['name' => 'storage#createHome', 'url' => '/~{userId}/api/storage/CreateHome', 'verb' => 'POST'],
        ['name' => 'storage#createReference', 'url' => '/~{userId}/api/storage/CreateReference', 'verb' => 'POST'],
        ['name' => 'storage#createStorageSpace', 'url' => '/~{userId}/api/storage/CreateStorageSpace', 'verb' => 'POST'],
        ['name' => 'storage#delete', 'url' => '/~{userId}/api/storage/Delete', 'verb' => 'POST'],
        ['name' => 'storage#download', 'url' => '/~{userId}/api/storage/Download/{path}', 'verb' => 'GET', 'requirements' => array('path' => '.+')],
        ['name' => 'storage#emptyRecycle', 'url' => '/~{userId}/api/storage/EmptyRecycle', 'verb' => 'POST'],
        ['name' => 'storage#getMD', 'url' => '/~{userId}/api/storage/GetMD', 'verb' => 'POST'],
        ['name' => 'storage#getPathByID', 'url' => '/~{userId}/api/storage/GetPathByID', 'verb' => 'POST'],
        ['name' => 'storage#initiateUpload', 'url' => '/~{userId}/api/storage/InitiateUpload', 'verb' => 'POST'],
        ['name' => 'storage#listFolder', 'url' => '/~{userId}/api/storage/ListFolder', 'verb' => 'POST'],
        ['name' => 'storage#listGrants', 'url' => '/~{userId}/api/storage/ListGrants', 'verb' => 'POST'],
        ['name' => 'storage#listRecycle', 'url' => '/~{userId}/api/storage/ListRecycle', 'verb' => 'POST'],
        ['name' => 'storage#listRevisions', 'url' => '/~{userId}/api/storage/ListRevisions', 'verb' => 'POST'],
        # TODO: @Mahdi where is Move endpoint controller function? not implemented?
        ['name' => 'storage#move', 'url' => '/~{userId}/api/storage/Move', 'verb' => 'POST'],
        ['name' => 'storage#removeGrant', 'url' => '/~{userId}/api/storage/RemoveGrant', 'verb' => 'POST'],
        ['name' => 'storage#restoreRecycleItem', 'url' => '/~{userId}/api/storage/RestoreRecycleItem', 'verb' => 'POST'],
        ['name' => 'storage#restoreRevision', 'url' => '/~{userId}/api/storage/RestoreRevision', 'verb' => 'POST'],
        ['name' => 'storage#setArbitraryMetadata', 'url' => '/~{userId}/api/storage/SetArbitraryMetadata', 'verb' => 'POST'],
        ['name' => 'storage#unsetArbitraryMetadata', 'url' => '/~{userId}/api/storage/UnsetArbitraryMetadata', 'verb' => 'POST'],
        ['name' => 'storage#updateGrant', 'url' => '/~{userId}/api/storage/UpdateGrant', 'verb' => 'POST'],
        ['name' => 'storage#upload', 'url' => '/~{userId}/api/storage/Upload/{path}', 'verb' => 'PUT', 'requirements' => ['path' => '.+']],

        // user routes.
        ['name' => 'user#getUser', 'url' => '/~{dummy}/api/user/GetUser', 'verb' => 'POST'],
        ['name' => 'user#getUserByClaim', 'url' => '/~{dummy}/api/user/GetUserByClaim', 'verb' => 'POST'],
    ]
];

$application = new ScienceMeshApp();
$application->registerRoutes($this, $routes_array);
