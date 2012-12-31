<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
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

		$flash   = sly_Core::getFlashMessage();
		$request = $this->getRequest();

		if (!empty($_FILES['file_new']['name']) && $_FILES['file_new']['name'] != 'none') {
			$title = $request->post('ftitle', 'string');
			$cat   = $this->getCurrentCategory();

			if (!$this->canAccessCategory($cat)) {
				$cat = 0;
			}

			// add the actual database record
			$file = $this->saveMedium($_FILES['file_new'], $cat, $title);

			// close the popup, if requested

			$callback = $request->request('callback', 'string');

			if ($callback && $request->post('saveandexit', 'boolean', false) && $file !== null) {
				$this->render('mediapool/upload_js.phtml', compact('file', 'callback'), false);
				exit;
			}
			elseif ($file !== null) {
				return $this->redirectResponse(null, 'mediapool');
			}
		}
		else {
			$flash->appendWarning(t('file_not_found_maybe_too_big'));
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

	protected function saveMedium(array $fileData, $category, $title) {
		$file  = null;
		$flash = sly_Core::getFlashMessage();

		try {
			$file = sly_Util_Medium::upload($fileData, $category, $title);
			$flash->appendInfo(t('file_added'));
		}
		catch (sly_Exception $e) {
			$flash->appendWarning($e->getMessage());
		}

		return $file;
	}
}
