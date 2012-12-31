<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $init;

	protected function init() {
		if ($this->init) return;
		$this->init = true;

		$layout = sly_Core::getLayout();
		$layout->pageHeader(t('system'));
	}

	public function indexAction() {
		$this->init();
		$this->render('system/index.phtml', array(), false);
	}

	public function clearcacheAction() {
		$this->init();

		// do not call sly_Core::clearCache(), since we want to have fine-grained
		// control over what caches get cleared
		clearstatcache();

		// clear loader cache
		sly_Loader::clearCache();

		// create bootcache
		sly_Util_BootCache::recreate();

		// clear our own data caches
		if ($this->isCacheSelected('sly_core')) {
			sly_Core::cache()->flush('sly', true);
		}

		// sync develop files
		if ($this->isCacheSelected('sly_develop')) {
			sly_Service_Factory::getTemplateService()->refresh();
			sly_Service_Factory::getModuleService()->refresh();
		}

		// clear asset cache
		if ($this->isCacheSelected('sly_asset')) {
			sly_Service_Factory::getAssetService()->clearCache();
		}

		sly_Core::getFlashMessage()->addInfo(t('delete_cache_message'));
		sly_Core::dispatcher()->notify('SLY_CACHE_CLEARED', null, array('backend' => true));

		$this->indexAction();
	}

	public function isCacheSelected($name) {
		$caches = $this->getRequest()->postArray('caches', 'string');
		return in_array($name, $caches);
	}

	public function updateAction() {
		$this->init();

		$request         = $this->getRequest();
		$startArticle    = $request->post('start_article',    'int');
		$notFoundArticle = $request->post('notfound_article', 'int');
		$defaultClang    = $request->post('default_clang',    'int');
		$defaultType     = $request->post('default_type',     'string');
		$developerMode   = $request->post('developer_mode',   'boolean', false);
		$backendLocale   = $request->post('backend_locale',   'string');
		$projectName     = $request->post('projectname',      'string');
		$cachingStrategy = $request->post('caching_strategy', 'string');
		$timezone        = $request->post('timezone',         'string');

		$keys = array(
			'START_ARTICLE_ID', 'NOTFOUND_ARTICLE_ID', 'DEFAULT_CLANG_ID', 'DEFAULT_ARTICLE_TYPE',
			'DEVELOPER_MODE', 'DEFAULT_LOCALE', 'PROJECTNAME', 'CACHING_STRATEGY', 'TIMEZONE'
		);

		// Änderungen speichern

		$container = $this->getContainer();
		$conf      = $container->getConfig();
		$flash     = $container->getFlashMessage();

		$flash->appendInfo(t('configuration_updated'));

		foreach ($keys as $key) {
			$originals[$key] = $conf->get($key);
		}

		if (sly_Util_Article::exists($startArticle)) {
			$conf->set('START_ARTICLE_ID', $startArticle);
		}
		elseif ($startArticle > 0) {
			$flash->appendWarning(t('invalid_start_article_selected'));
		}

		if (sly_Util_Article::exists($notFoundArticle)) {
			$conf->set('NOTFOUND_ARTICLE_ID', $notFoundArticle);
		}
		elseif ($notFoundArticle > 0) {
			$flash->appendWarning(t('invalid_not_found_article_selected'));
		}

		if (sly_Util_Language::exists($defaultClang)) {
			$conf->set('DEFAULT_CLANG_ID', $defaultClang);
		}
		else {
			$flash->appendWarning(t('invalid_default_language_selected'));
		}

		// Standard-Artikeltyp

		try {
			$service = sly_Service_Factory::getArticleTypeService();

			if (!empty($defaultType) && !$service->exists($defaultType)) {
				$flash->appendWarning(t('invalid_default_articletype_selected'));
			}
			else {
				$conf->set('DEFAULT_ARTICLE_TYPE', $defaultType);
			}
		}
		catch (Exception $e) {
			$conf->set('DEFAULT_ARTICLE_TYPE', '');
		}

		// caching strategy
		$strategies = sly_Cache::getAvailableCacheImpls();

		if (!isset($strategies[$cachingStrategy])) {
			$flash->appendWarning(t('invalid_caching_strategy_selected'));
		}
		elseif ($cachingStrategy !== $originals['CACHING_STRATEGY']) {
			$conf->setLocal('CACHING_STRATEGY', $cachingStrategy);

			// make the container create a fresh cache instance once the next
			// code requires the cache :-)
			$container['sly-cache'] = array($container, 'buildCache');

			// clear cache if different one was selected
			// important in case we re-use an existing cache that has something
			// like a broken addOn load order stored
			$cache = $container->getCache();
			$cache->flush('sly');
		}

		// timezone
		if (!in_array($timezone, DateTimeZone::listIdentifiers())) {
			$flash->appendWarning(t('invalid_timezone_selected'));
		}
		else {
			$conf->set('TIMEZONE', $timezone);
		}

		// backend default locale
		$locales = sly_I18N::getLocales(SLY_SALLYFOLDER.'/backend/lang');

		if (!in_array($backendLocale, $locales)) {
			$flash->appendWarning(t('invalid_locale_selected'));
		}
		else {
			$conf->set('DEFAULT_LOCALE', $backendLocale);
		}

		// misc
		$conf->setLocal('DEVELOPER_MODE', $developerMode);
		$conf->set('PROJECTNAME', $projectName);

		// notify system
		sly_Core::dispatcher()->notify('SLY_SETTINGS_UPDATED', null, compact('originals'));

		// create/remove bootcache
		sly_Util_BootCache::recreate();

		return $this->redirectResponse();
	}

	public function setupAction() {
		$this->init();
		sly_Core::config()->setLocal('SETUP', true);
		$this->redirect(array(), '');
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		if (!$user) return false;

		if (in_array($action, array('setup', 'update', 'clearcache'))) {
			sly_Util_Csrf::checkToken();
		}

		return $user && $user->isAdmin();
	}

	protected function getBackendLocales() {
		$langpath = SLY_SALLYFOLDER.'/backend/lang';
		$locales  = sly_I18N::getLocales($langpath);
		$result   = array();

		foreach ($locales as $locale) {
			$i18n = new sly_I18N($locale, $langpath, false);
			$result[$locale] = $i18n->msg('lang');
		}

		return $result;
	}
}
