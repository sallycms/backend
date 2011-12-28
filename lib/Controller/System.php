<?php
/*
 * Copyright (c) 2011, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System extends sly_Controller_Backend {
	protected $warning;
	protected $info;

	public function init() {
		// add subpages

		$navigation = sly_Core::getLayout()->getNavigation();
		$system     = $navigation->get('system', 'system');

		// show error log when using the original system error handler
		// (that's the only case when we can ensure that we're able to parse the error log)

		$subline = array();
		foreach ($system->getSubpages() as $subpage) {
			$subline[] = array($subpage->getPageParam(), $subpage->getTitle());
		}

		$layout = sly_Core::getLayout();
		$layout->pageHeader(t('system'), $subline);
	}

	public function indexAction() {
		print $this->render('system/index.phtml');
	}

	public function clearcacheAction() {
		$this->info = sly_Core::clearCache();
		$this->indexAction();
	}

	public function updateAction() {
		$startArticle    = sly_post('start_article',    'int');
		$notFoundArticle = sly_post('notfound_article', 'int');
		$defaultClang    = sly_post('default_clang',    'int');
		$defaultType     = sly_post('default_type',     'string');
		$developerMode   = sly_post('developer_mode',   'string');
		$backendLocale   = sly_post('backend_locale',   'string');
		$projectName     = sly_post('projectname',      'string');
		$cachingStrategy = sly_post('caching_strategy', 'string');
		$timezone        = sly_post('timezone',         'string');

		// Änderungen speichern

		$conf = sly_Core::config();
		$this->warning = array();

		if (sly_Util_Article::exists($startArticle)) {
			$conf->set('START_ARTICLE_ID', $startArticle);
		}
		else {
			$this->warning[] = t('invalid_start_article_selected');
		}

		if (sly_Util_Article::exists($notFoundArticle)) {
			$conf->set('NOTFOUND_ARTICLE_ID', $notFoundArticle);
		}
		else {
			$this->warning[] = t('invalid_not_found_article_selected').'<br />';
		}

		if (sly_Util_Language::exists($defaultClang)) {
			$conf->set('DEFAULT_CLANG_ID', $defaultClang);
		}
		else {
			$this->warning[] = t('invalid_default_language_selected').'<br />';
		}

		// Standard-Artikeltyp

		try {
			$service = sly_Service_Factory::getArticleTypeService();

			if (!empty($defaultType) && !$service->exists($defaultType)) {
				$this->warning[] = t('invalid_default_articletype_selected').'<br />';
			}
			else {
				$conf->set('DEFAULT_ARTICLE_TYPE', $defaultType);
			}
		}
		catch (Exception $e) {
			$conf->set('DEFAULT_ARTICLE_TYPE', '');
		}

		// Sonstige Einstellungen

		$conf->set('DEVELOPER_MODE', $developerMode === 'true');
		$conf->set('DEFAULT_LOCALE', $backendLocale);
		$conf->set('PROJECTNAME', $projectName);
		$conf->setLocal('CACHING_STRATEGY', $cachingStrategy);
		$conf->set('TIMEZONE', $timezone);

		$this->info    = t('configuration_updated');
		$this->warning = implode("<br />\n", $this->warning);

		// notify system
		sly_Core::dispatcher()->notify('SLY_SETTINGS_UPDATED');

		$this->indexAction();
	}

	public function setupAction() {
		sly_Core::config()->setLocal('SETUP', true);
		sly_Util_HTTP::redirect('index.php', array(), '', 302);
	}

	public function checkPermission() {
		$user = sly_Util_User::getCurrentUser();
		return $user && $user->isAdmin();
	}
}
