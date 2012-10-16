<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool_Detail extends sly_Controller_Mediapool_Base {
	protected $medium = false;

	public function indexAction() {
		// look for a valid medium via GET/POST
		$retval = $this->checkMedium(false);
		if ($retval) return $retval;

		return $this->indexView();
	}

	public function saveAction() {
		// look for a valid medium via POST only
		$retval = $this->checkMedium(true);
		if ($retval) return $retval;

		if (!$this->canAccessFile($this->medium)) {
			sly_Core::getFlashMessage()->appendWarning(t('no_permission'));
			return $this->indexView();
		}

		if (!empty($_POST['delete'])) {
			return $this->performDelete();
		}

		return $this->performUpdate();
	}

	protected function performUpdate() {
		$medium = $this->medium;
		$target = sly_post('category', 'int', $medium->getCategoryId());
		$flash  = sly_Core::getFlashMessage();

		// only continue if a file was found, we can access it and have access
		// to the target category

		if (!$this->canAccessCategory($target)) {
			$flash->appendWarning(t('you_have_no_access_to_this_medium'));
			return $this->indexView();
		}

		// update our file

		$title = sly_post('title', 'string');

		// upload new file or just change file properties?

		if (!empty($_FILES['file_new']['name']) && $_FILES['file_new']['name'] != 'none') {
			try {
				sly_Util_Medium::upload($_FILES['file_new'], $target, $title, $medium);

				$flash->appendInfo(t('file_changed'));

				return $this->redirectResponse(array('file_id' => $medium->getId()));
			}
			catch (Exception $e) {
				$code = $e->getCode();
				$msg  = t($code === sly_Util_Medium::ERR_TYPE_MISMATCH ? 'types_of_old_and_new_do_not_match' : 'an_error_happened_during_upload');

				$flash->appendWarning($msg);
			}
		}
		else {
			try {
				$medium->setTitle($title);
				$medium->setCategoryId($target);

				$service = sly_Service_Factory::getMediumService();
				$service->update($medium);

				$flash->appendInfo(t('medium_updated'));

				return $this->redirectResponse(array('file_id' => $medium->getId()));
			}
			catch (Exception $e) {
				$flash->appendWarning($e->getMessage());
			}
		}

		return $this->indexView();
	}

	protected function performDelete() {
		$this->deleteMedium($this->medium, sly_Core::getFlashMessage());
		return $this->redirectResponse(null, 'mediapool');
	}

	public function checkPermission($action) {
		if (!parent::checkPermission($action)) return false;

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}

	protected function checkMedium($requirePost) {
		$this->medium = $this->getCurrentMedium($requirePost);

		if (!$this->medium) {
			return $this->redirectResponse(null, 'mediapool');
		}
	}

	protected function getCurrentMedium($forcePost = false) {
		$fileID   = $forcePost ? sly_post('file_id', 'int', -1)      : sly_request('file_id', 'int', -1);
		$fileName = $forcePost ? sly_post('file_name', 'string', '') : sly_request('file_name', 'string', '');
		$service  = sly_Service_Factory::getMediumService();

		if (mb_strlen($fileName) > 0) {
			$media = $service->find(array('filename' => $fileName), null, null, 'LIMIT 1');

			if (!empty($media)) {
				return reset($media);
			}
		}
		elseif ($fileID > 0) {
			return $service->findById($fileID);
		}

		return null;
	}

	protected function indexView() {
		$this->init();
		$this->render('mediapool/detail.phtml', array(
			'medium' => $this->medium
		), false);
	}
}
