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
	const CONTROLLER_PARAM = 'page';    ///< string  the request param that contains the page
	const ACTION_PARAM     = 'func';    ///< string  the request param that contains the action

	protected $app;
	protected $dispatcher;

	public function __construct(array $routes = array(), sly_App_Backend $app, sly_Dispatcher_Backend $dispatcher) {
		parent::__construct($routes);

		$this->app        = $app;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Get the currently active page
	 *
	 * The page determines the controller that will be used for dispatching. It
	 * will be put into $_REQUEST (so that third party code can access the
	 * correct value).
	 *
	 * When setup is true, requests to the setup controller will be redirected to
	 * the profile page (always accessible). Otherwise, this method will also
	 * check whether the current user has access to the found controller. If a
	 * forbidden controller is requested, the profile page is used.
	 *
	 * @return string  the currently active page
	 */
	public function match(sly_Request $request) {
		if (parent::match($request)) return true;

		if (sly_Core::isSetup()) {
			$request->get->set(self::CONTROLLER_PARAM, 'setup');
			return true;
		}

		if ($request->get->has(self::CONTROLLER_PARAM)) {
			return true;
		}

		$container    = $this->app->getContainer();
		$dispatcher   = $this->dispatcher;
		$config       = $container->getConfig();
		$user         = $container->getUserService()->getCurrentUser();
		$alternatives = array_filter(array(
			$user ? $user->getStartpage() : null,
			strtolower($config->get('START_PAGE')),
			'profile'
		));

		//
		if (!$user) {
			$request->get->set(self::CONTROLLER_PARAM, 'login');
			return true;
		}

		foreach ($alternatives as $alt) {
			try {
				$controllerClass = $dispatcher->getControllerClass($alt);
				$dispatcher->checkController($controllerClass);

				// if we got here, cool, let's update the request
				$request->get->set(self::CONTROLLER_PARAM, $alt);
				return true;
			}
			catch (Exception $e) {
				// pass ...
			}
		}

		return false;
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

	public function getControllerFromRequest(sly_Request $request) {
		$val = $request->request(self::CONTROLLER_PARAM, 'string');
		return $val === null ? null : strtolower($val);
	}

	public function getActionFromRequest(sly_Request $request) {
		return strtolower($request->request(self::ACTION_PARAM, 'string', 'index'));
	}
}
