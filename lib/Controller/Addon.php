<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Addon extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $func    = '';
	protected $service = null;
	protected $comp    = null;
	protected $info    = '';
	protected $warning = '';

	private $init = 0;

	protected function init() {
		if ($this->init++) return;

		if (!sly_get('json', 'boolean')) {
			$layout = sly_Core::getLayout();
			$layout->pageHeader(t('addons'));
		}

		$package       = sly_request('package', 'string', '');
		$this->service = sly_Service_Factory::getPackageService();
		$this->pkg     = $this->service->isRegistered($package) ? $package : null;
	}

	public function indexAction() {
		$this->init();

		$data = $this->buildDataList();
		$data = $this->resolveParentRelationships($data);

		print $this->render('addon/list.phtml', array(
			'service' => $this->service,
			'tree'    => $data,
			'stati'   => $this->buildStatusList($data),
			'info'    => $this->info,
			'warning' => $this->warning
		));
	}

	public function installAction() {
		$this->init();
		$this->call('install', 'installed');

		if ($this->warning === '') {
			$this->call('activate', 'activated');
		}

		return $this->sendResponse();
	}

	public function uninstallAction()  { $this->init(); $this->call('uninstall', 'uninstalled');    return $this->sendResponse(); }
	public function activateAction()   { $this->init(); $this->call('activate', 'activated');       return $this->sendResponse(); }
	public function deactivateAction() { $this->init(); $this->call('deactivate', 'deactivated');   return $this->sendResponse(); }
	public function reinitAction()     { $this->init(); $this->call('copyAssets', 'assets_copied'); return $this->sendResponse(); }

	public function fullinstallAction() {
		$this->init();

		$todo = $this->getInstallList($this->pkg);

		if (!empty($todo)) {
			// pretend that we're about to work on this now
			$this->pkg = reset($todo);

			// if not installed, install it
			if (!$this->service->isInstalled($this->pkg)) {
				$this->call('install', 'installed');
			}

			// if not activated and install went OK, activate it
			if (!$this->service->isAvailable($this->pkg) && $this->warning === '') {
				$this->call('activate', 'activated');
			}

			// if everything worked out fine, we can redirect to the next component
			if ($this->warning === '' && count($todo) > 1) {
				sly_Util_HTTP::redirect($_SERVER['REQUEST_URI'], array(), '', 302);
			}
		}

		return $this->sendResponse();
	}

	public function checkPermission($action) {
		$user = sly_Util_User::getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('pages', 'addons'));
	}

	protected function call($method, $i18n) {
		$this->warning = $this->service->$method($this->pkg);

		if ($this->warning === true || $this->warning === 1) {
			$this->info    = t('component_'.$i18n, $this->pkg);
			$this->warning = '';
		}
	}

	private function sendResponse() {
		if (sly_get('json', 'boolean')) {
			header('Content-Type: application/json; charset=UTF-8');
			while (ob_get_level()) ob_end_clean();
			ob_start('ob_gzhandler');

			$data = $this->buildDataList();
			$data = $this->resolveParentRelationships($data);

			$response = array(
				'status'  => !empty($this->info),
				'stati'   => $this->buildStatusList($data),
				'message' => $this->warning
			);

			print json_encode($response);
			die;
		}

		return $this->indexAction();
	}

	/**
	 * @param  string $package
	 * @return array
	 */
	private function getPackageDetails($package) {
		static $reqCache = array();
		static $depCache = array();

		$service = $this->service;
		$key     = $package;

		if (!isset($reqCache[$key])) {
			$reqCache[$key] = $service->getRequirements($package);
			$depCache[$key] = $service->getDependencies($package);
		}

		$requirements = $reqCache[$key];
		$dependencies = $depCache[$key];
		$missing      = array();
		$required     = $service->isRequired($package) !== false;
		$installed    = $service->isInstalled($package);
		$activated    = $installed ? $service->isActivated($package) : false;
		$compatible   = $service->isCompatible($package);
		$version      = $service->getVersion($package);
		$parent       = $service->getParent($package);
		$author       = sly_Helper_Package::getSupportPage($package);
		$usable       = $compatible ? $this->canBeUsed($package) : false;

		if ($parent !== null) {
			// do not allow to nest more than one level
			$exists   = $service->exists($parent);
			$hasGrand = $exists ? $service->getParent($parent) : false;

			if (!$exists || $hasGrand) {
				$parent = null;
			}
			else {
				$requirements[] = $parent;
				$requirements   = array_unique($requirements);
			}
		}

		foreach ($requirements as $req) {
			if (!$service->isAvailable($req)) $missing[] = $req;
		}

		return compact('key', 'requirements', 'dependencies', 'missing', 'required', 'installed', 'activated', 'compatible', 'usable', 'version', 'author', 'parent');
	}

	/**
	 * Check whether a package can be used
	 *
	 * To make this method return true, all required packages must be present,
	 * compatible and themselves be usable.
	 *
	 * @param  string $package
	 * @return boolean
	 */
	private function canBeUsed($package) {
		if (!$this->service->exists($package))       return false;
		if (!$this->service->isCompatible($package)) return false;

		$requirements = $this->service->getRequirements($package);

		foreach ($requirements as $requirement) {
			if (!$this->canBeUsed($requirement)) return false;
		}

		return true;
	}

	/**
	 * Determine what components to install
	 *
	 * This method will walk through all requirements and collect a list of
	 * components that need to be installed to install the $package. The list
	 * is ordered ($package is always the last element). Already activated
	 * components will not be included (so the result can be empty if $package
	 * is also already activated).
	 *
	 * @param  string $package  package name
	 * @param  array  $list     current stack (used internally)
	 * @return array            install list
	 */
	private function getInstallList($package, array $list = array()) {
		$idx          = array_search($package, $list);
		$requirements = $this->service->getRequirements($package);

		if ($idx !== false) {
			unset($list[$idx]);
			$list = array_values($list);
		}

		if (!$this->service->isAvailable($component)) {
			array_unshift($list, $component);
		}

		foreach ($requirements as $requirement) {
			$list = $this->getInstallList($requirement, $list);
		}

		return $list;
	}

	private function buildDataList() {
		$result = array();

		foreach ($this->service->getRegisteredPackages() as $package) {
			$result[$package] = $this->getPackageDetails($package);
		}

		return $result;
	}

	private function buildStatusList(array $dataList) {
		$result = array();

		foreach ($dataList as $addon => $aInfo) {
			$classes = array('sly-addon');

			// build class list for all relevant stati

			if (!empty($aInfo['components'])) {
				$classes[] = 'p1';

				foreach ($aInfo['components'] as $pInfo) {
					if ($pInfo['activated']) {
						$classes[] = 'pa1';
						$classes[] = 'd1';  // assume implicit dependency of components from their parent components
						break;
					}
				}
			}
			else {
				$classes[] = 'p0';
			}

			if (!in_array('pa1', $classes)) {
				$classes[] = 'd'.intval($aInfo['required']);
			}
			else {
				$classes[] = 'pa0';
			}

			$classes[] = 'i'.intval($aInfo['installed']);
			$classes[] = 'a'.intval($aInfo['activated']);
			$classes[] = 'c'.intval($aInfo['compatible']);
			$classes[] = 'r'.intval($aInfo['requirements']);
			$classes[] = 'ro'.(empty($aInfo['missing']) ? 1 : 0);
			$classes[] = 'u'.intval($aInfo['usable']);

			$result[$addon] = array(
				'classes' => implode(' ', $classes),
				'deps'    => $this->buildDepsInfo($aInfo)
			);

			foreach ($aInfo['components'] as $plugin => $pInfo) {
				$key     = $pInfo['key'];
				$classes = array('sly-plugin');

				$pInfo['requirements'][] = $addon;
				$pInfo['requirements'] = array_unique($pInfo['requirements']);

				$classes[] = 'i'.intval($pInfo['installed']);
				$classes[] = 'a'.intval($pInfo['activated']);
				$classes[] = 'd'.intval($pInfo['required']);
				$classes[] = 'c'.intval($pInfo['compatible']);
				$classes[] = 'r'.intval($pInfo['requirements']);
				$classes[] = 'ro'.(empty($pInfo['missing']) ? 1 : 0);
				$classes[] = 'u'.intval($pInfo['usable']);

				$result[$key] = array(
					'classes' => implode(' ', $classes),
					'deps'    => $this->buildDepsInfo($pInfo)
				);
			}
		}

		return $result;
	}

	private function buildDepsInfo(array $info) {
		if ($info['required']) {
			$names = array();

			foreach ($info['dependencies'] as $pkg) {
				$names[] = str_replace('/', ' / ', $pkg);
			}

			$isRequiredTitle = sly_html(t('is_required', sly_Util_String::humanImplode(array_slice($names, 0, 3))));
		}
		else {
			$isRequiredTitle = '';
		}

		if ($info['requirements']) {
			$names = array();

			foreach ($info['requirements'] as $pkg) {
				$names[] = str_replace('/', ' / ', $pkg);
			}

			$requiresTitle = t('requires').' '.sly_Util_String::humanImplode(array_slice($names, 0, 3));
		}
		else {
			$requiresTitle = '';
		}

		$texts = array_filter(array($requiresTitle, $isRequiredTitle));
		if (empty($texts)) $texts[] = t('no_dependencies');
		return implode(' &amp; ', $texts);
	}

	private function resolveParentRelationships(array $data) {
		do {
			$changes = false;

			foreach ($data as $addon => $info) {
				if ($info['parent']) {
					$data[$info['parent']]['components'][$addon] = $info;
					unset($data[$addon]);
					$changes = true;
					break;
				}
			}
		} while ($changes);

		return $data;
	}
}
