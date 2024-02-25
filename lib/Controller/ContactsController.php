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
use OCA\ScienceMesh\RevaHttpClient;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

class ContactsController extends Controller
{
    /** @var ?string */
    private ?string $userId;

    /** @var RevaHttpClient */
    private RevaHttpClient $revaHttpClient;

    public function __construct(
        string         $appName,
        IRequest       $request,
        ?string        $userId,
        RevaHttpClient $revaHttpClient
    )
    {
        parent::__construct($appName, $request);

        $this->userId = $userId;
        $this->revaHttpClient = $revaHttpClient;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contacts(): TemplateResponse
    {
        $contactsData = [
        ];
        return new TemplateResponse('sciencemesh', 'contacts', $contactsData);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contactsAccept(): PlainResponse
    {
        $providerDomain = $this->request->getParam('providerDomain');
        $token = $this->request->getParam('token');
        $result = $this->revaHttpClient->acceptInvite($providerDomain, $token, $this->userId);
        return new PlainResponse($result, Http::STATUS_OK);
    }

    // TODO: @Mahdi @Giuseppe: is delete contact implemented in Reva?
    public function deleteContact(): PlainResponse
    {
        error_log('contact ' . $_POST['username'] . ' is deleted');
        return new PlainResponse(true, Http::STATUS_OK);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function contactsFindUsers($searchToken = ""): PlainResponse
    {
        $find_users_json = $this->revaHttpClient->findAcceptedUsers($this->userId);

        $find_users = json_decode($find_users_json, false);
        $return_users = array();
        if (strlen($searchToken) > 0) {
            if (!empty($find_users)) {
                for ($i = count($find_users); $i >= 0; $i--) {
                    if (str_contains($find_users[$i]->display_name, $searchToken) and !is_null($find_users[$i])) {
                        $return_users[] = $find_users[$i];
                    }
                }
            }
        } else {
            $return_users = json_decode($find_users_json, false);
        }

        error_log('test:' . json_encode($return_users));
        return new PlainResponse(json_encode($return_users), Http::STATUS_OK);
    }
}
