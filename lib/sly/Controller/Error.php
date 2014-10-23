<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Error extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $exception;

	public function __construct(Exception $e) {
		$this->exception = $e;
	}

	public function indexAction() {
		// use the system error handler to process the exception
		$container    = $this->getContainer();
		$errorHandler = $container['sly-error-handler'];

		if ($errorHandler && $errorHandler instanceof sly_ErrorHandler) {
			$errorHandler->handleException($this->exception);
		}

		$this->render('error/index.phtml', array('e' => $this->exception), false);
	}

	public function checkPermission($action) {
		return true;
	}
}
