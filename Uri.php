<?php

namespace HttpClient;

require_once 'Exception.php';

/**
 * Parse urls: [scheme://][username[:password]@]host[:port]path
 */
class Uri {
	const SCHEME_HTTP	= 'http';
	const SCHEME_HTTPS	= 'https';

	protected $scheme	= self::SCHEME_HTTP;

	protected $username	= null;
	protected $password	= null;

	protected $host;
	protected $port		= 80;
	protected $path		= '/';
	protected $query	= '';
	protected $fragment	= '';

	/**
	 * @param string $url
	 */
	public function __construct($url) {
		$parsedUrl = parse_url($url);

		isset($parsedUrl['scheme'])		&& $this->setScheme($parsedUrl['scheme']);
		isset($parsedUrl['user'])		&& $this->setUsername($parsedUrl['user']);
		isset($parsedUrl['password'])	&& $this->setPassword($parsedUrl['password']);
		isset($parsedUrl['host'])		&& $this->setHost($parsedUrl['host']);
		isset($parsedUrl['port'])		&& $this->setPort($parsedUrl['port']);
		isset($parsedUrl['path'])		&& $this->setPath($parsedUrl['path']);
		isset($parsedUrl['query'])		&& $this->setQuery($parsedUrl['query']);
		isset($parsedUrl['fragment'])	&& $this->setFragment($parsedUrl['fragment']);
	}

	/**
	 * @return string
	 */
	public function getScheme() {
		return $this->scheme;
	}

	/**
	 * @param string $scheme
	 * @return Uri
	 * @throws Exception
	 */
	public function setScheme($scheme) {
		switch ($scheme) {
			case self::SCHEME_HTTP:
				// do nothing
				break;
			case self::SCHEME_HTTPS:
				if ($this->getPort() === 80) {
					$this->setPort(443);
				}
				break;
			default:
				throw new Exception('Unsupported uri scheme');
		}
		$this->scheme = $scheme;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * @param string|null $username
	 * @return Uri
	 */
	public function setUsername($username) {
		$this->username = $username === null? null: (string) $username;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getPassword() {
		return $this->password;
	}

	/**
	 * @param string $password
	 * @return Uri
	 */
	public function setPassword($password) {
		$this->password = $password === null? null: (string) $password;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHost() {
		return $this->host;
	}

	/**
	 * @param string $host
	 * @return Uri
	 */
	public function setHost($host) {
		$this->host = (string) $host;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPort() {
		return $this->port;
	}

	/**
	 * @param int $port
	 * @return Uri
	 */
	public function setPort($port) {
		$this->port = (int) $port;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @param string $path
	 * @return Uri
	 */
	public function setPath($path) {
		$this->path = '/' . ltrim($path, '/');
		return $this;
	}

	/**
	 * @return string
	 */
	public function getQuery() {
		return $this->query;
	}

	/**
	 * @param string $query
	 * @return Uri
	 */
	public function setQuery($query) {
		$this->query = (string) $query;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFragment() {
		return $this->fragment;
	}

	/**
	 * @param string $fragment
	 * @return Uri
	 */
	public function setFragment($fragment) {
		$this->fragment = ltrim($fragment, '#');
		return $this;
	}

	/**
	 * @return string
	 */
	function __toString() {
		$auth = $this->getUsername();
		if ($password = $this->getPassword()) {
			$auth .= ":{$password}";
		}
		if (!empty($auth)) {
			$auth .= '@';
		}
		$uri = "{$this->getScheme()}://{$auth}{$this->getHost()}:{$this->getPort()}{$this->getPath()}";
		if ($query = $this->getQuery()) {
			$uri .= '?' . $query;
		}
		if ($fragment = $this->getFragment()) {
			$uri .= '#' . $fragment;
		}
		return $uri;
	}
}
