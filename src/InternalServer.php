<?php

namespace donatj\MockWebServer;

use donatj\MockWebServer\Exceptions\ServerException;

/**
 * Class InternalServer
 *
 * @internal
 */
class InternalServer {

	/**
	 * @var string
	 */
	private $tmpPath;
	/**
	 * @var \donatj\MockWebServer\RequestInfo
	 */
	private $request;
	/**
	 * @var callable
	 */
	private $header;

	/**
	 * InternalServer constructor.
	 *
	 * @param string                            $tmpPath
	 * @param \donatj\MockWebServer\RequestInfo $request
	 * @param callable|null                     $header
	 * @internal param array $server
	 * @internal param array $get
	 * @internal param array $post
	 * @internal param array $files
	 * @internal param array $cookie
	 * @internal param array $HEADERS
	 * @internal param string $INPUT
	 */
	function __construct( $tmpPath, RequestInfo $request, callable $header = null ) {
		if( is_null($header) ) {
			$header = "\\header";
		}

		$this->tmpPath = $tmpPath;

		$this->logRequest($request);

		$this->header  = $header;
		$this->request = $request;
	}

	private function logRequest( RequestInfo $request ) {
		$reqStr = json_encode($request);
		file_put_contents($this->tmpPath . DIRECTORY_SEPARATOR . MockWebServer::LAST_REQUEST_FILE, $reqStr);
		file_put_contents($this->tmpPath . DIRECTORY_SEPARATOR . 'request.' . microtime(true), $reqStr);
	}

	public static function aliasPath( $tmpPath, $path ) {
		$path = '/' . ltrim($path, '/');

		return $tmpPath . DIRECTORY_SEPARATOR . 'alias.' . md5($path);
	}

	public function __invoke() {
		$path = $this->getDataPath();

		if( $path !== false ) {
			if( is_readable($path) ) {
				$content  = file_get_contents($path);
				$response = unserialize($content);
				if( !$response instanceof ResponseInterface ) {
					throw new ServerException('invalid serialized response');
				}

				http_response_code($response->getStatus());

				foreach( $response->getHeaders() as $key => $header ) {
					if( is_int($key) ) {
						($this->header)($header);
					} else {
						($this->header)("{$key}: {$header}");
					}
				}

				if( $response->getBody() ) {
					echo $response->getBody();

					return;
				}
			} else {
				http_response_code(404);
				echo MockWebServer::VND . ": Resource '{$path}' not found!\n";

				return;
			}
		} else {
			header('Content-Type: application/json');
		}

		echo json_encode($this->request, JSON_PRETTY_PRINT);
	}

	/**
	 * @return false|string
	 */
	protected function getDataPath() {
		$path = false;

		$uriPath   = $this->request->getParsedUri()['path'];
		$aliasPath = self::aliasPath($this->tmpPath, $uriPath);
		if( file_exists($aliasPath) ) {
			if( $path = file_get_contents($aliasPath) ) {
				$path = $this->tmpPath . DIRECTORY_SEPARATOR . $path;
			}
		} elseif( preg_match('%^/' . preg_quote(MockWebServer::VND) . '/([0-9a-fA-F]{32})$%', $uriPath, $matches) ) {
			$path = $this->tmpPath . DIRECTORY_SEPARATOR . $matches[1];
		}

		return $path;
	}

}