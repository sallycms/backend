<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @author christoph@webvariants.de
 * @since  0.8
 */
class sly_Router_Backend extends sly_Router_Base {
	protected $app;

	public function __construct(array $routes = array(), sly_App_Backend $app) {
		parent::__construct($routes);
		$this->app = $app;
	}

	public function getUrl($controller, $action = 'index', $params = '', $sep = '&') {
		$url    = sprintf('./');
		$action = strtolower($action);

		if ($controller === null) {
			$controller = $this->app->getCurrentControllerName();
		}

		$url .= urlencode(strtolower($controller));

		if ($action && $action !== 'index') {
			$url .= '/'.urlencode($controller);
		}

		if (is_string($params)) {
			if ($params[0] === '?') $params = substr($params, 1);
			if ($params[0] === '&') $params = substr($params, 1);

			if (strlen($page) !== 0) {
				$params = 'page='.urlencode($page).'&'.$params;
			}

			$params = rtrim($params, '&?');
		}
		else {
			if (strlen($page) !== 0) {
				$params['page'] = $page;
			}

			$params = http_build_query($params, '', '&');
		}

		return $url.'?'.$params;
	}
}
