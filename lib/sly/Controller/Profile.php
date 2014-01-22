<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Profile extends sly_Controller_Backend implements sly_Controller_Interface {
	private $init;

	protected function init() {
		if ($this->init++) return;
		$layout = sly_Core::getLayout();
		$layout->pageHeader(t('my_profile'));
	}

	public function indexAction() {
		$this->init();
		$this->render('profile/index.phtml', array('user' => $this->getUser()), false);
	}

	public function updateAction() {
		$this->init();

		$user    = $this->getUser();
		$request = $this->getRequest();

		$user->setName($request->post('username', 'string'));
		$user->setDescription($request->post('description', 'string'));
		$user->setUpdateColumns();

		// backend locale

		$backendLocale  = $request->post('locale', 'string');
		$backendLocales = $this->getBackendLocales();

		if (isset($backendLocales[$backendLocale]) || strlen($backendLocale) === 0) {
			$user->setBackendLocale($backendLocale);
		}

		// timezone

		$timezone = $request->post('timezone', 'string');
		$user->setTimezone($timezone ? $timezone : null);

		// homepage

		$startpage  = $request->post('startpage', 'string');
		$startpages = $this->getPossibleStartpages();

		if (isset($startpages[$startpage])) {
			$user->setStartPage($startpage);
		}

		// change password if one was given

		$password = $request->post('password', 'string');
		$service  = $this->getContainer()->getUserService();

		if (!empty($password)) {
			$user->setPassword($password);
		}

		// save, done

		$service->save($user);

		sly_Core::getFlashMessage()->appendInfo(t('profile_updated'));

		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		$user = $this->getUser();
		if (!$user) return false;

		if ($action === 'update') {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}

	protected function getBackendLocales() {
		$langpath = SLY_SALLYFOLDER.'/backend/lang';
		$langs    = sly_I18N::getLocales($langpath);
		$result   = array('' => t('use_default_locale'));

		foreach ($langs as $locale) {
			$i18n = new sly_I18N($locale, $langpath, false);
			$result[$locale] = $i18n->msg('lang');
		}

		return $result;
	}

	protected function getPossibleStartpages() {
		$nav      = new sly_Layout_Navigation_Backend();
		$user     = $this->getUser();
		$starters = array('profile' => t('profile'));

		$nav->init($user);

		foreach ($nav->getGroups() as $group) {
			foreach ($group->getPages() as $page) {
				if ($page->isPopup()) continue;

				$pageParam = $page->getPageParam();
				$name      = $page->getTitle();

				$starters[$pageParam] = $name;
			}
		}

		return $starters;
	}

	protected function getUser() {
		return sly_Util_User::getCurrentUser();
	}
}
