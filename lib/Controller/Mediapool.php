<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool extends sly_Controller_Mediapool_Base implements sly_Controller_Interface {
	public function indexAction() {
		$this->init();
		$this->render('mediapool/toolbar.phtml', array(), false);

		$files = $this->getFiles();

		if (empty($files)) {
			sly_Core::getFlashMessage()->addInfo(t('no_media_found'));
		}

		print sly_Helper_Message::renderFlashMessage();

		if (!empty($files)) {
			$this->render('mediapool/index.phtml', compact('files'), false);
		}
	}

	public function batchAction() {
		$this->init();

		if (!$this->isMediaAdmin()) {
			return $this->indexAction();
		}

		$media   = sly_postArray('selectedmedia', 'int');
		$flash   = sly_Core::getFlashMessage();
		$service = sly_Service_Factory::getMediumService();

		// check selection

		if (empty($media)) {
			$flash->appendWarning(t('no_files_selected'));
			return $this->indexAction();
		}

		// pre-filter the selected media

		foreach ($media as $idx => $mediumID) {
			$medium = sly_Util_Medium::findById($mediumID);

			if (!$medium) {
				$flash->appendWarning(t('file_not_found', $mediumID));
				unset($media[$idx]);
			}
			else {
				$media[$idx] = $medium;
			}
		}

		// perform actual work

		if (!empty($_POST['delete'])) {
			foreach ($media as $medium) {
				$this->deleteMedium($medium, $flash, false);
			}
		}
		else {
			foreach ($media as $medium) {
				$medium->setCategoryId($this->category);
				$service->update($medium);
			}

			$flash->appendInfo(t('selected_files_moved'));
		}

		// refresh asset cache
		$this->revalidate();

		return $this->redirectResponse();
	}
}
