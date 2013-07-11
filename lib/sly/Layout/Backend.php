<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use sly\Assets\Util;

/**
 * @ingroup layout
 */
class sly_Layout_Backend extends sly_Layout_XHTML5 implements sly_ContainerAwareInterface {
	private $hasNavigation = true;
	private $navigation;
	private $topMenu;
	private $container;
	private $router;

	public function __construct(sly_I18N $i18n, sly_Configuration $config, sly_Request $request) {
		$locale  = $i18n->getLocale();
		$favicon = $config->get('backend/favicon');
		$project = $config->get('projectname');

		$this->addCSSFile(Util::appUri('css/import.less'));

		$this->addJavaScriptFile(Util::appUri('js/modernizr.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/iso8601.min.js'), 'if lt IE 8');
		$this->addJavaScriptFile(Util::appUri('js/jquery.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/json2.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/jquery.chosen.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/jquery.tools.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/jquery.datetime.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/locales/'.$locale.'.min.js'));
		$this->addJavaScriptFile(Util::appUri('js/standard.min.js'));

		if ($project) {
			$this->setTitle($project.' - ');
		}

		$this->addMeta('robots', 'noindex,nofollow');
		$this->setBase($request->getAppBaseUrl().'/');

		if ($favicon) {
			$this->setFavIcon(Util::appUri($favicon));
		}

		$locale = explode('_', $locale, 2);
		$locale = reset($locale);

		if (strlen($locale) === 2) {
			$this->setLanguage(strtolower($locale));
		}
	}

	public function setContainer(sly_Container $container = null) {
		$this->container = $container;
	}

	public function setCurrentPage($page, sly_Model_User $user = null) {
		$bodyID = str_replace('_', '-', $page);
		$this->setBodyAttr('id', 'sly-page-'.$bodyID);

		// put some helpers on the body tag (now that we definitly know whether someone is logged in)
		if ($user) {
			$this->setBodyAttr('class', implode(' ', array(
				'sly-'.sly_Core::getVersion('X'),
				'sly-'.sly_Core::getVersion('X_Y'),
				'sly-'.sly_Core::getVersion('X_Y_Z')
			)));

			$token = sly_Util_Csrf::getToken();

			if (!empty($token)) {
				$this->addMeta(sly_Util_Csrf::TOKEN_NAME, $token);
			}
		}
	}

	public function setRouter(sly_Router_Backend $router) {
		$this->router = $router;
	}

	public function setTopMenuHelper(sly_Helper_TopMenu $helper) {
		$this->topMenu = $helper;
	}

	public function setNavigation(sly_Layout_Navigation_Backend $nav) {
		$this->navigation = $nav;
	}

	public function printHeader() {
		parent::printHeader();

		$user     = $this->container->getUserService()->getCurrentUser();
		$username = $user ? ($user->getName() != '' ? $user->getName() : $user->getLogin()) : null;

		if ($this->hasNavigation) {
			$nav        = $this->getNavigation();
			$dispatcher = $this->container->getDispatcher();

			if ($user) {
				$nav->init($user, $dispatcher);
			}
		}
		else {
			$nav = null;
		}

		print $this->renderView('top.phtml', array('navigation' => $nav, 'username' => $username));
	}

	public function printFooter() {
		$user        = $this->container->getUserService()->getCurrentUser();
		$showCredits = $user && ($user->isAdmin() || $user->hasRight('apps', 'backend'));
		$memory      = sly_Util_String::formatFilesize(memory_get_peak_usage());
		$runtime     = null;

		if ($this->container->has('sly-start-time')) {
			$runtime = microtime(true) - $this->container->get('sly-start-time');
		}

		print $this->renderView('bottom.phtml', compact('user', 'memory', 'runtime', 'showCredits'));
		parent::printFooter();
	}

	public function pageHeader($title, $topMenu = null) {
		$dispatcher = $this->container->getDispatcher();

		if ($topMenu === null || $topMenu instanceof sly_Layout_Navigation_Page) {
			$navigation = $this->getNavigation();

			if ($topMenu === null) {
				$user = $this->container->getUserService()->getCurrentUser();

				if ($user) {
					$navigation->init($user, $dispatcher);
				}

				$topMenu = $navigation->getActivePage();
			}
		}
		elseif (!is_string($topMenu)) {
			throw new InvalidArgumentException('$topMenu must either be a navigation page, a string or null, got '.gettype($topMenu).'.');
		}

		$this->appendToTitle($title);

		$menu  = $topMenu; // assuming $topMenu is a pre-rendered string
		$app   = $this->container->getApplication();
		$title = $dispatcher->filter('SLY_BACKEND_PAGE_HEADER', $title, array(
			'menu'       => $topMenu,
			'layout'     => $this,
			'controller' => $app->getCurrentControllerName()
		));

		if ($topMenu instanceof sly_Layout_Navigation_Page) {
			$router = $app->getRouter();
			$menu   = $this->topMenu->render($topMenu, $router);
		}

		if ($menu) {
			$menu = '<div class="pagehead-row">'.$menu.'</div>';
		}

		print '<div id="sly-pagehead"><div class="pagehead-row"><h1>'.$title.'</h1></div>'.$menu.'</div>';
	}

	/**
	 * override default hasNavigation value
	 *
	 * @param boolean $flag  true to show navigation falso to hide
	 */
	public function showNavigation($flag = true) {
		$this->hasNavigation = !!$flag;
	}

	public function hasNavigation() {
		return $this->hasNavigation;
	}

	/**
	 * @return sly_Layout_Navigation_Backend
	 */
	public function getNavigation() {
		return $this->navigation;
	}

	protected function getViewFile($file) {
		$full = SLY_SALLYFOLDER.'/backend/views/layout/'.$file;
		if (file_exists($full)) return $full;

		return parent::getViewFile($file);
	}

	/**
	 * @param  string $filename
	 * @param  array  $params
	 * @return string
	 */
	protected function renderView($filename, $params = array()) {
		// make router available to all controller views
		$params = array_merge(array('_router' => $this->router), $params);

		return parent::renderView($filename, $params);
	}
}
