<?php
/*
 * Copyright (c) 2015, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Preview extends sly_Controller_Backend implements sly_Controller_Interface {

	public function indexAction() {
		$container  = $this->getContainer();
		$request    = $container->getRequest();
		$response   = $container->getResponse();
		$dispatcher = $container->getDispatcher();
		$articleID  = $request->get('article_id', 'int');
		$clangID    = $request->get('clang', 'int');
		$revision   = $request->get('revision', 'int');
		$article    = $container->getArticleService()->findByPK($articleID, $clangID, $revision);

		if (!$article) {
			throw new Exception(t('article_not_found', $articleID));
		}

		if (!$article->getTemplateName()) {
			throw new Exception(t('no_template_set'));
		}

		// preserve the backend layout
		$backendLayout = $container->getLayout();

		try {
			// hide backend
			$container->getApplication()->setIsBackend(false);

			// set needed variables for frontend code
			$container->setCurrentArticleId($article->getId());
			$container->setCurrentLanguageId($article->getClang());
			$container->setCurrentArticleRevision($article->getRevision());

			// add frontend translations and set language by clang param
			$i18n = $container['sly-i18n'];
			$i18n->setLocale(strtolower(sly_Util_Language::getLocale()));
			$i18n->appendFile(SLY_DEVELOPFOLDER.'/lang');

			// notify listeners about the article to be rendered
			$container['sly-dispatcher']->notify('SLY_CURRENT_ARTICLE', $article);

			$output = $article->getArticleTemplate();

			// article postprocessing is a special task, so here's a special event
			$output = $dispatcher->filter('SLY_ARTICLE_OUTPUT', $output, compact('article'));
		}
		catch (Exception $e) {
			// if anything goes wrong, restore
			$container->setLayout($backendLayout);

			throw $e;
		}

		$container->getApplication()->setIsBackend(true);

		$response->setContent($output);

		return $response;
	}

	public function checkPermission($action) {
		$request   = $this->getRequest();
		$articleID = $request->get('article_id', 'int');
		$user      = $this->getCurrentUser();

		return sly_Backend_Authorisation_Util::canReadArticle($user, $articleID);
	}
}
