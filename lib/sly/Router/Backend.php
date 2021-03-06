<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
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
	const CONTROLLER_PARAM = 'page';    ///< string  the request param that contains the page
	const ACTION_PARAM     = 'func';    ///< string  the request param that contains the action

	protected $app;
	protected $dispatcher;

	public function __construct(array $routes = array(), sly_App_Backend $app, sly_Dispatcher_Backend $dispatcher) {
		parent::__construct($routes);

		$this->app        = $app;
		$this->dispatcher = $dispatcher;

		$this->appendRoute('/:page/?',       array('func' => 'index'));
		$this->appendRoute('/:page/:func/?', array());
	}

	/**
	 * Get the currently active page
	 *
	 * The page determines the controller that will be used for dispatching. It
	 * will be put into $_REQUEST (so that third party code can access the
	 * correct value).
	 *
	 * This method will also check whether the current user has access to the
	 * found controller. If a forbidden controller is requested, the profile page
	 * is used.
	 *
	 * @return string  the currently active page
	 */
	public function match(sly_Request $request) {
		$matched = parent::match($request);

		if ($matched) {
			return true;
		}

		if ($request->request(self::CONTROLLER_PARAM, 'string') !== null) {
			return true;
		}

		return $this->findBestHomepage($request);
	}

	protected function findBestHomepage(sly_Request $request) {
		$container    = $this->app->getContainer();
		$dispatcher   = $this->dispatcher;
		$config       = $container->getConfig();
		$user         = $container->getUserService()->getCurrentUser();
		$alternatives = array_filter(array(
			$user ? $user->getStartpage() : null,
			strtolower($config->get('backend/start_page')),
			'profile'
		));

		// do not try to fetch an alternative if we're going to use the login controller anyway
		if (!$user) return true;

		foreach ($alternatives as $alt) {
			try {
				$dispatcher->getController($alt);

				// if we got here, cool, let's update the request
				$request->get->set(self::CONTROLLER_PARAM, $alt);

				return true;
			}
			catch (Exception $e) {
				// pass ... (abstract class, non-existing class, ...)
			}
		}

		return false;
	}

	public function getUrl($controller, $action = 'index', $params = '', $sep = '&amp;') {
		$url    = './';
		$action = strtolower($action);

		if ($controller === null) {
			$controller = $this->app->getCurrentControllerName();
		}

		$url .= urlencode(strtolower($controller));

		if ($action && $action !== 'index') {
			$url .= '/'.urlencode($action);
		}

		if (is_string($params)) {
			$params = trim($params, '&?');
		}
		elseif ($params !== null) {
			$params = http_build_query($params, '', $sep);
		}
		else {
			$params = '';
		}

		return rtrim($url.'?'.$params, '&?');
	}

	public function getAbsoluteUrl($controller, $action = 'index', $params = '', $sep = '&amp;', $forceProtocol = null) {
		$base = $this->app->getBaseUrl($forceProtocol);
		$url  = $this->getUrl($controller, $action, $params, $sep);

		// $url always starts with './' and $base never as a trailing slash.
		return $base.substr($url, 1);
	}

	public function getPlainUrl($controller, $action = 'index', $params = '') {
		return $this->getUrl($controller, $action, $params, '&');
	}

	public function getControllerFromRequest(sly_Request $request) {
		$val = $request->request(self::CONTROLLER_PARAM, 'string');
		return $val === null ? null : strtolower($val);
	}

	public function getActionFromRequest(sly_Request $request) {
		return strtolower($request->request(self::ACTION_PARAM, 'string', 'index'));
	}
}
