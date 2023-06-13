<?php

namespace OCA\ScienceMesh\Controller;

use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCA\ScienceMesh\AppConfig;
use OCA\ScienceMesh\Crypt;
use OCA\ScienceMesh\DocumentService;
use OCA\ScienceMesh\RevaHttpClient;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\Http;
use OCA\Sciencemesh\ServerConfig;
use OCP\IConfig;

/**
 * Settings controller for the administration page
 */
class ApiController extends Controller
{
    private $logger;
	private $config;
	private $urlGenerator;
	private $serverConfig;
	private $sciencemeshConfig;
	private $userId;
	private $db;
	const CATALOG_URL = "https://iop.sciencemesh.uni-muenster.de/iop/mentix/sitereg";

	/**
	 * @param string $AppName - application name
	 * @param IRequest $request - request object
	 * @param IURLGenerator $urlGenerator - url generator service
	 * @param IL10N $trans - l10n service
	 * @param ILogger $logger - logger
	 * @param AppConfig $config - application configuration
	 */
	public function __construct(
		$AppName,
		IRequest $request,
		IURLGenerator $urlGenerator,
		IL10N $trans,
		ILogger $logger,
		AppConfig $config,
		IConfig $sciencemeshConfig,
		IDBConnection $db,
		$userId
	) {
        parent::__construct($AppName, $request);
		$this->serverConfig = new \OCA\ScienceMesh\ServerConfig($sciencemeshConfig);

		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->config = $config;
		$this->sciencemeshConfig = $sciencemeshConfig;
        $this->db = $db;
		$this->request = $request;
    }

    /**
     * Check if the request is authenticated by comparing the request's API key with the stored inviteManagerApikey.
     *
	 * 
	 * @PublicPage
     * @param IRequest $request
     * @return bool
     */
    public function authentication($request)
    {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from('appconfig')
            ->where(
                $qb->expr()->eq('appid', $qb->createNamedParameter('sciencemesh', IQueryBuilder::PARAM_STR))
            )
            ->andWhere(
                $qb->expr()->eq('configkey', $qb->createNamedParameter('inviteManagerApikey', IQueryBuilder::PARAM_STR))
            );

        $cursor = $qb->execute();
        $row = $cursor->fetchAll();

		if ($row[0]['configvalue'] == $this->request->getHeader('apikey')) {
            return true;
        } else {
            return false;
        }
    }
	
	/**
	 * 
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function addToken($initiator, $request){
		if(!$this->authentication($this->request)) return new DataResponse((['message' => 'Authentication failed!','status' => 412, 'data' => null]), Http::STATUS_INTERNAL_SERVER_ERROR);

		if(!$this->request->getParam('token') and !$initiator and !$this->request->getParam('expiration') and !$this->request->getParam('description')){
			return new DataResponse(['message' => 'values are not provided properly!','status' => 412, 'data' => null], Http::STATUS_OK);
		}

		$qb = $this->db->getQueryBuilder();

        $qb->select('*')
		->from('ocm_tokens')
		->where(
			$qb->expr()->eq('initiator', $qb->createNamedParameter($initiator, IQueryBuilder::PARAM_STR))
		)
		->andWhere(
			$qb->expr()->eq('token', $qb->createNamedParameter($this->request->getParam('token'), IQueryBuilder::PARAM_STR))
		);
        $cursor = $qb->execute();
        $row = $cursor->fetchAll();
		
		$expiration = $this->request->getParam('expiration');

		if(empty($row)){
			$qb->insert('ocm_tokens')
			->values(
				array(
					'token' => $qb->createNamedParameter($this->request->getParam('token'), IQueryBuilder::PARAM_STR),
					'initiator' => $qb->createNamedParameter($initiator, IQueryBuilder::PARAM_STR),
					'expiration' => $qb->createNamedParameter($expiration, IQueryBuilder::PARAM_STR),
					'description' => $qb->createNamedParameter($this->request->getParam('description'), IQueryBuilder::PARAM_STR)
				)
			);
			$cursor = $qb->execute();
		}else{
			$cursor = 0;
		}

		if($cursor)
			return new DataResponse((['message' => 'Token added!','status' => 200, 'data' => $cursor]), Http::STATUS_OK);
		else if($cursor == 0)
			return new DataResponse((['message' => 'Token already exists!','status' => 204, 'data' => 0]), Http::STATUS_BAD_REQUEST);
		else
			return new DataResponse((['message' => 'Token added failed!','status' => 400, 'data' => 0]), Http::STATUS_BAD_REQUEST);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function getToken($initiator){

		if(!$this->authentication($this->request)) return new DataResponse((['message' => 'Authentication failed!','status' => 412, 'data' => null]), Http::STATUS_INTERNAL_SERVER_ERROR);

		$qb = $this->db->getQueryBuilder();

        $qb->select('*')
		->from('ocm_tokens')
		->where(
			$qb->expr()->eq('token', $qb->createNamedParameter($this->request->getParam('token'), IQueryBuilder::PARAM_STR))
		);

        $cursor = $qb->execute();
        $row = $cursor->fetchAll();

		if(empty($row)){
			return new DataResponse((['message' => 'No Token found!','status' => 201, 'data' => '']), Http::STATUS_BAD_REQUEST);
		}else{
			return new DataResponse(($row[0]), Http::STATUS_OK);
		}
		
	}

	
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function tokensList($initiator){

		if(!$this->authentication($this->request)) return new DataResponse((['message' => 'Authentication failed!','status' => 412, 'data' => null]), Http::STATUS_INTERNAL_SERVER_ERROR);

		$qb = $this->db->getQueryBuilder();

        $qb->select('*')
		->where(
			$qb->expr()->eq('initiator', $qb->createNamedParameter($initiator, IQueryBuilder::PARAM_STR))
		)
		->from('ocm_tokens');

        $cursor = $qb->execute();
        $row = $cursor->fetchAll();
		return new DataResponse(($row), Http::STATUS_OK);
	}

	
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function addRemoteUser($initiator){

		if(!$this->authentication($this->request)) return new DataResponse((['message' => 'Authentication failed!','status' => 412, 'data' => null]), Http::STATUS_INTERNAL_SERVER_ERROR);
		
		if(!$this->request->getParam('opaqueUserId') and !$this->request->getParam('idp') and !$this->request->getParam('email') and !$this->request->getParam('displayName')){
			return new DataResponse((['message' => 'values are not provided properly!','status' => 412, 'data' => null]), Http::STATUS_OK);
		}

		$qb = $this->db->getQueryBuilder();
		

        $qb->select('*')
		->from('ocm_remote_users')
		->where(
			$qb->expr()->eq('opaque_user_id', $qb->createNamedParameter($this->request->getParam('opaqueUserId'), IQueryBuilder::PARAM_STR))
		)
		->andWhere(
			$qb->expr()->eq('idp', $qb->createNamedParameter($this->request->getParam('idp'), IQueryBuilder::PARAM_STR))
		)
		->andWhere(
			$qb->expr()->eq('email', $qb->createNamedParameter($this->request->getParam('email'), IQueryBuilder::PARAM_STR))
		);
        $cursor = $qb->execute();
        $row = $cursor->fetchAll();
		
		if(empty($row)){
			$qb->insert('ocm_remote_users')
			->values(
				array(
					'initiator' => $qb->createNamedParameter($initiator, IQueryBuilder::PARAM_STR),
					'opaque_user_id' => $qb->createNamedParameter($this->request->getParam('opaqueUserId'), IQueryBuilder::PARAM_STR),
					'idp' => $qb->createNamedParameter($this->request->getParam('idp'), IQueryBuilder::PARAM_STR),
					'email' => $qb->createNamedParameter($this->request->getParam('email'), IQueryBuilder::PARAM_STR),
					'display_name' => $qb->createNamedParameter($this->request->getParam('displayName'), IQueryBuilder::PARAM_STR)
				)
			);
			$cursor = $qb->execute();
		}else{
			$cursor = 0;
		}

		if($cursor || !empty($row))
			if(!empty($row))
				return new DataResponse((['message' => 'User exists!','status' => 400, 'data' => $row]), Http::STATUS_BAD_REQUEST);
			if($cursor)
				return new DataResponse((['message' => 'User added!','status' => 200, 'data' => $cursor]), Http::STATUS_OK);
		else
			return new DataResponse((['message' => 'User does not added!','status' => 500, 'data' => 0]), Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	
	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function getRemoteUser($initiator){

		if(!$this->authentication($this->request)) return new DataResponse((['message' => 'Authentication failed!','status' => 412, 'data' => null]), Http::STATUS_INTERNAL_SERVER_ERROR);

		$qb = $this->db->getQueryBuilder();

        $qb->select('*')
		->from('ocm_remote_users')
		->where(
			$qb->expr()->eq('initiator', $qb->createNamedParameter($initiator, IQueryBuilder::PARAM_STR))
		)
		->andWhere(
			$qb->expr()->eq('idp', $qb->createNamedParameter($this->request->getParam('idp'), IQueryBuilder::PARAM_STR))
		)
		->andWhere(
			$qb->expr()->eq('opaque_user_id', $qb->createNamedParameter($this->request->getParam('opaqueUserId'), IQueryBuilder::PARAM_STR))
		);

        $cursor = $qb->execute();
        $row = $cursor->fetchAll();

		if(empty($row)){
			return new DataResponse((['message' => 'User not found!','status' => 201, 'data' => '']), Http::STATUS_BAD_REQUEST);
		}else{
			return new DataResponse(($this->CastToRevaUser($row[0])), Http::STATUS_OK);
		}
		
	}


	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function findRemoteUser($initiator){

		$qb = $this->db->getQueryBuilder();

        $qb->select('*')
		->from('ocm_remote_users')
		->where(
			$qb->expr()->eq('initiator', $qb->createNamedParameter($initiator, IQueryBuilder::PARAM_STR))
		)
		->andWhere(
			$qb->expr()->orX(
				$qb->expr()->like('opaque_user_id', $qb->createNamedParameter($this->request->getParam('search'), IQueryBuilder::PARAM_STR)),
				$qb->expr()->like('idp', $qb->createNamedParameter($this->request->getParam('search'), IQueryBuilder::PARAM_STR)),
				$qb->expr()->like('email', $qb->createNamedParameter($this->request->getParam('search'), IQueryBuilder::PARAM_STR)),
				$qb->expr()->like('display_name', $qb->createNamedParameter($this->request->getParam('search'), IQueryBuilder::PARAM_STR))
			)
		);

        $cursor = $qb->execute();
        $row = $cursor->fetchAll();

		if(empty($row)){
			return new DataResponse(([ ]), Http::STATUS_OK);
		}else{
			$result = [];
			foreach($row as $item){
				$result[] = $this->CastToRevaUser($item);
			}
			return new DataResponse(($result), Http::STATUS_OK);
		}
		
	}

	private function CastToRevaUser($user){
		return array(
			'opaqueUserId' => $user['opaque_user_id'],
			'idp' => $user['idp'],
			'email' => $user['email'],
			'displayName' => $user['display_name']
		);
	}
}