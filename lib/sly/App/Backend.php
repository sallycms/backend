<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_App_Backend extends sly_App_Base {
	protected $request    = null;
	protected $router     = null;
	protected $dispatcher = null;
	protected $isBackend;

	public function __construct(sly_Container $container = null) {
		parent::__construct($container);

		$this->setIsBackend(true);
	}

	public function isBackend() {
		return $this->isBackend;
	}

	public function setIsBackend($isBackend) {
		$this->isBackend = (bool) $isBackend;
	}

	/**
	 * Initialize Sally system
	 *
	 * This method will set-up the language, configuration, layout etc. After
	 * that, the addOns will be loaded, so that the application can be run
	 * via run().
	 */
	public function initialize() {
		$container = $this->getContainer();

		$this->setDefaultTimezone();

		// init basic error handling
		$container->getErrorHandler()->init();

		// init request
		$this->request = $container->getRequest();

		// Setup?
		if (sly_Core::isSetup()) {
			$target = $this->request->getBaseUrl(true).'/setup/';
			$text   = 'Bitte führe das <a href="'.sly_html($target).'">Setup</a> aus, um SallyCMS zu nutzen.';

			sly_Util_HTTP::tempRedirect($target, array(), $text);
		}

		// load static config
		$this->loadStaticConfig($container);

		// init the current language
		$this->initLanguage($container, $this->request);

		// init the core (addOns, listeners, ...)
		parent::initialize();

		$user = $container->getUserService()->getCurrentUser(true);

		// if it is develop mode the parent has already synced
		if (!sly_Core::isDeveloperMode() && $user && $user->isAdmin()) {
			$this->syncDevelopFiles();
		}
	}

	/**
	 * Run the backend app
	 *
	 * This will perform the routing, check the controller, load and execute it
	 * and send the response including the layout to the client.
	 */
	public function run() {
		try {
			// resolve URL and find controller
			$this->performRouting($this->request);
			$this->forceLoginController();

			// notify the addOns
			$this->notifySystemOfController();
		}
		catch (sly_Controller_Exception $e) {
			$this->controller = new sly_Controller_Error($e);
			$this->action     = 'index';
		}

		// set the appropriate page ID
		$this->updateLayout();

		// do it, baby
		$dispatcher = $this->getDispatcher();
		$response   = $dispatcher->dispatch($this->controller, $this->action);

		// send the response :)
		$response->send();
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

	public function getRouter() {
		return $this->router;
	}

	public function redirect($page, $params = array(), $code = 302) {
		$url = $this->router->getAbsoluteUrl($page, null, $params, '&');
		sly_Util_HTTP::redirect($url, '', '', $code);
	}

	public function redirectResponse($controller, $action, $params = array(), $code = 302) {
		$url      = $this->router->getAbsoluteUrl($controller, $action, $params, '&');
		$response = $this->getContainer()->getResponse();

		$response->setStatusCode($code);
		$response->setHeader('Location', $url);
		$response->setContent(t('redirect_to', $url));

		return $response;
	}

	protected function loadAddons() {
		$container = $this->getContainer();

		$container->getAddOnManagerService()->loadAddOns($container);

		// start session here
		sly_Util_Session::start();

		$user = $container->getUserService()->getCurrentUser(true);

		// init timezone and locale
		$this->initUserSettings($user);

		// make sure our layout is used later on
		$this->initLayout($container);

		$container->getDispatcher()->notify('SLY_ADDONS_LOADED', $container);
	}

	/**
	 * get request dispatcher
	 *
	 * @return sly_Dispatcher
	 */
	protected function getDispatcher() {
		if ($this->dispatcher === null) {
			$this->dispatcher = new sly_Dispatcher_Backend($this->getContainer());
		}

		return $this->dispatcher;
	}

	protected function initLanguage(sly_Container $container, sly_Request $request) {
		// init the current language
		$clangID = $request->request('clang', 'int', 0);

		if ($clangID <= 0 || !sly_Util_Language::exists($clangID)) {
			$clangID = sly_Core::getDefaultClangId();
		}

		// the following article API calls require to know a language
		$container->setCurrentLanguageId($clangID);
	}

	protected function initUserSettings($user) {
		$container = $this->getContainer();

		$locale = sly_Core::getDefaultLocale();

		// get user values
		if ($user instanceof sly_Model_User) {
			$locale   = $user->getBackendLocale() ? $user->getBackendLocale() : $locale;
			$timezone = $user->getTimeZone();

			// set user's timezone
			if ($timezone) $this->setTimezone($timezone);
		}

		// set the i18n object
		$this->initI18N($container, $locale);
	}

	protected function loadStaticConfig(sly_Container $container) {
		$container->getConfig()->setStatic('/', sly_Util_YAML::load(SLY_SALLYFOLDER.'/backend/config/static.yml'));
	}

	protected function initLayout(sly_Container $container) {
		$i18n    = $container->getI18N();
		$config  = $container->getConfig();
		$request = $container->getRequest();
		$router  = $this->getRouter();
		$layout  = new sly_Layout_Backend($i18n, $config, $request);

		$layout->setTopMenuHelper(new sly_Helper_TopMenu($router));
		$layout->setNavigation(new sly_Layout_Navigation_Backend());
		$layout->setContainer($container);

		$container->setLayout($layout);
	}

	protected function initI18N(sly_Container $container, $locale) {
		if ($container->has('sly-i18n')) {
			$i18n = $container->getI18N();
		}
		else {
			$i18n = new sly_I18N($locale, null, false);
			$container->setI18N($i18n);
		}

		if (strtolower($locale) !== strtolower($i18n->getLocale())) {
			$i18n->setLocale($locale);
		}

		$i18n->appendFile(SLY_SALLYFOLDER.'/backend/lang');
		$i18n->setPHPLocale();
	}

	protected function getControllerFromRequest(sly_Request $request) {
		return $this->router->getControllerFromRequest($request);
	}

	protected function getActionFromRequest(sly_Request $request) {
		return $this->router->getActionFromRequest($request);
	}

	protected function updateLayout() {
		// let the layout know where we are
		$container = $this->getContainer();
		$layout    = $container->getLayout();
		$user      = $container->getUserService()->getCurrentUser();
		$page      = $this->controller instanceof sly_Controller_Error ? 'error' : $this->controller;

		$layout->setCurrentPage($page, $user);
		$layout->setRouter($this->getRouter());
	}

	protected function prepareRouter(sly_Container $container) {
		// use the basic router
		$router = new sly_Router_Backend(array(), $this, $this->getDispatcher());

		// let addOns extend our router rule set
		return ($this->router = $container->getDispatcher()->filter('SLY_BACKEND_ROUTER', $router, array('app' => $this)));
	}

	protected function forceLoginController() {
		$container = $this->container;
		$request   = $this->request;
		$response  = $container->getResponse();
		$user      = $container->getUserService()->getCurrentUser();

		// force login controller if no login is found
		if ($user === null || (!$user->isAdmin() && !$user->hasRight('apps', 'backend'))) {
			// send a 403 header to prevent robots from including the login page
			// and to help ajax requests that were fired a long time after the last
			// interaction with the backend to easily detect the expired session
			$controller = $this->getControllerFromRequest($request);

			if ($controller !== 'login' && $controller !== null) {
				$response->setStatusCode(403);
			}

			$this->controller = 'login';
		}
	}
}
