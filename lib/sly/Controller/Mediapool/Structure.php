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
			$container = $this->getContainer();
			$service   = $container->getMediaCategoryService();
			$flash     = $container->getFlashMessage();
			$name      = $request->post('catname', 'string', '');
			$parentID  = $request->post('cat_id', 'int', 0);

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
			$container = $this->getContainer();
			$service   = $container->getMediaCategoryService();
			$flash     = $container->getFlashMessage();
			$editID    = $request->post('edit_id', 'int', 0);
			$category  = $service->findById($editID);

			if ($category) {
				$name = $request->post('catname', 'string', '');

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
		$container = $this->getContainer();
		$service   = $container->getMediaCategoryService();
		$flash     = $container->getFlashMessage();
		$editID    = $this->getRequest()->post('edit_id', 'int', 0);
		$category  = $service->findById($editID);

		if ($category) {
			$parent = $category->getParentId();

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
		$container = $this->getContainer();
		$request   = $this->getRequest();
		$service   = $container->getMediaCategoryService();
		$cat       = $request->request('cat_id', 'int', 0);
		$active    = $request->request('edit_id', 'int', 0);
		$cat       = $service->findById($cat);
		$active    = $service->findById($active);

		if ($cat === null) {
			$children = $service->findByParentId(0);
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
