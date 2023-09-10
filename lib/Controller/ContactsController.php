<?php

namespace OCA\ScienceMesh\Controller;

use OCA\ScienceMesh\PlainResponse;
use OCA\ScienceMesh\Plugins\ScienceMeshAcceptTokenPlugin;
use OCA\ScienceMesh\Plugins\ScienceMeshGenerateTokenPlugin;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;

class ContactsController extends Controller
{

    public function deleteContact()
    {
        error_log('contact ' . $_POST['username'] . ' is deleted');
        return new PlainResponse(true, Http::STATUS_OK);
    }
}
