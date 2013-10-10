<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Login extends sly_Controller_Backend implements sly_Controller_Generic {
	public function genericAction($action) {
		$layout = $this->getContainer()->getLayout();
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
		$container = $this->getContainer();
		$request   = $this->getRequest();
		$uService  = $container->getUserService();
		$username  = $request->post('username', 'string');
		$password  = $request->post('password', 'string');
		$loginOK   = $uService->login($username, $password);

		// login was only successful if the user is either admin or has apps/backend permission
		if ($loginOK === true) {
			$user    = $uService->getCurrentUser();
			$loginOK = $user->isAdmin() || $user->hasRight('apps', 'backend');
		}

		if ($loginOK !== true) {
			$msg = t('login_error', '<strong>'.sly_Core::config()->get('relogindelay').'</strong>');

			$container->getFlashMessage()->appendWarning($msg);
			$container->getResponse()->setStatusCode(403);

			$this->indexAction();
		}
		else {
			// notify system
			$container->getDispatcher()->notify('SLY_BE_LOGIN', $user);

			// if relogin, forward to previous page
			$referer = $request->post('referer', 'string', false);
			$refbase = basename($referer);
			$valid   =
				$referer &&
				!sly_Util_String::startsWith($refbase, 'index.php?page=login') &&
				strpos($referer, '/login') === false &&
				strpos($referer, '/setup') === false
			;

			if ($valid) {
				$url = $referer;
				$msg = t('redirect_previous_page', $referer);
			}
			else {
				$router = $container->getApplication()->getRouter();
				$url    = $router->getAbsoluteUrl($user->getStartPage());
				$msg    = t('redirect_startpage', $url);
			}

			while (ob_get_level()) ob_end_clean();
			sly_Util_HTTP::redirect($url, array(), $msg, 302);
		}
	}

	public function logoutAction() {
		$container = $this->getContainer();
		$uService  = $container->getUserService();
		$user      = $uService->getCurrentUser();

		if ($user) {
			// check access here to avoid layout problems
			sly_Util_Csrf::checkToken();

			// notify system
			$container->getDispatcher()->notify('SLY_BE_LOGOUT', $user);
			$uService->logout();
			$container->getFlashMessage()->appendInfo(t('you_have_been_logged_out'));
		}

		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		return true;
	}
}
