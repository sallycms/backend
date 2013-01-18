<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Mediapool_Structure extends sly_Controller_Mediapool_Base {
	public function indexAction() {
		$this->init();
		$this->indexView();
	}

	public function addAction() {
		$request = $this->getRequest();

		if ($request->isMethod('POST')) {
			$service  = sly_Service_Factory::getMediaCategoryService();
			$name     = $request->post('catname', 'string', '');
			$parentID = $request->post('cat_id', 'int', 0);
			$flash    = sly_Core::getFlashMessage();

			try {
				$parent = $service->findById($parentID); // may be null
				$service->add($name, $parent);

				$flash->appendInfo(t('category_added', $name));
				return $this->redirectResponse(array('cat_id' => $parentID));
			}
			catch (Exception $e) {
				$flash->appendWarning($e->getMessage());
			}
		}

		$this->indexView('add');
	}

	public function editAction() {
		$request = $this->getRequest();

		if ($request->isMethod('POST')) {
			$editID   = $request->post('edit_id', 'int', 0);
			$service  = sly_Service_Factory::getMediaCategoryService();
			$category = $service->findById($editID);

			if ($category) {
				$name  = $request->post('catname', 'string', '');
				$flash = sly_Core::getFlashMessage();

				try {
					$category->setName($name);
					$service->update($category);

					$flash->appendInfo(t('category_updated', $name));
					return $this->redirectResponse(array('cat_id' => $category->getParentId()));
				}
				catch (Exception $e) {
					$flash->appendWarning($e->getMessage());
				}
			}
		}

		$this->indexView('edit');
	}

	public function deleteAction() {
		$editID   = $this->getRequest()->post('edit_id', 'int', 0);
		$service  = sly_Service_Factory::getMediaCategoryService();
		$category = $service->findById($editID);

		if ($category) {
			$parent = $category->getParentId();
			$flash  = sly_Core::getFlashMessage();

			try {
				$service->deleteByCategory($category);
				$flash->appendInfo(t('category_deleted'));
				return $this->redirectResponse(array('cat_id' => $parent));
			}
			catch (Exception $e) {
				$flash->appendWarning($e->getMessage());
			}
		}

		$this->indexView('delete');
	}

	public function checkPermission($action) {
		if (!parent::checkPermission($action)) return false;

		if ($this->getRequest()->isMethod('POST') && in_array($action, array('add', 'edit', 'delete'))) {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}

	protected function indexView($action = '') {
		$request = $this->getRequest();
		$cat     = $request->request('cat_id', 'int', 0);
		$active  = $request->request('edit_id', 'int', 0);
		$cat     = sly_Util_MediaCategory::findById($cat);
		$active  = sly_Util_MediaCategory::findById($active);

		if ($cat === null) {
			$children = sly_Util_MediaCategory::getRootCategories();
		}
		else {
			$children = $cat->getChildren();
		}

		$this->init();
		$this->render('mediapool/structure.phtml', array(
			'action'   => $action,
			'cat'      => $cat,
			'children' => $children,
			'active'   => $active,
			'args'     => $this->args
		), false);
	}
}
