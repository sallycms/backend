<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Structure extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $categoryId;
	protected $clangId;
	protected $artService;
	protected $catService;
	protected $dontRedirect;

	public static $viewPath = 'structure/';

	public function __construct($dontRedirect = false) {
		$this->dontRedirect = $dontRedirect;

		parent::__construct();
	}

	protected function redirectToBaseLanguage() {
		if (!$this->dontRedirect) {
			$user    = $this->getCurrentUser();
			$allowed = $user->getAllowedCLangs();
			$request = $this->getRequest();

			if (!empty($user) && !empty($allowed) && $request->request('clang', 'int', -1) === -1 && !in_array(sly_Core::getDefaultClangId(), $allowed)) {
				$this->redirect(array('clang' => reset($allowed)));
			}
		}
	}

	protected function init() {
		$this->categoryId = $this->getRequest()->request('category_id', 'int', 0);
		$this->clangId    = $this->getRequest()->request('clang', 'int', sly_Core::getDefaultClangId());
		$this->artService = $this->getContainer()->getArticleService();
		$this->catService = $this->getContainer()->getCategoryService();

		$this->redirectToBaseLanguage();
	}

	public function indexAction() {
		$this->init();
		$this->view('index');
	}

	public function deletecategoryAction() {
		$this->init();

		$editId = $this->getRequest()->post('edit_id', 'int', 0);
		$flash  = $this->getFlashMessage();

		try {
			$this->catService->deleteById($editId);
			$flash->prependInfo(t('category_deleted'), true);
		}
		catch (Exception $e) {
			$flash->prependWarning($e->getMessage(), true);
		}

		return $this->redirectToCat();
	}

	public function deletearticleAction() {
		$this->init();

		$editId = $this->getRequest()->post('edit_id', 'int', 0);
		$flash  = $this->getFlashMessage();

		try {
			$this->artService->deleteById($editId);
			$flash->prependInfo(t('article_deleted'), true);
		}
		catch (Exception $e) {
			$flash->prependWarning($e->getMessage(), true);
		}

		return $this->redirectToCat();
	}

	public function addcategoryAction() {
		$this->init();

		$request = $this->getRequest();

		if ($request->isMethod('POST')) {
			$name     = $request->post('category_name',     'string', '');
			$position = $request->post('category_position', 'int',    0);
			$flash    = $this->getFlashMessage();

			try {
				$this->catService->add($this->categoryId, $name, $position);
				$flash->prependInfo(t('category_added'), true);

				return $this->redirectToCat();
			}
			catch (Exception $e) {
				$flash->prependWarning($e->getMessage(), true);
			}
		}

		$this->view('addcategory', array('renderAddCategory' => true));
	}

	public function addarticleAction() {
		$this->init();

		$request = $this->getRequest();

		if ($request->isMethod('POST')) {
			$name     = $request->post('article_name',     'string', '');
			$position = $request->post('article_position', 'int',    0);
			$flash    = $this->getFlashMessage();

			try {
				$this->artService->add($this->categoryId, $name, $position);
				$flash->prependInfo(t('article_added'), true);

				return $this->redirectToCat();
			}
			catch (Exception $e) {
				$flash->prependWarning($e->getMessage(), true);
			}
		}

		$this->view('addarticle', array('renderAddArticle' => true));
	}

	public function editcategoryAction() {
		$this->init();

		$request = $this->getRequest();
		$editId  = $request->request('edit_id', 'int', 0);

		if ($request->isMethod('POST')) {
			$name     = $request->post('category_name',     'string', '');
			$position = $request->post('category_position', 'int',    0);
			$flash    = $this->getFlashMessage();

			try {
				$editCategory = $this->catService->findByPK($editId, $this->clangId);
				$this->catService->edit($editCategory, $name, $position);
				$flash->prependInfo(t('category_updated'), true);

				return $this->redirectToCat();
			}
			catch (Exception $e) {
				$flash->prependWarning($e->getMessage(), true);
			}
		}

		$this->view('editcategory', array('renderEditCategory' => $editId));
	}

	public function editarticleAction() {
		$this->init();

		$request = $this->getRequest();
		$editId  = $request->request('edit_id', 'int', 0);

		if ($request->isMethod('POST')) {
			$name     = $request->post('article_name',     'string', '');
			$position = $request->post('article_position', 'int',    0);
			$flash    = $this->getFlashMessage();

			try {
				$editArticle = $this->artService->findByPK($editId, $this->clangId);
				$this->artService->edit($editArticle, $name, $position);
				$flash->prependInfo(t('article_updated'), true);

				return $this->redirectToCat();
			}
			catch (Exception $e) {
				$flash->prependWarning($e->getMessage(), true);
			}
		}

		$this->view('editarticle', array('renderEditArticle' => $editId));
	}

	/**
	 * returns the breadcrumb string
	 *
	 * @return string
	 */
	protected function getBreadcrumb() {
		// @edge / TODO
		//
		// move breadcrumb markup from controller to view (toolbars/breadcrumb.phtml)

		$result = '';
		$cat    = $this->catService->findByPK($this->categoryId, $this->clangId);
		$router = $this->getContainer()->getApplication()->getRouter();

		if ($cat) {
			foreach ($cat->getParentTree() as $parent) {
				if ($this->canViewCategory($parent->getId())) {
					$result .= '<li><a href="'.$router->getUrl(null, null, array('category_id' => $parent->getId(), 'clang' => $this->clangId)).'">'.sly_html($parent->getName()).'</a></li>';
				}
			}
		}

		// @edge fixed whitespace line breaks
		$result = '<ul class="sly-navi-path"><li>'.t('path').'</li><li><a href="'.$router->getUrl(null, null, array('clang' => $this->clangId)).'">'.t('home').'</a></li>'.$result.'</ul>';

		return $result;
	}

	/**
	 * checks if a user can edit a category
	 *
	 * @param  int $categoryId
	 * @return boolean
	 */
	protected function canEditCategory($categoryId) {
		$user = $this->getCurrentUser();
		return sly_Backend_Authorisation_Util::canEditArticle($user, $categoryId);
	}

	/**
	 * checks if a user can view a category
	 *
	 * @param  int $categoryId
	 * @return boolean
	 */
	protected function canViewCategory($categoryId) {
		$user = $this->getCurrentUser();
		return sly_Backend_Authorisation_Util::canReadCategory($user, $categoryId);
	}

	/**
	 * checks if a user can edit an article
	 *
	 * @param  int $articleId
	 * @return boolean
	 */
	protected function canEditContent($articleId) {
		$user = $this->getCurrentUser();
		return sly_Backend_Authorisation_Util::canEditContent($user, $articleId);
	}

	/**
	 * checks action permissions for the current user
	 *
	 * @return boolean
	 */
	public function checkPermission($action) {
		$request    = $this->getRequest();
		$categoryId = $request->request('category_id', 'int', 0);
		$editId     = $request->request('edit_id', 'int');
		$clang      = $request->request('clang', 'int', sly_Core::getDefaultClangId());
		$user       = $this->getCurrentUser();

		if ($user === null) {
			return false;
		}

		if ($request->isMethod('POST')) {
			sly_Util_Csrf::checkToken();
		}

		if ($user->isAdmin()) return true;
		if (!$user->hasRight('pages', 'structure')) return false;

		$this->redirectToBaseLanguage();

		if (!sly_Util_Language::hasPermissionOnLanguage($user, $clang)) return false;

		if ($action === 'index') {
			return $this->canViewCategory($categoryId);
		}

		if (sly_Util_String::startsWith($action, 'edit') || sly_Util_String::startsWith($action, 'delete')) {
			return $this->canEditCategory($editId);
		}
		elseif (sly_Util_String::startsWith($action, 'add')) {
			return $this->canEditCategory($categoryId);
		}

		return true;
	}

	/**
	 *
	 * @param string $action the current action
	 */
	protected function view($action, $params = array()) {
		/**
		 * stop the view if no languages are available
		 * but present a nice message
		 */
		$layout     = $this->getContainer()->getLayout();
		$dispatcher = $this->getContainer()->getDispatcher();

		if (count(sly_Util_Language::findAll()) === 0) {
			$layout->pageHeader(t('structure'));
			print sly_Helper_Message::info(t('no_languages_yet'));
			return;
		}

		$layout->pageHeader(t('structure')/*, $this->getBreadcrumb()*/);

		$this->render('toolbars/languages.phtml', array(
			'controller' => 'structure',
			'curClang'   => $this->clangId,
			'params'     => array('category_id' => $this->categoryId)
		), false);

		$this->render('toolbars/breadcrumb.phtml', array(
			'breadcrumb' => $this->getBreadcrumb()
		), false);

		print $dispatcher->filter('PAGE_STRUCTURE_HEADER', '', array(
			'category_id' => $this->categoryId,
			'clang'       => $this->clangId
		));

		// render flash message
		print sly_Helper_Message::renderFlashMessage();

		$currentCategory = $this->catService->findByPK($this->categoryId, $this->clangId);
		$categories      = $this->catService->findByParentId($this->categoryId, $this->clangId, false);
		$articles        = $this->artService->findArticlesByCategory($this->categoryId, $this->clangId, false);
		$maxPosition     = $this->artService->getMaxPosition($this->categoryId);
		$maxCatPosition  = $this->catService->getMaxPosition($this->categoryId);

		/**
		 * filter categories
		 */
		foreach ($categories as $key => $category) {
			if (!$this->canViewCategory($category->getId())) {
				unset($categories[$key]);
			}
		}

		/**
		 * filter articles
		 */
		foreach ($articles as $key => $article) {
			if (!$this->canEditContent($article->getId())) {
				unset($articles[$key]);
			}
		}

		$params = array_merge(array(
			'renderAddCategory'  => false,
			'renderEditCategory' => false,
			'renderAddArticle'   => false,
			'renderEditArticle'  => false,
			'action'             => $action,
			'maxPosition'        => $maxPosition,
			'maxCatPosition'     => $maxCatPosition,
			'categoryId'         => $this->categoryId,
			'clangId'            => $this->clangId,
			'canAdd'             => $this->canEditCategory($this->categoryId),
			'canEdit'            => $this->canEditCategory($this->categoryId),
		),	$params);

		$renderParams = array_merge($params, array(
			'categories'      => $categories,
			'currentCategory' => $currentCategory,
		));

		$this->render(self::$viewPath.'category_table.phtml', $renderParams, false);

		$renderParams = array_merge($params, array(
			'articles' => $articles,
		));

		$this->render(self::$viewPath.'article_table.phtml', $renderParams, false);
	}

	protected function redirectToCat($catID = null, $clang = null) {
		$clang  = $clang === null ? $this->clangId    : (int) $clang;
		$catID  = $catID === null ? $this->categoryId : (int) $catID;
		$params = array('category_id' => $catID, 'clang' => $clang);

		return $this->redirectResponse($params);
	}
}
