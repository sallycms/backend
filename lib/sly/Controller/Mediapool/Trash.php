<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
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

		$files      = $this->getFiles();
		$canDelete  = $this->canDeletePermanent();
		$canRestore = $this->canRestore();

		if (empty($files)) {
			$this->getFlashMessage()->addInfo(t('recycle_bin_is_empty'));
		}

		print sly_Helper_Message::renderFlashMessage();

		if (!empty($files)) {
			$this->render('mediapool/trash.phtml', compact('files', 'canDelete', 'canRestore'), false);
		}
	}

	public function batchAction() {
		$request = $this->getRequest();
		$media   = $request->postArray('media', 'int');
		$restore = $request->post('restore', 'boolean');
		$delete  = $request->post('delete_permanent', 'boolean');
		$flash   = $this->getFlashMessage();

		if ($restore) {
			$success = t('media_restored');
			$error   = t('could_not_restore_media');
		}
		elseif ($delete) {
			$success = t('media_permanently_deleted');
			$error   = t('could_not_permanently_delete_media');
		}

		try {
			foreach($media as $mediaID) {
				if ($restore) {
					$this->restoreMedium($mediaID);
				}
				elseif ($delete) {
					$this->deleteMediumPermanent($mediaID);
				}
			}
			$flash->addInfo($success);
		}
		catch (Exception $e) {
			$flash->addWarning($error);
			$flash->addWarning($e->getMessage());
		}

		return $this->redirectResponse(array(), null, 'index');
	}

	protected function deleteMediumPermanent($mediumID) {
		$this->getContainer()->getDeletedMediumService()->deletePermanentById($mediumID);
	}

	protected function restoreMedium($mediumID) {
		$this->getContainer()->getDeletedMediumService()->restoreMediumById($mediumID);
	}

	protected function getFiles() {
		return $this->getContainer()->getDeletedMediumService()->find();
	}

	public function checkPermission($action) {
		if(parent::checkPermission($action) === false) {
			return false;
		}

		if ($action === 'batch') {
			$request = $this->getRequest();
			$restore = $request->post('restore', 'boolean');
			$delete  = $request->post('delete_permanent', 'boolean');

			if ($restore && $delete) {
				throw new sly_Authorisation_Exception('Recycle bin batch action must either permanently delete or restore media');
			}

			if ($restore) {
				return $this->canRestore();
			}

			if ($delete) {
				return $this->canDeletePermanent();
			}
		}

		return true;
	}
}
