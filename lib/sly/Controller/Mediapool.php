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

		$this->render('mediapool/toolbar.phtml', compact('selected', 'dispatcher'), false);

		if (empty($files)) {
			$this->getFlashMessage()->addInfo(t('no_media_found'));
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

		$container = $this->getContainer();
		$request   = $this->getRequest();
		$service   = $container->getMediumService();
		$flash     = $container->getFlashMessage();
		$media     = $request->postArray('selectedmedia', 'int');

		// check selection

		if (empty($media)) {
			$flash->appendWarning(t('no_files_selected'));
			return $this->indexAction();
		}

		// pre-filter the selected media

		foreach ($media as $idx => $mediumID) {
			$medium = $service->findById($mediumID);

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
				$this->deleteMedium($medium, $flash);
			}
		}
		else {
			foreach ($media as $medium) {
				$medium->setCategoryId($this->category);
				$service->update($medium);
			}

			$flash->appendInfo(t('selected_files_moved'));
		}

		return $this->redirectResponse();
	}

	protected function getFiles() {
		$service = $this->getContainer()->getMediumService();
		$db      = $this->getContainer()->getPersistence();
		$cat     = $this->getCurrentCategory();

		$where = 'f.category_id = '.$cat;
		$where = $this->getContainer()->getDispatcher()->filter('SLY_MEDIA_LIST_QUERY', $where, array('category_id' => $cat));
		$where = '('.$where.')';
		$types = $this->popupHelper->getArgument('types');

		if (!empty($types)) {
			$types = explode('|', preg_replace('#[^a-z0-9/+.-|]#i', '', $types));

			if (!empty($types)) {
				$where .= ' AND filetype IN ("'.implode('","', $types).'")';
			}
		}

		$where .= ' AND deleted = 0';

		$db     = $this->getContainer()->getPersistence();
		$prefix = $db->getPrefix();
		$order  = $this->getFileOrder();
		$query  = 'SELECT f.id FROM '.$prefix.'file f LEFT JOIN '.$prefix.'file_category c ON f.category_id = c.id WHERE '.$where.' ORDER BY f.'.$order;
		$files  = array();

		$db->query($query);

		foreach ($db as $row) {
			$files[$row['id']] = sly_Util_Medium::findById($row['id']);
		}

		return $files;
	}

	protected function getFileOrder() {
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
		$value  = $this->getFileOrder();
		$values = array(
			'title ASC'       => t('title'),
			'createdate DESC' => t('createdate')
		);

		return new sly_Form_Select_DropDown($id, t('sort'), $value, $values);
	}
}
