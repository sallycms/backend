<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
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

		$service = sly_Service_Factory::getArticleTypeService();
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
					$sliceservice = sly_Service_Factory::getArticleSliceService();
					$slice_id     = $request->post('slice_id', 'int', 0);
					$slice        = $sliceservice->findById($slice_id);
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

		$type    = $this->getRequest()->post('article_type', 'string', '');
		$service = sly_Service_Factory::getArticleTypeService();

		if (!empty($type) && $service->exists($type, true)) {
			$service = sly_Service_Factory::getArticleService();
			$flash   = sly_Core::getFlashMessage();

			// change type and update database
			$service->setType($this->article, $type);
			$flash->appendInfo(t('article_updated'));
		}

		return $this->redirectToArticle('', null);
	}

	public function movesliceAction() {
		$this->init();

		$request   = $this->getRequest();
		$slice_id  = $request->post('slice_id', 'int');
		$direction = $request->post('direction', 'string');
		$flash     = sly_Core::getFlashMessage();

		// check if module exists
		$module = sly_Util_ArticleSlice::getModule($slice_id);

		if (!$module) {
			$flash->appendWarning(t('module_not_found'));
		}
		else {
			$user = sly_Util_User::getCurrentUser();

			// check permission
			if (sly_Util_ArticleSlice::canMoveSlice($user, $slice_id)) {
				$success = sly_Service_Factory::getArticleSliceService()->move($slice_id, $direction);

				if ($success) {
					$flash->appendInfo(t('slice_moved'));
				}
				else {
					$flash->appendWarning(t('cannot_move_slice'));
				}
			}
			else {
				$flash->appendWarning(t('no_rights_to_this_module'));
			}
		}

		return $this->redirectToArticle('#messages', sly_Util_ArticleSlice::findById($slice_id));
	}

	public function addarticlesliceAction() {
		$this->init();

		$request   = $this->getRequest();
		$module    = $request->post('module', 'string');
		$params    = array();
		$slicedata = $this->preSliceEdit('add');
		$flash     = sly_Core::getFlashMessage();

		if ($slicedata['SAVE'] === true) {
			$service  = sly_Service_Factory::getArticleSliceService();
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
		$articleSliceService = sly_Service_Factory::getArticleSliceService();
		$sliceService        = sly_Service_Factory::getSliceService();
		$slice_id            = $request->post('slice_id', 'int', 0);
		$articleSlice        = $articleSliceService->findById($slice_id);
		$flash               = sly_Core::getFlashMessage();

		if (!$articleSlice) {
			$flash->appendWarning(t('slice_not_found', $slice_id));
		}
		else {
			$slicedata = $this->preSliceEdit('edit');

			if ($slicedata['SAVE'] === true) {
				$slice = $articleSlice->getSlice();
				$slice->setValues($slicedata['VALUES']);
				$sliceService->save($slice);

				$articleSlice->setUpdateColumns();
				$articleSliceService->save($articleSlice);

				$flash->appendInfo(t('slice_updated'));
				$this->postSliceEdit('edit', $slice_id);

				$apply = sly_post('btn_update', 'string') !== null;
				return $this->redirectToArticle('#messages', $articleSlice, $apply);
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
		$flash   = sly_Core::getFlashMessage();

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
			$ok = sly_Util_ArticleSlice::deleteById($sliceID);
		}

		if ($ok) {
			$flash->appendInfo(t('slice_deleted'));
			$this->postSliceEdit('delete', $sliceID);
		}
		else {
			$flash->appendWarning(t('cannot_delete_slice'));
		}

		return $this->redirectToArticle('#messages', $slice);
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

		$flash = sly_Core::getFlashMessage();

		if ($function !== 'delete') {
			if (!sly_Service_Factory::getModuleService()->exists($module)) {
				$flash->appendWarning(t('module_not_found'));
				return false;
			}

			if (!sly_Service_Factory::getArticleTypeService()->hasModule($this->article->getType(), $module, $this->slot)) {
				$slotTitle  = $templateService->getSlotTitle($templateName, $this->slot);
				$moduleName = sly_Service_Factory::getModuleService()->getTitle($module);

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
		$slicedata   = sly_Core::dispatcher()->filter('SLY_SLICE_PRESAVE_'.strtoupper($function), $slicedata, $eventparams);

		// don't save
		if (!$slicedata['SAVE']) {
			if ($this->action == 'deleteArticleSlice') {
				$flash->appendWarning(t('cannot_delete_slice'));
			}
			else {
				$flash->prependWarning(t('cannot_update_slice'));
			}
		}

		return $slicedata;
	}

	private function postSliceEdit($function, $articleSliceId) {
		$user       = sly_Util_User::getCurrentUser();
		$flash      = sly_Core::getFlashMessage();
		$dispatcher = sly_Core::dispatcher();

		sly_Service_Factory::getArticleService()->touch($this->article, $user);

		$dispatcher->notify('SLY_SLICE_POSTSAVE_'.strtoupper($function), $articleSliceId);
		$dispatcher->notify('SLY_CONTENT_UPDATED', $this->article, array('article_id' => $this->article->getId(), 'clang' => $this->article->getClang()));
	}

	private function getRequestValues(array $slicedata) {
		$slicedata['VALUES'] = $this->getRequest()->post('slicevalue', 'array', array());
		return $slicedata;
	}

	protected function redirectToArticle($anchor, sly_Model_ArticleSlice $slice = null, $edit = false) {
		$artID   = $this->article->getId();
		$clang   = $this->article->getClang();
		$params  = array('article_id' => $artID, 'clang' => $clang, 'slot' => $this->slot);

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
