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
class sly_Layout_Backend extends sly_Layout_XHTML5 {
	private $hasNavigation = true;
	private $navigation;
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

	public function printHeader() {
		parent::printHeader();
		print $this->renderView('top.phtml');
	}

	public function printFooter() {
		$container   = sly_Core::getContainer();
		$user        = sly_Util_User::getCurrentUser();
		$showCredits = $user && ($user->isAdmin() || $user->hasRight('apps', 'backend'));
		$memory      = sly_Util_String::formatFilesize(memory_get_peak_usage());
		$runtime     = null;

		if ($container->has('sly-start-time')) {
			$runtime = microtime(true) - $container->get('sly-start-time');
		}

		print $this->renderView('bottom.phtml', compact('user', 'memory', 'runtime', 'showCredits'));
		parent::printFooter();
	}

	public function pageHeader($head, $subtitle = null) {
		if ($subtitle === null) {
			$subtitle = $this->getNavigation()->getActivePage();
		}

		if (!empty($subtitle)) {
			$subtitle = $this->getSubtitle($subtitle);

			if ($subtitle) {
				$subtitle = '<div class="pagehead-row">'.$this->getSubtitle($subtitle).'</div>';
			}
		}
		else {
			$subtitle = '';
		}

		$this->appendToTitle($head);
		$dispatcher = sly_Core::dispatcher();

		$head = $dispatcher->filter('PAGE_TITLE', $head, compact('page'));
		print '<div id="sly-pagehead"><div class="pagehead-row"><h1>'.$head.'</h1></div>'.$subtitle.'</div>';

		$dispatcher->notify('PAGE_TITLE_SHOWN', $subtitle, compact('page'));
	}

	/**
	 * Helper function, die den Subtitle generiert
	 */
	public function getSubtitle($subline) {
		if (!is_array($subline) && !($subline instanceof sly_Layout_Navigation_Page)) {
			return $subline;
		}

		if (empty($subline)) {
			return '';
		}

		$subPages   = is_array($subline) ? array_values($subline) : $subline->getSubpages();
		$result     = array();
		$curPage    = sly_Core::getCurrentControllerName();
		$numPages   = count($subPages);
		$format     = '<a href="%s"%s>%s</a>';
		$activePage = false;
		$nav        = $this->getNavigation();

		foreach ($subPages as $idx => $sp) {
			if (!is_array($sp) && !($sp instanceof sly_Layout_Navigation_Subpage)) continue;

			// the numeric version is just for compatibility reasons

			if (is_array($sp)) {
				if ($activePage === false) {
					$activePage = $nav->getActivePage();
					if ($activePage === null) $activePage = $nav->find($curPage);
					// It is still possible for $activePage to be null (for pages not in the
					// navigation, like the credits).
				}

				$page      = isset($sp['page'])   ? $sp['page']   : (isset($sp[0]) ? $sp[0] : $curPage);
				$label     = isset($sp['label'])  ? $sp['label']  : (isset($sp[1]) ? $sp[1] : '?');
				$forced    = isset($sp['forced']) ? $sp['forced'] : null;     // new in 0.6
				$extra     = isset($sp['extra'])  ? $sp['extra']  : array();  // dito
				$className = isset($sp['class'])  ? $sp['class']  : (isset($sp[4]) ? $sp[4] : '');

				$sp = $activePage === null ? $page : new sly_Layout_Navigation_Subpage($activePage, $page, $label, $page);

				if ($activePage) {
					$sp->forceStatus($forced);
					$sp->setExtraParams($extra);
				}
			}
			else {
				$page      = $sp->getPageParam();
				$label     = $sp->getTitle();
				$extra     = $sp->getExtraParams();
				$className = '';
			}

			$params    = !empty($extra) ? sly_Util_HTTP::queryString($extra, '&amp;', false) : '';
			$active    = is_string($sp) ? $curPage === $sp : $sp->isActive();
			$linkClass = array();
			$liClass   = array();

			if ($className) {
				$liClass[] = $className;
			}

			if ($idx === 0) {
				$liClass[] = 'sly-first';
			}

			if ($idx === $numPages-1) {
				$liClass[] = 'sly-last';
			}

			if ($active) {
				$linkClass[] = 'sly-active';
				$liClass[]   = 'sly-active';
			}

			$linkAttr  = ' rel="page-'.urlencode($page).'"';
			$linkAttr .= empty($linkClass) ? '' : ' class="'.implode(' ', $linkClass).'"';
			$liAttr    = empty($liClass)   ? '' : ' class="'.implode(' ', $liClass).'"';
			$link      = sprintf($format, $this->router->getUrl($page, null, $params), $linkAttr, $label);

			$result[] = '<li'.$liAttr.'>'.$link.'</li>';
		}

		if (empty($result)) return '';

		return '<div id="sly-navi-page"><ul>'.implode("\n", $result).'</ul></div>';
	}

	/**
	 * override default hasNavigation value
	 *
	 * @param boolean $active true to show navigation falso to hide
	 */
	public function showNavigation($active = true) {
		$this->hasNavigation = $active;
	}

	public function hasNavigation() {
		return $this->hasNavigation;
	}

	/**
	 * @return sly_Layout_Navigation_Backend
	 */
	public function getNavigation() {
		if (!isset($this->navigation)) {
			$this->navigation = new sly_Layout_Navigation_Backend();
		}

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
