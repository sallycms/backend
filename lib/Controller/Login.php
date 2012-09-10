<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Login extends sly_Controller_Backend implements sly_Controller_Generic {
	public function genericAction($action) {
		$layout = sly_Core::getLayout();
		$layout->showNavigation(false);
		$layout->pageHeader(t('login_title'));

		if (in_array(strtolower($action), array('index', 'login', 'logout'))) {
			$method = $action.'Action';
		}
		else {
			$method = 'indexAction';
		}

		try {
			return $this->$method();
		}
		catch (Exception $e) {
			print sly_Helper_Message::warn($e->getMessage());
		}
	}

	public function indexAction() {
		$this->render('login/index.phtml', array(), false);
	}

	public function loginAction() {
		$username = sly_post('username', 'string');
		$password = sly_post('password', 'string');
		$loginOK  = sly_Service_Factory::getUserService()->login($username, $password);

		// login was only successful if the user is either admin or has apps/backend permission
		if ($loginOK === true) {
			$user    = sly_Util_User::getCurrentUser();
			$loginOK = $user->isAdmin() || $user->hasRight('apps', 'backend');
		}

		if ($loginOK !== true) {
			$msg = t('login_error', '<strong>'.sly_Core::config()->get('RELOGINDELAY').'</strong>');
			sly_Core::getFlashMessage()->appendWarning($msg);
			$this->indexAction();
		}
		else {
			// if relogin, forward to previous page
			$referer = sly_post('referer', 'string', false);

			if ($referer && !sly_Util_String::startsWith(basename($referer), 'index.php?page=login')) {
				$url = $referer;
				$msg = t('redirect_previous_page', $referer);
			}
			else {
				$user = sly_Util_User::getCurrentUser();
				$base = sly_Util_HTTP::getBaseUrl(true);
				$url  = $base.'/backend/index.php?page='.$user->getStartPage();
				$msg  = t('redirect_startpage', $url);
			}

			sly_Util_HTTP::redirect($url, array(), $msg, 302);
		}
	}

	public function logoutAction() {
		sly_Service_Factory::getUserService()->logout();
		sly_Core::getFlashMessage()->appendInfo(t('you_have_been_logged_out'));
		$this->indexAction();
	}

	public function checkPermission($action) {
		return true;
	}
}
