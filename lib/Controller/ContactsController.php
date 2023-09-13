<?php
/**
 * ownCloud - sciencemesh
 *
 * This file is licensed under the MIT License. See the LICENCE file.
 * @license MIT
 * @copyright Sciencemesh 2020 - 2023
 *
 * @author Michiel De Jong <michiel@pondersource.com>
 * @author Mohammad Mahdi Baghbani Pourvahid <mahdi-baghbani@azadehafzar.ir>
 */

namespace OCA\ScienceMesh\Controller;

use OCA\ScienceMesh\PlainResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;

class ContactsController extends Controller
{
    // TODO: @Mahdi @Giuseppe: is delete contact implemented in Reva?
    public function deleteContact(): PlainResponse
    {
        error_log('contact ' . $_POST['username'] . ' is deleted');
        return new PlainResponse(true, Http::STATUS_OK);
    }
}
