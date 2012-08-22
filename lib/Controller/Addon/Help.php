<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Addon_Help extends sly_Controller_Addon implements sly_Controller_Interface {
	public function indexAction() {
		$this->init();
		$addon = $this->getAddOn();

		if ($addon) {
			$this->render('addon/help.phtml', array('addon' => $addon), false);
		}
		else {
			print sly_Helper_Message::warn(t('addon_not_found', $addon));
		}
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('pages', 'addons'));
	}
}
