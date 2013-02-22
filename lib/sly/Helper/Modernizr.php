<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Helper_Modernizr {
	protected $request;
	protected $name;

	const COOKIE_NAME = 'sly_modernizr';

	/**
	 * constructor
	 *
	 * @param sly_Request $request
	 * @param string      $cookieName
	 */
	public function __construct(sly_Request $request, $cookieName = null) {
		$this->request = $request;
		$this->name    = $cookieName === null ? self::COOKIE_NAME : (string) $cookieName;
	}

	/**
	 * check if the client has a given capability
	 *
	 * @param  string  $test  test name like 'filereader'
	 * @return boolean
	 */
	public function hasCapability($test) {
		$info = $this->getCapabilities();
		return isset($info[$test]) && ((boolean) $info[$test]) === true;
	}

	/**
	 * get client capabilities
	 *
	 * @return mixed  array if cookie was set, else false
	 */
	public function getCapabilities() {
		$value = $this->request->cookie($this->name, 'string', 'false');

		return @json_decode($value, true);
	}

	/**
	 * check if an input type is known
	 *
	 * @param  string  $type
	 * @return boolean
	 */
	public function hasInputtype($type) {
		$data = $this->getCapabilities();
		return !empty($data['inputtypes'][$type]);
	}

	/**
	 * check if an input is known
	 *
	 * @param  string  $type
	 * @return boolean
	 */
	public function hasInput($type) {
		$data = $this->getCapabilities();
		return !empty($data['input'][$type]);
	}
}
