<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Contentmeta extends sly_Controller_Content_Base {
	public function indexAction() {
		$this->init();

		if ($this->header() !== true) return;

		$this->render('content/meta/index.phtml', array(
			'article' => $this->article,
			'slot'    => $this->slot,
			'user'    => sly_Util_User::getCurrentUser()
		), false);
	}

	protected function getPageName() {
		return 'contentmeta';
	}

	public function processmetaformAction() {
		$this->init();

		try {
			// save metadata
			if (sly_post('save_meta', 'boolean', false)) {
				return $this->saveMeta();
			}

			// make article the startarticle
			elseif (sly_post('to_startarticle', 'boolean', false) && $this->canConvertToStartArticle()) {
				return $this->convertToStartArticle();
			}

			// copy content to another language
			elseif (sly_post('copy_content', 'boolean', false)) {
				return $this->copyContent();
			}

			// move article to other category
			elseif (sly_post('move_article', 'boolean', false)) {
				return $this->moveArticle();
			}

			elseif (sly_post('copy_article', 'boolean', false)) {
				return $this->copyArticle();
			}

			elseif (sly_post('move_category', 'string')) {
				return $this->moveCategory();
			}
		}
		catch (Exception $e) {
			sly_Core::getFlashMessage()->appendWarning($e->getMessage());
		}

		$this->indexAction();
	}

	private function saveMeta() {
		$name  = sly_post('meta_article_name', 'string');
		$flash = sly_Core::getFlashMessage();

		sly_Service_Factory::getArticleService()->edit($this->article->getId(), $this->article->getClang(), $name);

		// notify system
		$flash->appendInfo(t('metadata_updated'));

		sly_Core::dispatcher()->notify('SLY_ART_META_UPDATED', $this->article, array(
			'id'    => $this->article->getId(),   // deprecated
			'clang' => $this->article->getClang() // deprecated
		));

		return $this->redirectToArticle();
	}

	private function convertToStartArticle() {
		$flash   = sly_Core::getFlashMessage();
		$service = sly_Service_Factory::getArticleService();

		try {
			$service->convertToStartArticle($this->article->getId());
			$flash->appendInfo(t('article_converted_to_startarticle'));
		}
		catch (sly_Exception $e) {
			$flash->appendWarning(t('cannot_convert_to_startarticle').': '.$e->getMessage());
		}

		return $this->redirectToArticle();
	}

	private function copyContent() {
		$srcClang  = sly_post('clang_a', 'int', 0);
		$dstClangs = array_unique(sly_postArray('clang_b', 'int'));
		$user      = sly_Util_User::getCurrentUser();
		$infos     = array();
		$errs      = array();
		$articleID = $this->article->getId();

		if (empty($dstClangs)) {
			throw new sly_Authorisation_Exception(t('no_language_selected'));
		}

		if (!sly_Util_Language::hasPermissionOnLanguage($user, $srcClang)) {
			$lang = sly_Util_Language::findById($srcClang);
			throw new sly_Authorisation_Exception(t('you_have_no_access_to_this_language', sly_translate($lang->getName())));
		}

		foreach ($dstClangs as $targetClang) {
			if (!sly_Util_Language::hasPermissionOnLanguage($user, $targetClang)) {
				$lang = sly_Util_Language::findById($targetClang);
				$errs[$targetClang] = t('you_have_no_access_to_this_language', sly_translate($lang->getName()));
				continue;
			}

			if (!$this->canCopyContent($srcClang, $targetClang)) {
				$errs[$targetClang] = t('no_rights_to_this_function');
				continue;
			}

			try {
				sly_Service_Factory::getArticleService()->copyContent($articleID, $articleID, $srcClang, $targetClang);
				$infos[$targetClang] = t('article_content_copied');
			}
			catch (sly_Exception $e) {
				$errs[$targetClang] = t('cannot_copy_article_content').': '.$e->getMessage();
			}
		}

		// only prepend language names if there were more than one language
		if (count($dstClangs) > 1) {
			foreach ($infos as $clang => $msg) {
				$lang = sly_Util_Language::findById($clang);
				$infos[$clang] = sly_translate($lang->getName()).': '.$msg;
			}

			foreach ($errs as $clang => $msg) {
				$lang = sly_Util_Language::findById($clang);
				$errs[$clang] = sly_translate($lang->getName()).': '.$msg;
			}
		}

		$flash = sly_Core::getFlashMessage();

		foreach ($infos as $msg) $flash->appendInfo($info);
		foreach ($errs  as $msg) $flash->appendWarning($msg);

		return $this->redirectToArticle();
	}

	private function moveArticle() {
		$target  = sly_post('category_id_new', 'int', 0);
		$flash   = sly_Core::getFlashMessage();
		$service = sly_Service_Factory::getArticleService();

		if ($this->canMoveArticle()) {
			try {
				$service->move($this->article->getId(), $target);
				$flash->appendInfo(t('article_moved'));
			}
			catch (sly_Exception $e) {
				$flash->appendWarning(t('cannot_move_article').': '.$e->getMessage());
			}
		}
		else {
			$flash->appendWarning(t('no_rights_to_this_function'));
		}

		return $this->redirectToArticle();
	}

	private function copyArticle() {
		$target  = sly_post('category_copy_id_new', 'int', 0);
		$flash   = sly_Core::getFlashMessage();
		$service = sly_Service_Factory::getArticleService();

		if ($this->canCopyArticle($target)) {
			try {
				$newID         = $service->copy($this->article->getId(), $target);
				$this->article = sly_Util_Article::findById($newID);

				$flash->appendInfo(t('article_copied'));
			}
			catch (sly_Exception $e) {
				$flash->appendWarning(t('cannot_copy_article').': '.$e->getMessage());
			}
		}
		else {
			$flash->appendWarning(t('no_rights_to_this_function'));
		}

		return $this->redirectToArticle();
	}

	private function moveCategory() {
		$target  = sly_post('category_id_new', 'int');
		$user    = sly_Util_User::getCurrentUser();
		$flash   = sly_Core::getFlashMessage();
		$service = sly_Service_Factory::getCategoryService();

		if ($this->canMoveCategory() && sly_Util_Article::canEditArticle($user, $target)) {
			try {
				$service->move($this->article->getCategoryId(), $target);
				$flash->appendInfo(t('category_moved'));
			}
			catch (sly_Exception $e) {
				$flash->appendWarning(t('cannot_move_category').': '.$e->getMessage());
			}
		}
		else {
			$flash->appendWarning(t('no_rights_to_this_function'));
		}

		return $this->redirectToArticle();
	}

	/**
	 * @return boolean
	 */
	protected function canMoveArticle() {
		if ($this->article->isStartArticle()) return false;
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || $user->hasRight('article', 'move', 0) || $user->hasRight('article', 'move', $this->article->getId());
	}

	/**
	 * @return boolean
	 */
	protected function canConvertToStartArticle() {
		$user = sly_Util_User::getCurrentUser();
		return sly_Util_Article::canEditArticle($user, $this->article->getCategoryId());
	}

	/**
	 * @return boolean
	 */
	protected function canCopyContent($clang_a, $clang_b) {
		$user    = sly_Util_User::getCurrentUser();
		$editok  = sly_Util_Article::canEditContent($user, $this->article->getId());
		$clangok = sly_Util_Language::hasPermissionOnLanguage($user, $clang_a);
		$clangok = $clangok && sly_Util_Language::hasPermissionOnLanguage($user, $clang_b);

		return $editok && $clangok;
	}

	/**
	 * @return boolean
	 */
	protected function canCopyArticle($target) {
		$user = sly_Util_User::getCurrentUser();
		return sly_Util_Article::canEditArticle($user, $target);
	}

	/**
	 * @return boolean
	 */
	protected function canMoveCategory() {
		if (!$this->article->isStartArticle()) return false;
		$user = sly_Util_User::getCurrentUser();
		return $user->isAdmin() || $user->hasRight('article', 'move', sly_Authorisation_ArticleListProvider::ALL) || $user->hasRight('article', 'move', $this->article->getId());
	}

	protected function redirectToArticle() {
		$artID   = $this->article->getId();
		$clang   = $this->article->getClang();
		$params  = array('article_id' => $artID, 'clang' => $clang);

		return $this->redirectResponse($params);
	}
}
