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
		$requestUri = $this->getRequest()->getRequestUri();
		$this->render('login/index.phtml', compact('requestUri'), false);
	}

	public function loginAction() {
		$request  = $this->getRequest();
		$username = $request->post('username', 'string');
		$password = $request->post('password', 'string');
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
			// notify system
			sly_Core::dispatcher()->notify('SLY_BE_LOGIN', $user);

			// if relogin, forward to previous page
			$referer = $request->post('referer', 'string', false);

			if ($referer && !sly_Util_String::startsWith(basename($referer), 'index.php?page=login')) {
				$url = $referer;
				$msg = t('redirect_previous_page', $referer);
			}
			else {
				$base = sly_Util_HTTP::getBaseUrl(true);
				$url  = $base.'/backend/index.php?page='.$user->getStartPage();
				$msg  = t('redirect_startpage', $url);
			}

			sly_Util_HTTP::redirect($url, array(), $msg, 302);
		}
	}

	public function logoutAction() {
		$user = sly_Util_User::getCurrentUser();

		if ($user) {
			// check access here to avoid layout problems
			sly_Util_Csrf::checkToken();

			// notify system
			sly_Core::dispatcher()->notify('SLY_BE_LOGOUT', $user);
			sly_Service_Factory::getUserService()->logout();
			sly_Core::getFlashMessage()->appendInfo(t('you_have_been_logged_out'));
		}

		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		return true;
	}
}
