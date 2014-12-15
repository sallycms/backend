<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool_Upload extends sly_Controller_Mediapool_Base {
	public function indexAction() {
		$this->init();
		$this->render('mediapool/upload.phtml', array(), false);
	}

	public function uploadAction() {
		$this->init();

		$container = $this->getContainer();
		$request   = $this->getRequest();
		$flash     = $container->getFlashMessage();

		try {
			// check if a file was received at all
			// the medium service will do a more thoroughly check later on

			if (empty($request->files['file_new']['name']) || $request->files['file_new']['name'] === 'none') {
				throw new Exception(t('file_not_found_maybe_too_big'));
			}

			// move the file into the media filesystem

			$service = $container->getMediumService();
			$fileURI = $service->uploadFile($request->files['file_new'], true, true); // 'sly://media/myfile.jpg'

			// add new medium to database

			$file  = basename($fileURI);
			$title = $request->post('ftitle', 'string');
			$cat   = $this->getCurrentCategory();

			if (!$this->canAccessCategory($cat)) {
				$cat = 0;
			}

			$service->add($file, $title, $cat);
			$flash->appendInfo(t('file_added'));

			// close the popup, if requested

			$callback = $this->popupHelper->get('callback');

			if ($callback && $request->post('saveandexit', 'boolean', false) && $file !== null) {
				$this->render('mediapool/upload_js.phtml', compact('file', 'title', 'callback'), false);
				exit;
			}
			elseif ($file !== null) {
				return $this->redirectResponse(null, 'mediapool');
			}
		}
		catch (Exception $e) {
			$flash->appendWarning($e->getMessage());
		}

		$this->indexAction();
	}

	public function checkPermission($action) {
		if (!parent::checkPermission($action)) return false;

		if ($action === 'upload') {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}
}
