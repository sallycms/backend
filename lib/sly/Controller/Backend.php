<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class sly_Controller_Backend extends sly_Controller_Base {
	public function __construct() {
		$response = sly_Core::getContainer()->getResponse();
		$response->setContentType('text/html', 'UTF-8');
	}

	protected function getViewFolder() {
		return SLY_SALLYFOLDER.'/backend/views/';
	}

	/**
	 * Render a view
	 *
	 * This method renders a view, making all keys in $params available as
	 * variables.
	 *
	 * @param  string  $filename      the filename to include, relative to the view folder
	 * @param  array   $params        additional parameters (become variables)
	 * @param  boolean $returnOutput  set to false to not use an output buffer
	 * @return string                 the generated output if $returnOutput, else null
	 */
	protected function render() {
		// make router available to all controller views
		$router = $this->getContainer()->getApplication()->getRouter();
		$params = array_merge(array('_router' => $router), func_get_arg(1));

		return parent::render(func_get_arg(0), $params, func_get_arg(2));
	}

	protected function redirect($params = array(), $page = null, $code = 302) {
		$this->container->getApplication()->redirect($page, $params, $code);
	}

	protected function redirectResponse($params = array(), $controller = null, $action = null, $code = 302) {
		return $this->container->getApplication()->redirectResponse($controller, $action, $params, $code);
	}

	/**
	 * get the current logged in user
	 *
	 * @return sly_Model_User
	 */
	protected function getCurrentUser() {
		return $this->getContainer()->getUserService()->getCurrentUser();
	}

	/**
	 * get backend flash message
	 *
	 * @return sly_Util_FlashMessage
	 */
	protected function getFlashMessage() {
		return $this->getContainer()->getFlashMessage();
	}
}
