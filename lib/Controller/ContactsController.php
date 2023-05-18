<?php

namespace OCA\ScienceMesh\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\Controller;
use OCA\ScienceMesh\RevaHttpClient;
use OCA\ScienceMesh\Plugins\ScienceMeshGenerateTokenPlugin;
use OCA\ScienceMesh\Plugins\ScienceMeshAcceptTokenPlugin;
use OCA\ScienceMesh\Controller\RevaController;

class ContactsController extends Controller {

    public function deleteContact() {
      error_log('contact '.$_POST['username'].' is deleted');
      return new TextPlainResponse(true, Http::STATUS_OK);
    }
}