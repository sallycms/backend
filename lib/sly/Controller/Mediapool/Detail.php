<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
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
			$this->getContainer()->getFlashMessage()->appendWarning(t('no_permission'));
			return $this->indexView();
		}

		if ($this->getRequest()->post->has('delete')) {
			return $this->performDelete();
		}

		return $this->performUpdate();
	}

	protected function performUpdate() {
		$medium    = $this->medium;
		$container = $this->getContainer();
		$request   = $this->getRequest();
		$flash     = $container->getFlashMessage();
		$target    = $request->post('category', 'int', $medium->getCategoryId());

		// only continue if a file was found, we can access it and have access
		// to the target category

		if (!$this->canAccessCategory($target)) {
			$flash->appendWarning(t('you_have_no_access_to_this_medium'));
			return $this->indexView();
		}

		// update our file

		$title   = $request->post('title', 'string');
		$service = $container->getMediumService();
		$files   = $request->files;

		$medium->setTitle($title);
		$medium->setCategoryId($target);

		// upload new file or just change file properties

		try {
			if (!empty($files['file_new']['name']) && $files['file_new']['name'] !== 'none') {
				$service->replaceByUpload($medium, $files['file_new']);
				$service->update($medium);
				$flash->appendInfo(t('file_changed'));
			}
			else {
				$service->update($medium);
				$flash->appendInfo(t('medium_updated'));
			}

			return $this->redirectResponse(array('file_id' => $medium->getId()));
		}
		catch (Exception $e) {
			$flash->appendWarning($e->getMessage());
		}

		return $this->indexView();
	}

	protected function performDelete() {
		$this->deleteMedium($this->medium, sly_Core::getFlashMessage());
		return $this->redirectResponse(null, 'mediapool');
	}

	public function checkPermission($action) {
		if (!parent::checkPermission($action)) return false;

		if ($this->getRequest()->isMethod('POST')) {
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
		$request  = $this->getRequest();
		$fileID   = $forcePost ? $request->post('file_id', 'int', -1)      : $request->request('file_id', 'int', -1);
		$fileName = $forcePost ? $request->post('file_name', 'string', '') : $request->request('file_name', 'string', '');
		$service  = $this->getContainer()->getMediumService();

		if (mb_strlen($fileName) > 0) {
			return $service->findByFilename($fileName);
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
