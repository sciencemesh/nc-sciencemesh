<?php declare(strict_types=1);

namespace OCA\ScienceMesh;

use League\Flysystem\FilesystemInterface as Filesystem;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ResourceServer
{
    ////////////////////////////// CLASS PROPERTIES \\\\\\\\\\\\\\\\\\\\\\\\\\\\

    public const ERROR_CAN_NOT_DELETE_NON_EMPTY_CONTAINER = 'Only empty containers can be deleted, "%s" is not empty';
    public const ERROR_NOT_IMPLEMENTED_SPARQL = 'SPARQL Not Implemented';
    public const ERROR_PATH_DOES_NOT_EXIST = 'Requested path "%s" does not exist';
    public const ERROR_PATH_EXISTS = 'Requested path "%s" already exists';
    public const ERROR_POST_EXISTING_RESOURCE = 'Requested path "%s" already exists. Can not "POST" to existing resource. Use "PUT" instead';
    public const ERROR_PUT_NON_EXISTING_RESOURCE = self::ERROR_PATH_DOES_NOT_EXIST . '. Can not "PUT" non-existing resource. Use "POST" instead';
    public const ERROR_PUT_EXISTING_RESOURCE = self::ERROR_PATH_EXISTS . '. Can not "PUT" existing container.';
    public const ERROR_UNKNOWN_HTTP_METHOD = 'Unknown or unsupported HTTP METHOD "%s"';
    public const ERROR_CAN_NOT_PARSE_FOR_PATCH = 'Could not parse the requested resource for patching';
    private const MIME_TYPE_DIRECTORY = 'directory';
    private const QUERY_PARAM_HTTP_METHOD = 'http-method';

    /** @var string[] */
    private $availableMethods = [
        'DELETE',
        'GET',
        'HEAD',
        'OPTIONS',
        'PATCH',
        'POST',
        'PUT',
    ];
    /** @var Filesystem */
    private $filesystem;
    /** @var Response */
    private $response;
    private $baseUrl;

    //////////////////////////////// PUBLIC API \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    final public function __construct(Filesystem $filesystem, Response $response)
    {
        $this->filesystem = $filesystem;
        $this->response = $response;
        $this->baseUrl = '';
        $this->basePath = '';
    }

    final public function getFilesystem() {
        return $this->filesystem;
    }
    final public function getResponse() {
        return $this->response;
    }

    final public function respondToRequest(Request $request) : Response
    {
        $path = $request->getUri()->getPath();
        if ($this->basePath) {
            $path = str_replace($this->basePath, "", $path);
        }
        $path = rawurldecode($path);
    
        // @FIXME: The path can also come from a 'Slug' header

        $method = $this->getRequestMethod($request);

        $contents = $request->getBody()->getContents();
        
        return $this->handle($method, $path, $contents, $request);
    }

    ////////////////////////////// UTILITY METHODS \\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    private function getRequestMethod(Request $request) : string
    {
        $method = $request->getMethod();

        $queryParams = $request->getQueryParams();

        if (
            array_key_exists(self::QUERY_PARAM_HTTP_METHOD, $queryParams)
            && in_array(strtoupper($queryParams[self::QUERY_PARAM_HTTP_METHOD]), $this->availableMethods, true)
        ) {
            $method = strtoupper($queryParams[self::QUERY_PARAM_HTTP_METHOD]);
        }

        return $method;
    }

    public function setBaseUrl($url) {
        $this->baseUrl = $url;

        $uri = $this->baseUrl;
        $serverRequest = new \Laminas\Diactoros\ServerRequest(array(),array(), $this->baseUrl);
        $this->basePath = $serverRequest->getUri()->getPath();
    }

    private function handle(string $method, string $path, $contents, $request) : Response
    {
        $response = $this->response;
        $filesystem = $this->filesystem;

        // Lets assume the worst...
        $response = $response->withStatus(500);

        // Set Accept, Allow, and CORS headers
        $response = $response
            // ->withHeader('Access-Control-Allow-Origin', '*')
            // ->withHeader('Access-Control-Allow-Credentials','true')
            // ->withHeader('Access-Control-Allow-Headers', '*, authorization, accept, content-type')
            // @FIXME: Add correct headers to resources (for instance allow DELETE on a GET resource)
            // ->withAddedHeader('Accept-Patch', 'text/ldpatch')
            // ->withAddedHeader('Accept-Post', 'text/turtle, application/ld+json, image/bmp, image/jpeg')
            // ->withHeader('Allow', 'GET, HEAD, OPTIONS, PATCH, POST, PUT');
        ;

        switch ($method) {
            case 'DELETE':
                $response = $this->handleDeleteRequest($response, $path, $contents);
            break;
            case 'GET':
            case 'HEAD':
                $mime = $this->getRequestedMimeType($request->getHeaderLine("Accept"));
                $response = $this->handleReadRequest($response, $path, $contents, $mime);
                if ($method === 'HEAD') {
                    $response->getBody()->rewind();
                    $response->getBody()->write('');
                    $response = $response->withStatus("204"); // CHECKME: nextcloud will remove the updates-via header - any objections to give the 'HEAD' request a 'no content' response type?
                }
            break;
            case 'OPTIONS':
                $response = $response
                    ->withHeader('Vary', 'Accept')
                    ->withStatus('204')
                ;
            break;
            case 'POST':
                $pathExists = $filesystem->has($path);
                if ($pathExists) {
                    $mimetype = $filesystem->getMimetype($path);
                }
                if ($path === "/") {
                    $pathExists = true;
                    $mimetype = self::MIME_TYPE_DIRECTORY;
                }
                if ($pathExists === true) {
                    if ($mimetype === self::MIME_TYPE_DIRECTORY) {
                        $contentType= explode(";", $request->getHeaderLine("Content-Type"))[0];
                        $slug = $request->getHeaderLine("Slug");
                        if ($slug) {
                            $filename = $slug;
                        } else {
                            $filename = $this->guid();
                        }                        
                        switch ($contentType) {
                            case "text/plain":
                                $filename .= ".txt";
                            break;
                            case "text/html":
                                $filename .= ".html";
                            break;
                            case "application/json":
                            case "application/ld+json":
                                $filename .= ".json";
                            break;
                        }

                        $response = $this->handleCreateRequest($response, $path . $filename, $contents);
                    } else {
                        $response = $this->handleUpdateRequest($response, $path, $contents);
                    }
                } else {
                    $response = $this->handleCreateRequest($response, $path, $contents);
                }
            break;
            case 'PUT':
                if ($filesystem->has($path) === true) {
                    $response = $this->handleUpdateRequest($response, $path, $contents);
                } else {
                    $response = $this->handleCreateRequest($response, $path, $contents);
                }
            break;
            default:
                $message = vsprintf(self::ERROR_UNKNOWN_HTTP_METHOD, [$method]);
                throw new \LogicException($message);
                break;
        }

        return $response;
    }

    private function handleCreateRequest(Response $response, string $path, $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === true) {
            $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            // @FIXME: Handle error scenarios correctly (for instance trying to create a file underneath another file)
            $success = $filesystem->write($path, $contents);
            if ($success) {
                $response = $response->withHeader("Location", $this->baseUrl . $path);
                $response = $response->withStatus(201);
            } else {
                $response = $response->withStatus(500);
            }
        }

        return $response;
    }
    private function parentPath($path) {
        if ($path == "/") {
            return "/";
        }
        $pathicles = explode("/", $path);
        $end = array_pop($pathicles);
        if ($end == "") {
            array_pop($pathicles);
        }
        return implode("/", $pathicles) . "/";
    }
    
    private function handleCreateDirectoryRequest(Response $response, string $path) : Response
    {
        $filesystem = $this->filesystem;
        if ($filesystem->has($path) === true) {
            $message = vsprintf(self::ERROR_PUT_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            $success = $filesystem->createDir($path);
            $response = $response->withStatus($success ? 201 : 500);
        }

        return $response;
    }

    private function handleDeleteRequest(Response $response, string $path, $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path)) {
            $mimetype = $filesystem->getMimetype($path);

            if ($mimetype === self::MIME_TYPE_DIRECTORY) {
                $directoryContents = $filesystem->listContents($path, true);
                if (count($directoryContents) > 0) {
                    $status = 400;
                    $message = vsprintf(self::ERROR_CAN_NOT_DELETE_NON_EMPTY_CONTAINER, [$path]);
                    $response->getBody()->write($message);
                } else {
                    $success = $filesystem->deleteDir($path);
                    $status = $success ? 204 : 500;
                }
            } else {
                $success = $filesystem->delete($path);
                $status = $success ? 204 : 500;
            }

            $response = $response->withStatus($status);
        } else {
            $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(404);
        }

        return $response;
    }

    private function handleUpdateRequest(Response $response, string $path, string $contents) : Response
    {
        $filesystem = $this->filesystem;

        if ($filesystem->has($path) === false) {
            $message = vsprintf(self::ERROR_PUT_NON_EXISTING_RESOURCE, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(400);
        } else {
            $success = $filesystem->update($path, $contents);
            $response = $response->withStatus($success ? 201 : 500);
        }

        return $response;
    }

    private function getRequestedMimeType($accept) {        
        // text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8
        $mimes = explode(",", $accept);
        foreach ($mimes as $mime) {
                        $parts = explode(";", $mime);
                        $mimeInfo = $parts[0];
            switch ($mimeInfo) {
                case "text/turtle": // turtle
                case "application/ld+json": //json
                case "application/rdf+xml": //rdf
                    return $mimeInfo;
                break;
            }
        }            
        return '';
    }
    private function handleReadRequest(Response $response, string $path, $contents, $mime='') : Response
    {
        $filesystem = $this->filesystem;
        if ($path == "/") { // FIXME: this is a patch to make it work for Solid-Nextcloud; we should be able to just list '/';
            $contents = $this->listDirectory($path);
            $response->getBody()->write($contents);
            $response = $response->withHeader("Content-type", "application/json");
            $response = $response->withStatus(200);
        } else if ($filesystem->has($path) === false) {            
            $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
            $response->getBody()->write($message);
            $response = $response->withStatus(404);
        } else {
            $mimetype = $filesystem->getMimetype($path);
            if ($mimetype === self::MIME_TYPE_DIRECTORY) {
                $contents = $this->listDirectory($path);
                $response->getBody()->write($contents);
                $response = $response->withHeader("Content-type", "application/json");
                $response = $response->withStatus(200);
            } else {
                if ($filesystem->has($path)) {
                    $mimetype = $filesystem->getMimetype($path);
                    $contents = $filesystem->read($path);
                    $mimetype = $filesystem->getMimetype($path);
                    if ($contents !== false) {
                        $response->getBody()->write($contents);
                        $response = $response->withHeader("Content-type", $mimetype);
                        $response = $response->withStatus(200);
                    }
                } else {
                    $message = vsprintf(self::ERROR_PATH_DOES_NOT_EXIST, [$path]);
                    $response->getBody()->write($message);
                    $response = $response->withStatus(404);
                }
            }
        }

        return $response;
    }
    
    private function guid() {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    private function listDirectory($path) {
        $filesystem = $this->filesystem;
        if ($path == "/") {
            $listContents = $filesystem->listContents(".");// FIXME: this is a patch to make it work for Solid-Nextcloud; we should be able to just list '/';
        } else {
            $listContents = $filesystem->listContents($path);
        }
        // CHECKME: maybe structure this data als RDF/PHP
        // https://www.easyrdf.org/docs/rdf-formats-php
        return json_encode($listContents, JSON_PRETTY_PRINT);
    }
}
