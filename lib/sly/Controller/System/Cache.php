<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System_Cache extends sly_Controller_System {
	public function indexAction() {
		$this->init();

		$this->render('system/cache.phtml', array(), false);
	}

	public function clearcacheAction() {
		$this->init();

		$container  = $this->getContainer();
		$dispatcher = $container->getDispatcher();

		// do not call sly_Core::clearCache(), since we want to have fine-grained
		// control over what caches get cleared
		clearstatcache();

		// clear our own data caches
		if ($this->isCacheSelected('sly_core')) {
			$container->getCache()->clear('sly', true);
		}

		// sync develop files
		if ($this->isCacheSelected('sly_develop')) {
			$container->getTemplateService()->refresh();
			$container->getModuleService()->refresh();
		}

		$this->getFlashMessage()->addInfo(t('delete_cache_message'));
		$dispatcher->notify('SLY_CACHE_CLEARED', null, array('backend' => true));

		$this->indexAction();
	}

	public function isCacheSelected($name) {
		$caches = $this->getRequest()->postArray('caches', 'string');
		return in_array($name, $caches);
	}
}
