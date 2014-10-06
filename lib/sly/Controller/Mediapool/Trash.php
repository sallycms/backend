<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool_Trash extends sly_Controller_Mediapool_Base {
	protected $medium = false;

	public function indexAction() {
		$this->init();

		$files = $this->getFiles();

		if (empty($files)) {
			$this->getFlashMessage()->addInfo(t('no_deleted_media_found'));
		}

		print sly_Helper_Message::renderFlashMessage();

		if (!empty($files)) {
			$this->render('mediapool/trash.phtml', compact('files'), false);
		}
	}

	public function batchAction() {
		$request = $this->getRequest();
		$media   = $request->postArray('media', 'int');
		$restore = $request->post('restore', 'boolean');
		$delete  = $request->post('permanent_delete', 'boolean');
		$flash   = $this->getFlashMessage();

		if ($restore) {
			$success = t('media_restored');
			$error   = t('could_not_restore_media');
		}
		elseif ($delete) {
			$success = t('media_permanently_deleted');
			$error   = t('could_not_permanently_delete_media');
		}
		else {
			throw new sly_Exception('Recycle bin action must either permanently delete or restore media');
		}
		try {
			foreach($media as $mediaID) {
				if ($restore) {
					$this->restore($mediaID);
				}
				elseif ($delete) {
					$this->permanentDelete($mediaID);
				}
			}
			$flash->addInfo($success);
		}
		catch (Exception $e) {
			$flash->addWarning($error);
		}

		return $this->redirectResponse(array(), null, 'index');
	}

	protected function permanentDelete($mediaID) {

	}

	protected function restore($mediaID) {

	}

	protected function getFiles() {
		return parent::getFiles();
	}
}
