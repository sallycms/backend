<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System_Languages extends sly_Controller_System {
	protected $func      = '';
	protected $id        = '';
	protected $languages = array();

	public function indexAction() {
		$this->init();

		$languageService = $this->container->getLanguageService();
		$this->languages = $languageService->findAll();

		$this->render('system/languages.phtml', array(), false);
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if (!$user) return false;

		if ($this->getRequest()->isMethod('POST')) {
			sly_Util_Csrf::checkToken();
		}

		return parent::checkPermission($action);
	}

	public function addAction() {
		$request = $this->getRequest();

		if ($request->isMethod('POST')) {
			$this->id    = $request->post('clang_id', 'int', -1);
			$clangName   = $request->post('clang_name', 'string');
			$clangLocale = $request->post('clang_locale', 'string');
			$flash       = sly_Core::getFlashMessage();

			if (!empty($clangName)) {
				try {
					$languageService = $this->getLanguageService();
					$languageService->create(array('name' => $clangName, 'locale' => $clangLocale));

					$flash->appendInfo(t('language_added'));
				}
				catch (Exception $e) {
					$flash->appendWarning($e->getMessage());
				}

				return $this->redirectResponse();
			}
			else {
				$flash->appendWarning(t('plase_enter_a_name'));
			}
		}

		$this->func = 'add';
		return $this->indexAction();
	}

	public function editAction() {
		$request  = $this->getRequest();
		$this->id = $request->request('clang_id', 'int', -1);

		if ($request->isMethod('POST')) {
			$clangName       = $request->post('clang_name', 'string', '');
			$clangLocale     = $request->post('clang_locale', 'string', '');
			$languageService = $this->getLanguageService();
			$clang           = $languageService->findById($this->id);

			if ($clang) {
				$clang->setName($clangName);
				$clang->setLocale($clangLocale);
				$languageService->save($clang);

				sly_Core::getFlashMessage()->appendInfo(t('language_updated'));
				return $this->redirectResponse();
			}
		}

		$this->func = 'edit';
		$this->indexAction();
	}

	public function deleteAction() {
		$clangID   = $this->getRequest()->post('clang_id', 'int', -1);
		$languages = sly_Util_Language::findAll();
		$flash     = sly_Core::getFlashMessage();

		if (isset($languages[$clangID])) {
			$deleted = $this->getLanguageService()->deleteById($clangID);

			if ($deleted > 0) {
				$flash->appendInfo(t('language_deleted'));
			}
			else {
				$flash->appendWarning(t('cannot_delete_language'));
			}
		}

		return $this->redirectResponse();
	}

	/**
	 *
	 * @return sly_Service_Language
	 */
	protected function getLanguageService() {
		return $this->getContainer()->getLanguageService();
	}
}
