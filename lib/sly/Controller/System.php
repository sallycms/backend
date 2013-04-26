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

		$container = $this->getContainer();

		// it's not perfect, but let's check whether the setup app actually
		// exists before showing the 'Setup' button inside the form.
		$hasSetupApp = is_dir(SLY_SALLYFOLDER.'/setup');
		$locales             = $this->getBackendLocales();
		$languages           = sly_Util_Language::findAll();
		$database            = $container->getConfig()->get('database');
		$database['version'] = $container->getPersistence()->getPDO()->getAttribute(PDO::ATTR_SERVER_VERSION);
		$types               = array('' => t('no_articletype'));

		try {
			$typeService = $this->getContainer()->getArticleTypeService();
			$types       = array_merge($types, $typeService->getArticleTypes());
		}
		catch (Exception $e) {
			// pass...
		}

		$this->render('system/index.phtml', compact('hasSetupApp', 'locales', 'languages', 'database', 'types'), false);
	}

	public function clearcacheAction() {
		$this->init();

		$container = $this->getContainer();

		// do not call sly_Core::clearCache(), since we want to have fine-grained
		// control over what caches get cleared
		clearstatcache();

		// clear our own data caches
		if ($this->isCacheSelected('sly_core')) {
			sly_Core::cache()->flush('sly', true);
		}

		// sync develop files
		if ($this->isCacheSelected('sly_develop')) {
			$container->getTemplateService()->refresh();
			$container->getModuleService()->refresh();
		}

		// re-initialize assets of all installed addOns
		if ($this->isCacheSelected('sly_reinit_addons')) {
			$addonService = $this->getContainer()->getAddOnService();
			$addonMngr    = $this->getContainer()->getAddOnManagerService();
			$addOns       = $addonService->getInstalledAddOns();

			foreach ($addOns as $addOn) {
				$addonMngr->copyAssets($addOn);
			}
		}

		// clear asset cache (force this if the assets have been re-initialized)
		if ($this->isCacheSelected('sly_asset') || $this->isCacheSelected('sly_reinit_addons')) {
			$this->getContainer()->getAssetService()->clearCache();
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
			'start_article_id', 'notfound_article_id', 'default_clang_id', 'default_article_type',
			'environment', 'default_locale', 'projectname', 'caching_strategy', 'timezone'
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
			$conf->set('start_article_id', $startArticle);
		}
		elseif ($startArticle > 0) {
			$flash->appendWarning(t('invalid_start_article_selected'));
		}

		if (sly_Util_Article::exists($notFoundArticle)) {
			$conf->set('notfound_article_id', $notFoundArticle);
		}
		elseif ($notFoundArticle > 0) {
			$flash->appendWarning(t('invalid_not_found_article_selected'));
		}

		if (sly_Util_Language::exists($defaultClang)) {
			$conf->set('default_clang_id', $defaultClang);
		}
		else {
			$flash->appendWarning(t('invalid_default_language_selected'));
		}

		// Standard-Artikeltyp

		try {
			$service = $this->getContainer()->getArticleTypeService();

			if (!empty($defaultType) && !$service->exists($defaultType)) {
				$flash->appendWarning(t('invalid_default_articletype_selected'));
			}
			else {
				$conf->set('default_article_type', $defaultType);
			}
		}
		catch (Exception $e) {
			$conf->set('default_article_type', '');
		}

		// caching strategy
		$strategies = sly_Cache::getAvailableCacheImpls();

		if (!isset($strategies[$cachingStrategy])) {
			$flash->appendWarning(t('invalid_caching_strategy_selected'));
		}
		elseif ($cachingStrategy !== $originals['caching_strategy']) {
			$conf->set('caching_strategy', $cachingStrategy);

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
			$conf->set('timezone', $timezone);
		}

		// backend default locale
		$locales = sly_I18N::getLocales(SLY_SALLYFOLDER.'/backend/lang');

		if (!in_array($backendLocale, $locales)) {
			$flash->appendWarning(t('invalid_locale_selected'));
		}
		else {
			$conf->set('default_locale', $backendLocale);
		}

		// misc
		$conf->set('environment', $developerMode ? 'dev' : 'prod');
		$conf->set('projectname', $projectName);
		$conf->store();
		// notify system
		sly_Core::dispatcher()->notify('SLY_SETTINGS_UPDATED', null, compact('originals'));

		return $this->redirectResponse();
	}

	public function setupAction() {
		$this->init();
		sly_Core::config()->set('setup', true)->store();
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
