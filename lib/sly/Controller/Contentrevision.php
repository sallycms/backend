<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Contentrevision extends sly_Controller_Content_Base {
	public function indexAction() {
		$this->init();

		if ($this->header() !== true) return;

		// @edge unused variable
		// $post        = $this->getRequest()->post;
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

		$this->render('content/revision/index.phtml', array(
			'article'     => $art,
			'revisions'   => $revisions,
			'userService' => $userService,
			'total'       => $total
		), false);
	}

	public function comparerevisionAction() {
		$this->init();

		if ($this->header() !== true) return;

		$flash     = $this->getFlashMessage();
		$container = $this->getContainer();

		$request     = $container['sly-request'];
		$revisions   = $request->post('revisions', 'array', array());
		$comparisons = array();

		$templateService = $container['sly-service-template'];
		$templateName    = $this->article->getTemplateName();
		$templateSlots   = $templateService->getSlots($templateName);

		try {
			foreach ($templateSlots as $slotKey) {
				$slotName = sly_translate($templateService->getSlotTitle($templateName, $slotKey), true);
				$diff     = $this->compareRevisions($revisions, $slotKey);

				$comparison = new sly_Diff_Comparison();
				$comparison->setSlotKey($slotKey);
				$comparison->setSlotName($slotName);
				$comparison->setDiff($diff);
				$comparison->setRevA($revisions[1]);
				$comparison->setRevB($revisions[0]);

				$comparisons[] = $comparison;
			}

			$this->render('content/revision/compare.phtml', array(
				'comparisons' => $comparisons
			), false);
		}
		catch (Exception $e) {
			$flash->appendWarning($e->getMessage());

			$this->redirectToArticle();
		}
	}

	protected function getViewFolder() {
		return SLY_SALLYFOLDER.'/backend/views/';
	}

	protected function getPageName() {
		return 'contentrevision';
	}

	public function checkPermission($action) {
		$hasPermission = parent::checkPermission($action);
		$request       = $this->getRequest();

		if ($action === 'deleterevision') {
			$user          = $this->getCurrentUser();
			$articleId     = $request->request('article_id', 'int', 0);
			$hasPermission = sly_Backend_Authorisation_Util::canEditArticle($user, $articleId);
		}

		if ($request->isMethod('POST')) {
			sly_Util_Csrf::checkToken();
		}

		return $hasPermission;
	}

	public function deleterevisionAction() {
		// @edge add multiple purging of revisions

		$this->init();

		$flash = $this->getFlashMessage();

		try {
			$this->getContainer()->getArticleService()->purgeArticleRevision($this->article);
			$flash->appendInfo(t('article_revision_deleted'));
		} catch (Exception $e) {
			$flash->appendWarning(t('cannont_delete_article_revision'));
		}

		$this->redirectToArticle();
	}

	protected function redirectToArticle() {
		$artID   = $this->article->getId();
		$clang   = $this->article->getClang();
		$params  = array('article_id' => $artID, 'clang' => $clang);

		return $this->redirectResponse($params);
	}

	protected function compareRevisions(array $revisions, $slot) {
		// we need exactly two revisions for comparison

		if (count($revisions) !== 2) {
			throw new sly_Exception('Please select at least 2 revisions for comparison.');
		}

		// sort revisions

		sort($revisions);

		// obtain content of revisions

		$a = $this->getContainer()->getArticleService()->findByPK($this->article->getId(), $this->article->getClang(), $revisions[0]);
		$b = $this->getContainer()->getArticleService()->findByPK($this->article->getId(), $this->article->getClang(), $revisions[1]);

		if ($a->getTemplateName() !== $b->getTemplateName()) {
			throw new sly_Exception('Please select at least 2 revisions of the same article type.');
		}

		$a = array_filter(explode("\n", $a->getContent($slot)));
		$b = array_filter(explode("\n", $b->getContent($slot)));

		// compare revisions contents

		$diff     = new Diff($a, $b, $options = array());
		$renderer = new sly_Diff_Renderer();

		// return comparison

		return $diff->Render($renderer);
	}
}
