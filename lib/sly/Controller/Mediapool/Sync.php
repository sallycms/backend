<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool_Sync extends sly_Controller_Mediapool {
	public function indexAction() {
		$this->init();

		$diff = $this->getFileDiff();

		if (empty($diff)) {
			print sly_Helper_Message::info(t('no_file_diffs_found'));
		}
		else {
			$this->render('mediapool/sync.phtml', array('diffFiles' => $diff), false);
		}
	}

	public function syncAction() {
		$request  = $this->getRequest();
		$selected = $request->postArray('sync_files', 'string');
		$flash    = sly_Core::getFlashMessage();

		if (!empty($selected)) {
			$title = $request->post('ftitle', 'string', '');
			$diff  = $this->getFileDiff();
			$cat   = $this->getCurrentCategory();
			$count = 0;

			foreach ($selected as $hash) {
				if (isset($diff[$hash]) && $this->syncMedium($diff[$hash], $cat, $title)) {
					++$count;
				}
			}

			$flash->appendInfo(t('files_synced', $count));
			return $this->redirectResponse();
		}

		$flash->appendWarning(t('no_files_selected'));
		return $this->indexAction();
	}

	public function checkPermission($action) {
		if (!parent::checkPermission($action)) return false;

		if ($action === 'sync') {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}

	protected function syncMedium($filename, $category, $title) {
		$absFile = SLY_MEDIAFOLDER.'/'.$filename;
		if (!file_exists($absFile)) return false;

		// get cleaned filename
		$filename = sly_Util_Directory::fixWindowsDisplayFilename($filename);
		if (empty($title)) $title = $filename;
		$newName  = SLY_MEDIAFOLDER.'/'.sly_Util_Medium::createFilename($filename, false);

		if ($newName !== $absFile) {
			// move file to cleaned filename
			rename($absFile, $newName);
		}

		// create and save the file

		$service = $this->getContainer()->getMediumService();

		try {
			$service->add($newName, $title, $category);
			return true;
		}
		catch (sly_Exception $e) {
			return false;
		}
	}

	protected function getFilesFromFilesystem() {
		$dir   = new sly_Util_Directory(SLY_MEDIAFOLDER);
		$files = $dir->listPlain(true, false);

		return $files ?: array();
	}

	protected function getFilesFromDatabase() {
		$db    = $this->getContainer()->getPersistence();
		$files = array();

		$db->select('file', 'filename');
		foreach ($db as $row) $files[] = $row['filename'];

		return $files;
	}

	protected function getFileDiff() {
		$database   = $this->getFilesFromDatabase();
		$filesystem = $this->getFilesFromFilesystem();
		$diff       = array_diff($filesystem, $database);
		$res        = array();

		// Do not use the filename as the array's key to avoid problems
		// when the filename contains broken characters.

		foreach ($diff as $filename) {
			$hash = substr(md5($filename), 0, 12);
			$res[$hash] = $filename;
		}

		return $res;
	}
}
