<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
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

		$languageService = sly_Service_Factory::getLanguageService();
		$this->languages = $languageService->find(null, null, 'id');

		$this->render('system/languages.phtml', array(), false);
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if (!$user) return false;

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			sly_Util_Csrf::checkToken();
		}

		return parent::checkPermission($action);
	}

	public function addAction() {
		if (sly_post('sly-submit', 'boolean', false)) {
			$this->id    = sly_post('clang_id', 'int', -1);
			$clangName   = sly_post('clang_name', 'string');
			$clangLocale = sly_post('clang_locale', 'string');
			$flash       = sly_Core::getFlashMessage();

			if (!empty($clangName)) {
				try {
					$languageService = sly_Service_Factory::getLanguageService();
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
				$this->func = 'add';
			}
		}
		else {
			$this->func = 'add';
		}

		return $this->indexAction();
	}

	public function editAction() {
		$this->id = sly_request('clang_id', 'int', -1);

		if (sly_post('sly-submit', 'boolean', false)) {
			$clangName       = sly_post('clang_name', 'string');
			$clangLocale     = sly_post('clang_locale', 'string');
			$languageService = sly_Service_Factory::getLanguageService();
			$clang           = $languageService->findById($this->id);

			if ($clang) {
				$clang->setName($clangName);
				$clang->setLocale($clangLocale);
				$languageService->save($clang);

				sly_Core::getFlashMessage()->appendInfo(t('language_updated'));
				return $this->redirectResponse();
			}
		}
		else {
			$this->func = 'edit';
		}

		$this->indexAction();
	}

	public function deleteAction() {
		$clangID   = sly_post('clang_id', 'int', -1);
		$languages = sly_Util_Language::findAll();
		$flash     = sly_Core::getFlashMessage();

		if (isset($languages[$clangID])) {
			$deleted = sly_Service_Factory::getLanguageService()->deleteById($clangID);

			if ($deleted > 0) {
				$flash->appendInfo(t('language_deleted'));
			}
			else {
				$flash->appendWarning(t('cannot_delete_language'));
			}
		}

		return $this->redirectResponse();
	}
}
