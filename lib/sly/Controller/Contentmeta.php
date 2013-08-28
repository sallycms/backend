<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
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

		$post        = $this->getRequest()->post;
		$container   = $this->getContainer();
		$userService = $container['sly-service-user'];
		$artService  = $container['sly-service-article'];

		// handle pagination of revision list

		sly_Table::setElementsPerPageStatic(25);
		$paging = sly_Table::getPagingParameters('revisions', true, false);

		// fetch revisions

		$art       = $this->article;
		$revisions = $artService->findAllRevisions($art->getId(), $art->getClang(), $paging['start'], $paging['elements']);
		$total     = $artService->countRevisions($art);

		$this->render('content/meta/index.phtml', array(
			'article'     => $art,
			'slot'        => $this->slot,
			'revisions'   => $revisions,
			'user'        => $userService->getCurrentUser(),
			'clangB'      => $post->get('clang_b', 'int'),
			'userService' => $userService,
			'total'       => $total
		), false);
	}

	protected function getViewFolder() {
		return SLY_SALLYFOLDER.'/backend/views/';
	}

	protected function getPageName() {
		return 'contentmeta';
	}

	public function checkPermission($action) {
		if (parent::checkPermission($action)) {
			if ($this->getRequest()->isMethod('POST')) {
				sly_Util_Csrf::checkToken();
			}

			return true;
		}

		return false;
	}

	public function processmetaformAction() {
		$this->init();

		$post = $this->getRequest()->post;

		try {
			// save metadata
			if ($post->has('save_meta')) {
				return $this->saveMeta();
			}

			// make article the startarticle
			elseif ($post->has('to_startarticle') && $this->canConvertToStartArticle()) {
				return $this->convertToStartArticle();
			}

			// copy content to another language
			elseif ($post->has('copy_content')) {
				return $this->copyContent();
			}

			// move article to other category
			elseif ($post->has('move_article')) {
				return $this->moveArticle();
			}

			elseif ($post->has('copy_article')) {
				return $this->copyArticle();
			}

			elseif ($post->has('move_category')) {
				return $this->moveCategory();
			}
		}
		catch (Exception $e) {
			sly_Core::getFlashMessage()->appendWarning($e->getMessage());
		}

		$this->indexAction();
	}

	private function saveMeta() {
		$flash   = sly_Core::getFlashMessage();

		// notify system
		$flash->appendInfo(t('metadata_updated'));

		sly_Core::dispatcher()->notify('SLY_ART_META_UPDATED', $this->article, array(
			'id'    => $this->article->getId(),   // deprecated
			'clang' => $this->article->getClang() // deprecated
		));

		return $this->redirectToArticle();
	}

	private function convertToStartArticle() {
		$flash   = $this->getContainer()->getFlashMessage();
		$service = $this->getContainer()->getArticleService();

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
		$request        = $this->getRequest();
		$srcClang       = $request->post('clang', 'int', 0);
		$dstClangs      = array_unique($request->postArray('clang_b', 'int'));
		$user           = sly_Util_User::getCurrentUser();
		$infos          = array();
		$errs           = array();
		$articleService = $this->getContainer()->getArticleService();

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

			if (!$this->canCopyContent($targetClang)) {
				$errs[$targetClang] = t('no_rights_to_this_function');
				continue;
			}

			try {
				if ($targetClang === $this->article->getClang()) {
					continue;
				}
				$target = $articleService->findByPK($this->article->getId(), $targetClang);
				$target = $articleService->touch($target);
				$articleService->copyContent($this->article, $target, $user);
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

		foreach ($infos as $msg) {
			$flash->appendInfo($msg);
		}

		foreach ($errs as $msg) {
			$flash->appendWarning($msg);
		}

		return $this->redirectToArticle();
	}

	private function moveArticle() {
		$target  = $this->getRequest()->post('category_id_new', 'int', 0);
		$flash   = sly_Core::getFlashMessage();
		$service = $this->getContainer()->getArticleService();

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
		$target  = $this->getRequest()->post('category_copy_id_new', 'int', 0);
		$flash   = sly_Core::getFlashMessage();
		$service = $this->getContainer()->getArticleService();

		if ($this->canCopyArticle($target)) {
			try {
				$this->article = $service->copy($this->article->getId(), $target);

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
		$target  = $this->getRequest()->post('category_id_new', 'int');
		$user    = sly_Util_User::getCurrentUser();
		$flash   = sly_Core::getFlashMessage();
		$service = $this->getContainer()->getCategoryService();

		if ($this->canMoveCategory() && \sly_Backend_Authorisation_Util::canEditArticle($user, $target)) {
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
		return sly_Backend_Authorisation_Util::canEditArticle($user, $this->article->getCategoryId());
	}

	/**
	 * @return boolean
	 */
	protected function canCopyContent($clang_b) {
		$user    = sly_Util_User::getCurrentUser();
		$editok  = sly_Backend_Authorisation_Util::canEditContent($user, $this->article->getId());
		$clangok = sly_Util_Language::hasPermissionOnLanguage($user, $clang_b);

		return $editok && $clangok;
	}

	/**
	 * @return boolean
	 */
	protected function canCopyArticle($target) {
		$user = sly_Util_User::getCurrentUser();
		return sly_Backend_Authorisation_Util::canEditArticle($user, $target);
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
