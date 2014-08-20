<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool extends sly_Controller_Mediapool_Base implements sly_Controller_Interface {
	public function indexAction() {
		$this->init();

		$dispatcher = $this->container->getDispatcher();
		$selected   = $this->getCurrentCategory();
		$files      = $this->getFiles();

		print $this->render('mediapool/toolbar.phtml', compact('selected', 'dispatcher'));

		if (empty($files)) {
			$this->container->getFlashMessage()->addInfo(t('no_media_found'));
		}

		print sly_Helper_Message::renderFlashMessage();

		if (!empty($files)) {
			print $this->render('mediapool/index.phtml', compact('files'));
		}
	}

	public function batchAction() {
		$this->init();

		if (!$this->isMediaAdmin()) {
			return $this->indexAction();
		}

		$request = $this->getRequest();
		$media   = $request->postArray('selectedmedia', 'int');
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

		if ($request->post->has('delete')) {
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

	protected function getFiles() {
		$cat   = $this->getCurrentCategory();
		$where = 'f.category_id = '.$cat;
		$where = sly_Core::dispatcher()->filter('SLY_MEDIA_LIST_QUERY', $where, array('category_id' => $cat));
		$where = '('.$where.')';
		$types = $this->popupHelper->getArgument('types');

		if (!empty($types)) {
			$types = explode('|', preg_replace('#[^a-z0-9/+.-|]#i', '', $types));

			if (!empty($types)) {
				$where .= ' AND filetype IN ("'.implode('","', $types).'")';
			}
		}

		$db     = $this->getContainer()->getPersistence();
		$prefix = sly_Core::getTablePrefix();
		$order  = $this->getfileOrder();
		$query  = 'SELECT f.id FROM '.$prefix.'file f LEFT JOIN '.$prefix.'file_category c ON f.category_id = c.id WHERE '.$where.' ORDER BY f.'.$order;
		$files  = array();

		$db->query($query);

		foreach ($db as $row) {
			$files[$row['id']] = sly_Util_Medium::findById($row['id']);
		}

		return $files;
	}

	protected function getfileOrder() {
		$id      = 'fileorder';
		$user    = $this->getCurrentUser();
		$default = $user->getAttribute($id, 'title ASC');

		$value = $this->request->request($id, 'string', $default);

		if ($value !== $default) {
			$user->setAttribute($id, $value);
			$this->container->getUserService()->save($user);
		}

		return $value;
	}

	protected function getFileOrderSelect() {
		$id     = 'fileorder';
		$user   = $this->getCurrentUser();
		$value  = $this->getfileOrder	();
		$values = array(
						'title ASC' => t('title'),
						'createdate DESC' => t('createdate')
					);

		return new sly_Form_Select_DropDown($id, t('sort'), $value, $values);
	}
}
