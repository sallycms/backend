<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
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

		$layout = $this->getContainer()->getLayout();
		$layout->pageHeader(t('system'));
	}

	public function indexAction() {
		$this->init();

		$container   = $this->getContainer();
		$locales     = $this->getBackendLocales();
		$languages   = sly_Util_Language::findAll();
		$types       = array('' => t('no_articletype'));

		try {
			$typeService = $this->getContainer()->getArticleTypeService();
			$types       = array_merge($types, $typeService->getArticleTypes());
		}
		catch (Exception $e) {
			// pass...
		}

		$this->render('system/index.phtml', compact('locales', 'languages', 'types'), false);
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

		$container  = $this->getContainer();
		$conf       = $container->getConfig();
		$flash      = $container->getFlashMessage();
		$dispatcher = $container->getDispatcher();

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
			$service = $container->getArticleTypeService();

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
		$factory    = $container['sly-cache-factory'];
		$strategies = $factory->getAvailableAdapters();

		if (!isset($strategies[$cachingStrategy])) {
			$flash->appendWarning(t('invalid_caching_strategy_selected'));
		}
		elseif ($cachingStrategy !== $originals['caching_strategy']) {
			$conf->set('caching_strategy', $cachingStrategy);

			// flush the old cache
			// we are no messies
			$cache = $container->getCache();
			$cache->clear('sly', true);

			// make the container create a fresh cache instance once the next
			// code requires the cache :-)
			$container['sly-cache'] = $container->share(function($container) use ($cachingStrategy) {
				return $container['sly-cache-factory']->getCache($cachingStrategy);
			});

			// clear cache if different one was selected
			// important in case we re-use an existing cache that has something
			// like a broken addOn load order stored
			$cache = $container->getCache();
			$cache->clear('sly', true);
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
		$dispatcher->notify('SLY_SETTINGS_UPDATED', null, compact('originals'));

		return $this->redirectResponse();
	}

	public function checkPermission($action) {
		$user = $this->getCurrentUser();

		if (!$user) {
			return false;
		}

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
