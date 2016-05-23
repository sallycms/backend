<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
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
		// $layout->pageHeader(t('login_title'));

		$action = strtolower($action);

		if ($action === 'logout') {
			$method = 'logoutAction';
		}
		else {
			// if already logged in, forward to the startpage
			$user     = $this->getCurrentUser();
			$loggedIn = $user && ($user->isAdmin() || $user->hasPermission('apps', 'backend'));

			if ($loggedIn) {
				return $this->redirectToStartpage($user, null);
			}

			if (in_array(strtolower($action), array('index', 'login'))) {
				$method = $action.'Action';
			}
			else {
				$method = 'indexAction';
			}
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
			$loginOK = $user->isAdmin() || $user->hasPermission('apps', 'backend');
		}

		if ($loginOK !== true) {
			$msg = t('login_error', '<strong>'.$container->getConfig()->get('relogindelay').'</strong>');

			$container->getFlashMessage()->appendWarning($msg);

			return $this->redirectResponse(array(), 'login', 'index', 302);
		}
		else {
			// notify system
			$container->getDispatcher()->notify('SLY_BE_LOGIN', $user);

			// redirect to referer
			$referer = $request->post('referer', 'string', false);

			return $this->redirectToStartpage($user, $referer);
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

	protected function redirectToStartpage(sly_Model_User $user, $target) {
		$base  = basename($target);
		$valid =
			$target &&
			!sly_Util_String::startsWith($base, 'index.php?page=login') &&
			strpos($target, '/login') === false &&
			strpos($target, '/setup') === false
		;

		if ($valid) {
			$url = $target;
			$msg = t('redirect_previous_page', $target);
		}
		else {
			$router = $this->getContainer()->getApplication()->getRouter();
			$url    = $router->getAbsoluteUrl($user->getStartPage() ?: 'profile');
			$msg    = t('redirect_startpage', $url);
		}

		$response = $this->getContainer()->getResponse();

		$response->setStatusCode(302);
		$response->setHeader('Location', $url);
		$response->setContent($msg);

		return $response;
	}
}
