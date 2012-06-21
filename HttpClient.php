<?php

namespace HttpClient;

require_once 'Response.php';
require_once 'Exception.php';
require_once 'ClientException.php';
require_once 'Uri.php';

class Client {
	const REQUEST_METHOD_GET	= 'GET';
	const REQUEST_METHOD_POST	= 'POST';

	/**
	 * POST data encoding methods
	 */
	const ENC_URLENCODED = 'application/x-www-form-urlencoded';
	const ENC_FORMDATA   = 'multipart/form-data';

	/**
	 * @var Uri|null
	 */
	protected $uri;

	/**
	 * @var resource|null
	 */
	protected $context;

	/**
	 * @var resource|null
	 */
	protected $socket;

	/**
	 * @var array
	 */
	protected $auth				= array('username' => null, 'password' => null);

	protected $parametersGet	= array();
	protected $parametersPost	= array();

	protected $headers			= array();

	/**
	 * Load response error string.
	 * Is null if there was no error while response loading
	 *
	 * @var string|null
	 */
	protected $loadResponseErrorStr;

	/**
	 * Load response error code.
	 * Is null if there was no error while response loading
	 *
	 * @var int|null
	 */
	protected $loadResponseErrorCode;

	/**
	 * @var int[]|string[]
	 */
	protected $config = array(
		'fetchBody'		=> true,
		'fetchHeaders'	=> true,
		'timeout'		=> 10
	);

	/**
	 * @param \HttpClient\Uri|string|null $uri
	 */
	public function __construct($uri = null) {
		if ($uri !== null) {
			$this->setUri($uri);
		}
	}

	/**
	 * @param string $method
	 * @return \HttpClient\Response
	 */
	public function request($method = self::REQUEST_METHOD_GET) {
		// Warnings and errors are suppressed
		set_error_handler(array($this, 'loadFileErrorHandler'));

		$this->setConfig(array('method' => $method));

		$this->createContext();
		$this->connect();

		$response = new Response($this->fetchHeaders(), $this->fetchBody());

		$this->disconnect();
		restore_error_handler();

		return $response;
	}

	/**
	 * @throws \HttpClient\ClientException
	 * @return array
	 */
	protected function fetchHeaders() {
		if ($this->getConfig('fetchHeaders')) {
			$metaData = stream_get_meta_data($this->socket);
			if ($this->loadResponseErrorStr !== null) {
				$this->disconnect();
				throw new ClientException("Can't fetch response headers from {$this->getUrl()} [{$this->loadResponseErrorStr}]");
			}
			$headers = $metaData['wrapper_data'];
		} else {
			$headers = array('HTTP/1.0 200 OK');
		}
		return $headers;
	}

	/**
	 * @throws \HttpClient\ClientException
	 * @return string
	 */
	protected function fetchBody() {
		if ($this->getConfig('fetchBody')) {
			$body = stream_get_contents($this->socket);
			if ($this->loadResponseErrorStr !== null) {
				$this->disconnect();
				throw new ClientException("Can't fetch response content from {$this->getUrl()} [{$this->loadResponseErrorStr}]", $this->loadResponseErrorCode);
			}
		} else {
			$body = '';
		}
		return $body;
	}


	/**
	 * @throws \HttpClient\ClientException
	 * @return null|resource
	 */
	protected function createContext() {
		$httpOptions = array(
			'method'		=> $this->getConfig('method'),
			'timeout'		=> $this->getConfig('timeout'),
			'max_redirects'	=> 0,
			'ignore_errors'	=> 1,
			'header'		=> $this->prepareHeaders()
		);

		// setup POST-paramteres
		if ($this->getConfig('method') == self::REQUEST_METHOD_POST) {
			$httpOptions['content'] = http_build_query($this->parametersPost, null, '&');
		}

		$this->context = stream_context_create(array(
			'http' => $httpOptions
		));
		if ($this->loadResponseErrorStr !== null) {
			throw new ClientException($this->loadResponseErrorStr, $this->loadResponseErrorCode);
		}
		return $this->context;
	}

	protected function prepareHeaders() {
		$headers = '';

		$this->setHeader('Host', $this->getUrl()->getHost());
		if (($username = $this->getAuth('username')) && ($password = $this->getAuth('password'))) {
			$this->setHeader('Authorization', "Basic " . base64_encode(urlencode($username) . ':' . urlencode($password)));
		}
		$this->setHeader('Content-Type', $this->getConfig('method') == self::REQUEST_METHOD_POST? self::ENC_URLENCODED: self::ENC_FORMDATA);

		foreach($this->headers as $name => $value) {
			$headers .= "{$name}: {$value}\n";
		}

		return $headers;
	}

	/**
	 * @param string $name
	 * @param string|null $value
	 * @return \HttpClient\Client
	 */
	protected function setHeader($name, $value) {
		if ($value === null && array_key_exists($name, $this->headers)) {
			unset($this->headers[$name]);
		} else {
			$this->headers[$name] = $value;
		}
		return $this;
	}

	/**
	 * @throws \HttpClient\ClientException
	 * @return resource
	 */
	protected function connect() {
		// setup GET-parameters to URI
		$uri = clone $this->getUrl();
		$query = $uri->getQuery();
		if (!empty($query)) {
			$query .= '&';
		}
		$query .= http_build_query($this->parametersGet);
		$uri->setQuery($query);

		// connect to http-server
		$this->socket = fopen((string) $uri, 'r', null, $this->context);

		if ($this->loadResponseErrorStr !== null) {
			$this->disconnect();
			throw new ClientException("Can't connect to {$this->getUrl()} [{$this->loadResponseErrorStr}]", $this->loadResponseErrorCode);
		}
		return $this->socket;
	}

	/**
	 * @return \HttpClient\Client
	 */
	protected function disconnect() {
		if (is_resource($this->socket)) {
			fclose($this->socket);
		}
		return $this;
	}

	/**
	 * Handle any errors from simplexml_load_file or parse_ini_file
	 *
	 * @param integer $errno
	 * @param string $errstr
	 * @param string $errfile
	 * @param integer $errline
	 */
	public function loadFileErrorHandler($errno, $errstr, $errfile, $errline) {
		$this->loadResponseErrorCode	= $errno;
		if ($this->loadResponseErrorStr === null) {
			$this->loadResponseErrorStr	= $errstr;
		} else {
			$this->loadResponseErrorStr .= (PHP_EOL . $errstr);
		}
	}

	/**
	 * @param string|null $username
	 * @param string|null $password
	 * @return \HttpClient\Client
	 */
	public function setAuth($username, $password) {
		$this->auth = array(
			'username' => $username === null? null: (string) $username,
			'password' => $password === null? null: (string) $password
		);
		return $this;
	}

	/**
	 * @param string|null $field
	 * @return array|string
	 */
	public function getAuth($field = null) {
		if ($field === null) {
			return $this->auth;
		} else if (array_key_exists($field, $this->auth)) {
			return $this->auth[$field];
		}
		return null;
	}

	/**
	 * @param array $config
	 * @return \HttpClient\Client
	 */
	public function setConfig(array $config) {
		$this->config = array_merge($this->config, $config);
		return $this;
	}

	/**
	 * @param null $parameter
	 * @return array|string|int|null
	 */
	public function getConfig($parameter = null) {
		if ($parameter === null) {
			return $this->config;
		} else if (array_key_exists($parameter, $this->config)) {
			return $this->config[$parameter];
		}
		return null;
	}

	/**
	 * @param \HttpClient\Uri|string $uri
	 * @return \HttpClient\Client;
	 */
	public function setUri($uri) {
		if (!$uri instanceof Uri) {
			$uri = new Uri($uri);
		}
		$this->uri = $uri;
		return $this;
	}

	/**
	 *
	 * @throws \HttpClient\ClientException
	 * @return \HttpClient\Uri|null
	 */
	public function getUrl() {
		if (!$this->uri instanceof Uri) {
			throw new ClientException('RequestUri is not setup');
		}
		return $this->uri;
	}

	/**
	 * @param bool $resetHeaders
	 * @return \HttpClient\Client
	 */
	public function resetParameters($resetHeaders = false) {
		$this->parametersGet	= array();
		$this->parametersPost	= array();
		if ($resetHeaders) {
			$this->headers = array();
		}
		return $this;
	}

	/**
	 * @param string[]|string $name
	 * @param string|null $value
	 * @return \HttpClient\Client
	 */
	public function setParameterGet($name, $value = null) {
		if (is_array($name)) {
			foreach($name as $k => $v) {
				$this->setParameterGet($k, $v);
			}
		} else {
			$this->parametersGet[$name] = $value;
		}
		return $this;
	}

	/**
	 * @param string[]|string $name
	 * @param string|null $value
	 * @return \HttpClient\Client
	 */
	public function setParameterPost($name, $value = null) {
		if (is_array($name)) {
			foreach($name as $k => $v) {
				$this->setParameterPost($k, $v);
			}
		} else {
			$this->parametersPost[$name] = $value;
		}
		return $this;
	}
}
