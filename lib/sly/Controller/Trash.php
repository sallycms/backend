<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Trash extends sly_Controller_Backend implements sly_Controller_Interface {

	public function indexAction() {
		// load our i18n stuff
		sly_Core::getI18N()->appendFile(SLY_SALLYFOLDER.'/backend/lang/pages/trash/');

		$this->getContainer()->getLayout()->pageHeader(t('trash'), '');
		$articles = $this->getContainer()->getDeletedArticleService()->findLatest(array('clang' => sly_Core::getDefaultClangId()));
		print $this->render('trash/article_table.phtml', array('articles' => $articles));
	}

	public function restoreAction() {
		$id    = $this->getRequest()->post('id', 'int');
		$flash = $this->getContainer()->getFlashMessage();
		try {
			$this->getContainer()->getDeletedArticleService()->restore($id);
			$flash->prependInfo(t('article_restored'), true);
			return $this->redirectResponse(array('article_id' => $id, 'clang' => sly_Core::getDefaultClangId()), 'content', 'index');
		} catch(sly_Exception $e) {
			$flash->prependWarning($e->getMessage(), true);
			return $this->redirectResponse(array(), 'trash', 'index');
		}
	}

	public function checkPermission($action) {
		return true;
	}

}