<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Addon_Help extends sly_Controller_Backend implements sly_Controller_Interface {
	public function indexAction() {
		$service = sly_Service_Factory::getPackageService();
		$package = sly_request('package', 'string', '');
		$pkg     = $service->isRegistered($package) ? $package : null;

		if ($pkg) {
			$layout = sly_Core::getLayout();
			$layout->pageHeader(t('addons'));
			print '<div class="sly-content">';
			print $this->render('addon/help.phtml', array('component' => $pkg));
			print '</div>';
		}
		else {
			$controller = new sly_Controller_Addon();
			return $controller->indexAction();
		}
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return isset($user) && $user->isAdmin();
	}
}
