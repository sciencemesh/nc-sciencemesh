<?php

namespace OCA\ScienceMesh\Controller;


class ContactsController extends Controller {

    public function deleteContact() {
      return new TextPlainResponse('test', Http::STATUS_OK);
    }
}