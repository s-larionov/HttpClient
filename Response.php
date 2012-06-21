<?php

namespace http;

class Response {
	protected $statusCode	= 200;
	protected $statusText	= 'OK';
	protected $body			= '';
	protected $headers		= array();

	public function __construct(array $headers, $body) {
		list(, $statusCode, $statusText) = explode(' ', array_shift($headers), 3);
		$this->setStatus($statusCode, $statusText);

		$this->setHeaders($headers);
		$this->setBody($body);
	}

	/**
	 * @param int $code
	 * @param string $text
	 * @return Response
	 */
	protected function setStatus($code, $text) {
		$this->statusCode = (int) $code;
		$this->statusText = (string) $text;
		return $this;
	}

	/**
	 * @param array $headers
	 * @return Response
	 */
	protected function setHeaders(array $headers) {
		foreach($headers as $header) {
			list($name, $value) = explode(':', $header, 2);
			$this->headers[$name] = trim($value);
		}
		return $this;
	}

	/**
	 * @param string|null $name
	 * @return string[]|string|null
	 */
	public function getHeader($name = null) {
		if ($name === null) {
			return $this->headers;
		} else if (array_key_exists($name, $this->headers)) {
			return $this->headers[$name];
		}
		return null;
	}

	/**
	 * @return string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * @param string $body
	 * @return string
	 */
	protected function setBody($body) {
		$this->body = (string) $body;
	}

	/**
	 * @return int
	 */
	public function getStatus() {
		return $this->statusCode;
	}

	/**
	 * @return string
	 */
	public function getStatusText() {
		return $this->statusText;
	}
}
