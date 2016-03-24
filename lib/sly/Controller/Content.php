<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Content extends sly_Controller_Content_Base {
	public function indexAction($extraparams = array()) {
		$this->init();
		if ($this->header() !== true) return;

		$service = $this->getContainer()->getArticleTypeService();
		$types   = $service->getArticleTypes();
		$modules = array();

		if ($this->article->hasType()) {
			try {
				$modules = $service->getModules($this->article->getType(), $this->slot);
			}
			catch (Exception $e) {
				$modules = array();
			}
		}

		foreach ($modules as $idx => $module) $modules[$idx] = sly_translate($module);
		foreach ($types as $idx => $type)     $types[$idx]   = sly_translate($type);

		uasort($types, 'strnatcasecmp');
		uasort($modules, 'strnatcasecmp');

		$request = $this->getRequest();
		$params  = array(
			'article'      => $this->article,
			'articletypes' => $types,
			'modules'      => $modules,
			'slot'         => $this->slot,
			'slice_id'     => $request->request('slice_id', 'int', 0),
			'pos'          => $request->request('pos', 'int', 0),
			'function'     => $request->request('function', 'string'),
			'module'       => $request->request('add_module', 'string'),
			'localmsg'     => $request->request('pos', 'int', null) !== null
		);

		$params = array_merge($params, $extraparams);
		$this->render('content/index.phtml', $params, false);
	}

	protected function getPageName() {
		return 'content';
	}

	public function checkPermission($action, $forceModule = null) {
		$this->action = $action;

		if (parent::checkPermission($this->action)) {
			$user    = sly_Util_User::getCurrentUser();
			$request = $this->getRequest();

			if ($request->isMethod('POST') && in_array($action, array('setarticletype', 'moveslice', 'addarticleslice', 'editarticleslice', 'deletearticleslice'))) {
				sly_Util_Csrf::checkToken();
			}

			if ($this->action === 'moveslice') {
				$slice_id = $request->post('slice_id', 'int');
				return $slice_id ? sly_Util_ArticleSlice::canMoveSlice($user, $slice_id) : false;
			}

			if ($action === 'addarticleslice') {
				$module = $forceModule === null ? $request->post('module', 'string', '') : $forceModule;
				return sly_Util_ArticleSlice::canAddModule($user, $module);
			}

			if ($action === 'editarticleslice') {
				// skip the slice stuff if the user is admin
				if ($user->isAdmin()) return true;

				if ($forceModule === null) {
					$sliceservice = $this->getContainer()->getArticleSliceService();
					$slice_id     = $request->post('slice_id', 'int', 0);
					$slice        = $sliceservice->findOne(array('id' => $slice_id));
					$module       = $slice ? $slice->getModule() : null;
				}
				else {
					$module = $forceModule;
				}

				return $module !== null && sly_Util_ArticleSlice::canEditModule($user, $module);
			}

			return true;
		}

		return false;
	}

	public function setarticletypeAction() {
		$this->init();

		$type        = $this->getRequest()->post('article_type', 'string', '');
		$container   = $this->getContainer();
		$typeService = $container['sly-service-articletype'];
		$artService  = $container['sly-service-article'];


		if (!empty($type) && $typeService->exists($type, true)) {
			$flash = $container['sly-flash-message'];

			// change type and update database
			$this->article = $artService->setType($this->article, $type);

			$flash->appendInfo(t('article_updated'));
		}

		$container['sly-dispatcher']->notify('SLY_ART_META_UPDATED', $this->article, array(
			'id'    => $this->article->getId(),   // deprecated
			'clang' => $this->article->getClang() // deprecated
		));

		// re-fetch the article, addOns might have created a new version in the background
		// and we need that one to correct redirect the user
		$this->article = $artService->findByPK($this->article->getId(), $this->article->getClang());

		return $this->redirectToArticle('', null);
	}

	public function movesliceAction() {
		$this->init();

		$request   = $this->getRequest();
		$container = $this->getContainer();
		$sliceID   = $request->post('slice_id', 'int');
		$direction = $request->post('direction', 'string');
		$flash     = $container['sly-flash-message'];
		$service   = $container['sly-service-articleslice'];
		$slice     = $service->findOne(array('id' => $sliceID));

		try {
			// check slice

			if (!$slice) {
				throw new Exception(t('slice_not_found', $sliceID));
			}

			$user = $this->getCurrentUser();

			if (!sly_Util_ArticleSlice::canMoveSlice($user, $sliceID)) {
				throw new Exception(t('no_rights_to_this_module'));
			}

			// check if module exists

			$module = $slice->getModule();
			$module = $container['sly-service-module']->exists($module) ? $module : false;

			if (!$module) {
				throw new Exception(t('module_not_found'));
			}

			// move the slice

			$slice = $service->move($sliceID, $direction);
			$flash->appendInfo(t('slice_moved'));
		}
		catch (Exception $e) {
			$flash->appendWarning(t('cannot_move_slice', $e->getMessage()));
		}

		return $this->redirectToArticle('#messages', $slice);
	}

	public function addarticlesliceAction() {
		$this->init();

		$request   = $this->getRequest();
		$module    = $request->post('module', 'string');
		$params    = array();
		$slicedata = $this->preSliceEdit('add');
		$flash     = $this->getFlashMessage();

		if ($slicedata['SAVE'] === true) {
			$service  = $this->getContainer()->getArticleSliceService();
			$pos      = $request->post('pos', 'int', 0);
			$instance = $service->add($this->article, $this->slot, $module, $slicedata['VALUES'], $pos);

			$flash->appendInfo(t('slice_added'));

			$this->postSliceEdit('add', $instance->getId());

			return $this->redirectToArticle('#messages', $instance);
		}
		else {
			$params['function']    = 'add';
			$params['module']      = $module;
			$params['slicevalues'] = $this->getRequestValues(array());
		}

		return $this->indexAction($params);
	}

	public function editarticlesliceAction() {
		$this->init();

		$request             = $this->getRequest();
		$articleSliceService = $this->getContainer()->getArticleSliceService();
		$slice_id            = $request->post('slice_id', 'int', 0);
		$articleSlice        = $articleSliceService->findOne(array('id' => $slice_id));
		$flash               = $this->getFlashMessage();

		if (!$articleSlice) {
			$flash->appendWarning(t('slice_not_found', $slice_id));
		}
		else {
			$slicedata = $this->preSliceEdit('edit');

			if ($slicedata['SAVE'] === true) {
				$instance = $articleSliceService->edit($this->article, $this->slot, $articleSlice->getPosition(), $slicedata['VALUES']);

				$flash->appendInfo(t('slice_updated'));
				$this->postSliceEdit('edit', $instance->getId());

				$apply = sly_post('btn_update', 'string') !== null;
				return $this->redirectToArticle('#messages', $instance, $apply);
			}

			$extraparams = array();

			if ($request->post->has('btn_update') || $slicedata['SAVE'] !== true) {
				$extraparams['slicevalues'] = $slicedata['VALUES'];
				$extraparams['function']    = 'edit';
			}
		}

		$this->indexAction($extraparams);
	}

	public function deletearticlesliceAction() {
		$this->init();

		$ok      = false;
		$sliceID = $this->getRequest()->post('slice_id', 'int', 0);
		$slice   = sly_Util_ArticleSlice::findById($sliceID);
		$flash   = $this->getFlashMessage();

		if (!$slice) {
			$flash->appendWarning(t('module_not_found', $sliceID));
			return $this->redirectToArticle('#messages', $slice);
		}

		$module = $slice->getModule();
		$user   = sly_Util_User::getCurrentUser();

		if (!sly_Util_ArticleSlice::canEditModule($user, $module)) {
			$flash->appendWarning(t('no_rights_to_this_module'));
			return $this->redirectToArticle('#messages', $slice);
		}

		if ($this->preSliceEdit('delete') !== false) {
			//$ok = sly_Util_ArticleSlice::deleteById($sliceID);
			$article = $this->getContainer()->getArticleService()->touch($slice->getArticle(), $user, array($slice->getSliceId()));
		}

		if ($article) {
			$this->article = $article;
			$flash->appendInfo(t('slice_deleted'));
			$this->postSliceEdit('delete', $sliceID);
		}
		else {
			$flash->appendWarning(t('cannot_delete_slice'));
		}

		return $this->redirectToArticle('#messages', null);
	}

	private function preSliceEdit($function) {
		if (!$this->article->hasTemplate()) return false;

		$request = $this->getRequest();

		if ($function === 'delete' || $function === 'edit') {
			$slice_id = $request->request('slice_id', 'int', 0);
			if (!sly_Util_ArticleSlice::exists($slice_id)) return false;
			$module = sly_Util_ArticleSlice::getModuleNameForSlice($slice_id);
		}
		else {
			$module = $request->post('module', 'string');
		}

		$flash = $this->getFlashMessage();

		if ($function !== 'delete') {
			if (!$this->getContainer()->getModuleService()->exists($module)) {
				$flash->appendWarning(t('module_not_found'));
				return false;
			}

			if (!$this->getContainer()->getArticleTypeService()->hasModule($this->article->getType(), $module, $this->slot)) {
				$slotTitle  = $templateService->getSlotTitle($templateName, $this->slot);
				$moduleName = $this->getContainer()->getModuleService()->getTitle($module);

				$flash->appendWarning(t('module_not_allowed_in_slot', $moduleName, $slotTitle));
				return false;
			}
		}

		// Daten einlesen
		$slicedata = array('SAVE' => true);

		if ($function != 'delete') {
			$slicedata = $this->getRequestValues($slicedata);
		}

		// ----- PRE SAVE EVENT [ADD/EDIT/DELETE]
		$eventparams = array('module' => $module, 'article_id' => $this->article->getId(), 'clang' => $this->article->getClang());
		$slicedata   = $this->getContainer()->getDispatcher()->filter('SLY_SLICE_PRESAVE_'.strtoupper($function), $slicedata, $eventparams);

		// don't save
		if (!$slicedata['SAVE']) {
			if ($this->action == 'deleteArticleSlice') {
				$flash->appendWarning(t('cannot_delete_slice'));
			}
			else {
				$flash->prependWarning(t('cannot_update_slice'), false);
			}
		}

		return $slicedata;
	}

	private function postSliceEdit($function, $articleSliceId) {
		$user       = sly_Util_User::getCurrentUser();
		$flash      = $this->getFlashMessage();
		$dispatcher = $this->getContainer()->getDispatcher();

		$dispatcher->notify('SLY_SLICE_POSTSAVE_'.strtoupper($function), $articleSliceId);
		$dispatcher->notify('SLY_CONTENT_UPDATED', $this->article, array('article_id' => $this->article->getId(), 'clang' => $this->article->getClang()));
	}

	private function getRequestValues(array $slicedata) {
		$slicedata['VALUES'] = $this->getRequest()->post('slicevalue', 'array', array());
		return $slicedata;
	}

	protected function redirectToArticle($anchor, sly_Model_ArticleSlice $slice = null, $edit = false) {
		$art     = $slice ? $slice->getArticle() : $this->article;
		$artID   = $art->getId();
		$clang   = $art->getClang();
		$rev     = $art->getRevision();
		$params  = array('article_id' => $artID, 'clang' => $clang, 'slot' => $this->slot, 'revision' => $rev);

		if ($edit) {
			$params['slice_id'] = $slice->getId();
			$params['pos']      = $slice->getPosition();
			$params['function'] = 'edit';
			$anchor             = '#editslice';
		}
		elseif ($slice) {
			$params['pos'] = $slice->getPosition();
		}

		$params  = http_build_query($params, '', '&');
		$params .= $anchor;

		return $this->redirectResponse($params);
	}
}
