<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class sly_Controller_Content_Base extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $article;
	protected $slot;

	protected function init() {
		$request  = $this->getRequest();
		$id       = $request->request('article_id', 'int', 0);
		$clang    = $request->request('clang',      'int', sly_Core::getDefaultClangId());
		$revision = $request->request('revision',   'int', sly_Service_Article::FIND_REVISION_LATEST);

		$this->article = $this->getContainer()->getArticleService()->findByPK($id, $clang, $revision);

		if ($this->article === null) {
			throw new sly_Exception(t('article_not_found', $id), 404);
		}

		$session    = sly_Core::getSession();
		$this->slot = $request->request('slot', 'string', $session->get('contentpage_slot', 'string', ''));

		// validate slot
		if ($this->article->hasTemplate()) {
			$templateName = $this->article->getTemplateName();
			$tplService   = $this->getContainer()->getTemplateService();

			if (!$tplService->hasSlot($templateName, $this->slot)) {
				$this->slot = $tplService->getFirstSlot($templateName);
			}
		}

		$session->set('contentpage_slot', $this->slot);

		sly_Core::setCurrentArticleId($id);
	}

	protected function renderLanguageBar() {
		$this->render('toolbars/languages.phtml', array(
			'controller' => $this->getPageName(),
			'params'     => array('article_id' => $this->article->getId())
		), false);
	}

	/**
	 * returns the breadcrumb string
	 *
	 * @return string
	 */
	protected function getBreadcrumb() {
		$art    = $this->article;
		$clang  = $art->getClang();
		$user   = sly_Util_User::getCurrentUser();
		$cat    = $art->getCategory();
		$router = $this->getContainer()->getApplication()->getRouter();
		$result = '<ul class="sly-navi-path">
			<li>'.t('path').'</li>
			<li> : <a href="'.$router->getUrl('structure', null, array('clang' => $clang)).'">'.t('home').'</a></li>';

		if ($cat) {
			foreach ($cat->getParentTree() as $parent) {
				if (sly_Backend_Authorisation_Util::canReadCategory($user, $parent->getId())) {
					$result .= '<li> : <a href="'.$router->getUrl('structure', null, array('category_id' => $parent->getId(), 'clang' => $clang)).'">'.sly_html($parent->getName()).'</a></li>';
				}
			}
		}

		$result .= '<li> | '.($art->isStartArticle() ? t('startarticle') : t('article')).'</li>';
		$result .= '<li> : <a href="'.$router->getUrl($this->getPageName(), null, array('article_id' => $art->getId(), 'clang' => $clang)).'">'.str_replace(' ', '&nbsp;', sly_html($art->getName())).'</a></li>';
		$result .= '</ul>';

		return $result;
	}

	protected function header() {
		if ($this->article === null) {
			sly_Core::getLayout()->pageHeader(t('content'));
			print sly_Helper_Message::warn(t('no_articles_available'));
			return false;
		}
		else {
			sly_Core::getLayout()->pageHeader(t('content'), $this->getBreadcrumb());

			$this->renderLanguageBar();

			// extend menu
			print sly_Core::dispatcher()->filter('PAGE_CONTENT_HEADER', '', array(
				'article_id'  => $this->article->getId(),
				'clang'       => $this->article->getClang(),
				'category_id' => $this->article->getCategoryId()
			));

			return true;
		}
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if ($user === null) return false;

		$request   = $this->getRequest();
		$articleId = $request->request('article_id', 'int', 0);

		// all users are allowed to see the error message in init()
		if (!sly_Util_Article::exists($articleId)) return true;

		$clang   = $this->container->getCurrentLanguageID();
		$clangOk = sly_Util_Language::hasPermissionOnLanguage($user, $clang);
		if (!$clangOk) return false;

		return sly_Backend_Authorisation_Util::canEditContent($user, $articleId);
	}
}
