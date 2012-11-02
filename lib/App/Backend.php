<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Backend extends sly_App_Base {
	const CONTROLLER_PARAM = 'page';    ///< string  the request param that contains the page
	const ACTION_PARAM     = 'func';    ///< string  the request param that contains the action

	protected $controller = null;
	protected $action     = null;
	protected $request    = null;

	public function isBackend() {
		return true;
	}

	public function initialize() {
		$container = $this->getContainer();

		// init request
		$this->request = $container->getRequest();

		// init the current language
		$clangID = $this->request->request('clang', 'int', 0);

		if ($clangID <= 0 || !sly_Util_Language::exists($clangID)) {
			$clangID = sly_Core::getDefaultClangId();
		}

		// the following article API calls require to know a language
		$container->setCurrentLanguageId($clangID);

		// only start session if not running unit tests
		if (!SLY_IS_TESTING) sly_Util_Session::start();

		// load static config
		$this->loadStaticConfig($container);

		// are we in setup mode?
		$isSetup = sly_Core::isSetup();

		// init timezone and locale
		$this->initUserSettings($isSetup);

		// make sure our layout is used later on
		$this->initLayout($container);

		// be the first to init the layout later on, after the possibly available
		// auth provider has been setup by external addOns / frontend code.
		$container->getDispatcher()->register('SLY_ADDONS_LOADED', array($this, 'initNavigation'));

		// instantiate asset service before addOns are loaded to make sure
		// the CSS processing is first in the line for CSS files
		$container->getAssetService();

		// and now init the rest (addOns, listeners, ...)
		parent::initialize();
	}

	public function run() {
		$container = $this->getContainer();
		$layout    = $container->getLayout();

		// force login controller if not logged in
		$this->checkController($container);

		// get page and action from the current request
		$page   = $this->controller === null ? $this->findPage() : $this->controller;
		$action = $this->getActionParam('index');

		// let the core know where we are
		$this->controller = $page;
		$this->action     = $action;

		// let the layout know as well
		$user = sly_Core::isSetup() ? null : $container->getUserService()->getCurrentUser();
		$layout->setCurrentPage($page, $user);

		// notify the addOns
		$this->notifySystemOfController(true);

		// do it, baby
		$content  = $this->dispatch($page, $action);
		$response = $container->getResponse(); // re-fetch the current global response

		// if we got a string, wrap it in the layout and then in the response object
		if (is_string($content)) {
			$layout->setContent($content);
			$payload = $layout->render();
			$this->handleStringResponse($response, $payload);
		}

		// if we got a response, use that one
		elseif ($content instanceof sly_Response) {
			$response = $content;
		}

		// everything else is a bug
		else {
			throw new LogicException('Controllers must return either content as a string or a Response, got '.gettype($content).'.');
		}

		// send the response :)
		$response->send();
	}

	protected function initUserSettings($isSetup) {
		// set timezone
		$this->setDefaultTimezone($isSetup);

		if (!SLY_IS_TESTING && $isSetup) {
			$locale        = sly_Core::getDefaultLocale();
			$locales       = sly_I18N::getLocales(SLY_SALLYFOLDER.'/backend/lang');
			$requestLocale = $this->request->request('lang', 'string', '');
			$user          = null;

			if (in_array($requestLocale, $locales)) {
				$locale = $requestLocale;
			}

			// force setup page
			$this->controller = 'setup';
		}
		else {
			$locale = sly_Core::getDefaultLocale();
			$user   = $container->getUserService()->getCurrentUser();

			// get user values
			if ($user instanceof sly_Model_User) {
				$locale   = $user->getBackendLocale() ? $user->getBackendLocale() : $locale;
				$timezone = $user->getTimeZone();

				// set user's timezone
				if ($timezone) date_default_timezone_set($timezone);
			}
		}

		// set the i18n object
		$this->initI18N($container, $locale);
	}

	/**
	 * Get the page param
	 *
	 * Reads the page param from the $_REQUEST array and returns it.
	 *
	 * @param  string $default  default value if param is not present
	 * @return string           the page param
	 */
	public function getControllerParam($default = '') {
		return strtolower($this->request->request(self::CONTROLLER_PARAM, 'string', $default));
	}

	/**
	 * Get the action param
	 *
	 * Reads the action param from the $_REQUEST array and returns it.
	 *
	 * @param  string $default  default value if param is not present
	 * @return string           the action param
	 */
	public function getActionParam($default = '') {
		return strtolower($this->request->request(self::ACTION_PARAM, 'string', $default));
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
	protected function findPage() {
		$container = $this->getContainer();
		$config    = $container->getConfig();
		$page      = $this->getControllerParam();

		// Erst normale Startseite, dann User-Startseite, dann System-Startseite und
		// zuletzt auf die Profilseite zurückfallen.

		if (strlen($page) === 0 || !$this->isControllerAvailable($page)) {
			$user = $container->getUserService()->getCurrentUser();
			$page = $user ? $user->getStartpage() : null;

			if ($page === null || !$this->isControllerAvailable($page)) {
				$page = strtolower($config->get('START_PAGE'));

				if (!$this->isControllerAvailable($page)) {
					$page = 'profile';
				}
			}
		}

		return $page;
	}

	public function getControllerClassPrefix() {
		return 'sly_Controller';
	}

	public function getCurrentControllerName() {
		return $this->controller;
	}

	public function getCurrentAction() {
		return $this->action;
	}

	public function redirect($page, $params = array(), $code = 302) {
		$url = $this->prepareRedirectUrl($page, $params);
		sly_Util_HTTP::redirect($url, '', '', $code);
	}

	public function redirectResponse($page, $params = array(), $code = 302) {
		$url      = $this->prepareRedirectUrl($page, $params);
		$response = $this->getContainer()->getResponse();

		$response->setStatusCode($code);
		$response->setHeader('Location', $url);
		$response->setContent(t('redirect_to', $url));

		return $response;
	}

	protected function prepareRedirectUrl($page, $params) {
		$cont = $this->getContainer();
		$app  = $cont->getApplicationName();
		$base = $cont->getRequest()->getBaseUrl(true).'/'.$app.'/index.php';

		if ($page === null) {
			$page = $this->getCurrentControllerName();
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

		return $base.'?'.$params;
	}

	protected function handleControllerError(Exception $e, $controller, $action) {
		// throw away all content (including notices and warnings)
		while (ob_get_level()) ob_end_clean();

		// manually create the error controller to pass the exception
		$controller = new sly_Controller_Error($e);

		// forward to the error page
		return new sly_Response_Forward($controller, 'index');
	}

	/**
	 * Event handler
	 */
	public function initNavigation(array $params) {
		$layout = $this->getContainer()->getLayout();
		$layout->getNavigation()->init();
	}

	protected function checkController(sly_Container $container) {
		$response = $container->getResponse();
		$isSetup  = sly_Core::isSetup();
		$user     = $isSetup ? null : $container->getUserService()->getCurrentUser();

		// force login controller if no login is found
		if (!$isSetup && ($user === null || (!$user->isAdmin() && !$user->hasRight('apps', 'backend')))) {
			// send a 403 header to prevent robots from including the login page
			// and to help ajax requests that were fired a long time after the last
			// interaction with the backend to easily detect the expired session

			if ($this->getControllerParam('login') !== 'login') {
				$response->setStatusCode(403);
			}

			$this->controller = 'login';
		}
	}

	protected function loadStaticConfig(sly_Container $container) {
		$container->getConfig()->loadStatic(SLY_SALLYFOLDER.'/backend/config/static.yml');
	}

	protected function initLayout(sly_Container $container) {
		$i18n    = $container->getI18N();
		$config  = $container->getConfig();
		$request = $container->getRequest();

		$container->setLayout(new sly_Layout_Backend($i18n, $config, $request));
	}

	protected function initI18N(sly_Container $container, $locale) {
		$i18n = new sly_I18N($locale, SLY_SALLYFOLDER.'/backend/lang');
		$container->setI18N($i18n);
	}
}
