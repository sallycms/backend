<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Addon extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $addon = false;
	private   $init  = 0;

	protected function init() {
		if ($this->init++) return;

		if (!$this->getRequest()->isAjax()) {
			$layout = $this->getContainer()->getLayout();
			$layout->pageHeader(t('addons'));
		}
	}

	/**
	 * Get current addOn
	 *
	 * @return string  the addOn or null
	 */
	protected function getAddOn() {
		if ($this->addon === false) {
			extract($this->getServices());

			$addon = $this->getRequest()->request('addon', 'string', '');

			if (empty($addon)) {
				throw new sly_Exception(t('addon_not_given'));
			}

			if (!$aservice->isRegistered($addon)) {
				throw new sly_Exception(t('addon_not_found', $addon));
			}

			$this->addon = $addon;
		}

		return $this->addon;
	}

	/**
	 * Set current addOn
	 *
	 * @param string $addon  addOn name
	 */
	protected function setAddOn($addon) {
		$this->addon = $addon;
	}

	/**
	 * Returns the commonly used services
	 *
	 * @return array  {aservice: addon-service, manager: addon-manager, pservice: package-service}
	 */
	protected function getServices() {
		return array(
			'aservice' => $this->getContainer()->getAddOnService(),
			'manager'  => $this->getContainer()->getAddOnManagerService(),
			'pservice' => $this->getContainer()->getAddOnPackageService()
		);
	}

	/**
	 * index action
	 */
	public function indexAction() {
		$this->init();

		$data = $this->buildDataList();
		$data = $this->resolveParentRelationships($data);

		$this->render('addon/list.phtml', array(
			'tree'  => $data,
			'stati' => $this->buildStatusList($data)
		), false);
	}

	public function installAction() {
		try {
			$this->call('install', 'installed', true);
			$this->call('activate', 'activated');
		}
		catch (Exception $e) {
			$this->getFlashMessage()->appendWarning($e->getMessage());
		}

		return $this->sendResponse();
	}

	public function uninstallAction() {
		try {
			$this->call('uninstall', 'uninstalled');
		}
		catch (Exception $e) {
			$this->getFlashMessage()->appendWarning($e->getMessage());
		}

		return $this->sendResponse();
	}

	public function activateAction() {
		try {
			$this->call('activate', 'activated');
		}
		catch (Exception $e) {
			$this->getFlashMessage()->appendWarning($e->getMessage());
		}

		return $this->sendResponse();
	}

	public function deactivateAction() {
		try {
			$this->call('deactivate', 'deactivated');
		}
		catch (Exception $e) {
			$this->getFlashMessage()->appendWarning($e->getMessage());
		}

		return $this->sendResponse();
	}

	public function fullinstallAction() {
		$this->init();

		try {
			$todo = $this->getInstallList($this->getAddOn());
			extract($this->getServices());

			if (!empty($todo)) {
				// pretend that we're about to work on this now
				$addon = reset($todo);
				$this->setAddOn($addon);

				// if not installed, install it
				if (!$aservice->isInstalled($addon)) {
					$this->call('install', 'installed', true);
				}

				// if not activated and install went OK, activate it
				if (!$aservice->isAvailable($addon)) {
					$this->call('activate', 'activated');
				}

				// redirect to the next addOn
				if (count($todo) > 1) {
					return $this->sendResponse(false);
				}
			}
		}
		catch (Exception $e) {
			$this->getFlashMessage()->appendWarning($e->getMessage());
		}

		return $this->sendResponse();
	}

	public function checkPermission($action) {
		$user = $this->getCurrentUser();

		if (!$user || (!$user->isAdmin() && !$user->hasRight('pages', 'addons'))) {
			return false;
		}

		return true;
	}

	protected function call($method, $i18n, $silent = false) {
		extract($this->getServices());

		$addon       = $this->getAddOn();
		$container   = $this->getContainer();
		$persistence = $container->getPersistence();

		// check token here instead of in checkPermission() to handle the exception
		// ourselves and send a proper JSON response
		sly_Util_Csrf::checkToken();

		switch ($method) {
			case 'install':
				$manager->install($addon, true, $persistence, $container);
				break;

			case 'uninstall':
				$manager->uninstall($addon, $persistence, $container);
				break;

			case 'activate':
				$manager->$method($addon, $container);
				break;

			default:
				$manager->$method($addon);
		}

		if (!$silent) {
			$container->getFlashMessage()->appendInfo(t('addon_'.$i18n, $addon));
		}
	}

	private function sendResponse($finished = true) {
		if ($this->getRequest()->isAjax()) {
			$data  = $this->buildDataList();
			$data  = $this->resolveParentRelationships($data);
			$flash = $this->getFlashMessage();
			$msgs  = $flash->getMessages(sly_Util_FlashMessage::TYPE_WARNING);

			foreach ($msgs as $idx => $list) {
				$msgs[$idx] = is_array($list) ? implode('<br />', $list) : $list;
			}

			$content = array(
				'status'   => empty($msgs),
				'stati'    => $this->buildStatusList($data),
				'message'  => implode('<br />', $msgs),
				'finished' => $finished
			);

			$flash->clear();

			$response = $this->getContainer()->getResponse();
			$response->setContentType('application/json', 'UTF-8');
			$response->setContent(json_encode($content));

			return $response;
		}

		return $this->redirectResponse();
	}

	/**
	 * @param  string $addon
	 * @return array
	 */
	private function getAddOnDetails($addon) {
		static $reqCache = array();
		static $depCache = array();

		extract($this->getServices());

		if (!isset($reqCache[$addon])) {
			$reqCache[$addon] = $aservice->getRequirements($addon);
			$depCache[$addon] = $aservice->getDependencies($addon, true, true);
		}

		$requirements = $reqCache[$addon];
		$dependencies = $depCache[$addon];
		$missing      = array();
		$required     = $aservice->isRequired($addon) !== false;
		$installed    = $aservice->isInstalled($addon);
		$activated    = $installed ? $aservice->isActivated($addon) : false;
		$compatible   = $aservice->isCompatible($addon);
		$version      = $pservice->getVersion($addon);
		$parent       = $pservice->getParent($addon);
		$author       = sly_Helper_Package::getSupportPage($addon);
		$usable       = $compatible ? $this->canBeUsed($addon) : false;

		if ($parent !== null) {
			// do not allow to nest more than one level
			$exists   = $pservice->exists($parent);
			$hasGrand = $exists ? $pservice->getParent($parent) : false;

			if (!$exists || $hasGrand) {
				$parent = null;
			}
			else {
				$requirements[] = $parent;
				$requirements   = array_unique($requirements);
			}
		}

		foreach ($requirements as $req) {
			if (!$aservice->isAvailable($req)) $missing[] = $req;
		}

		return compact('requirements', 'dependencies', 'missing', 'required', 'installed', 'activated', 'compatible', 'usable', 'version', 'author', 'parent');
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
		extract($this->getServices());

		if (!$pservice->exists($package))       return false;
		if (!$aservice->isCompatible($package)) return false;

		$requirements = $aservice->getRequirements($package, false);

		foreach ($requirements as $requirement) {
			if (!$this->canBeUsed($requirement)) return false;
		}

		return true;
	}

	/**
	 * Determine what packages to install
	 *
	 * This method will walk through all requirements and collect a list of
	 * packages that need to be installed to install the $addon. The list
	 * is ordered ($addon is always the last element). Already activated
	 * packages will not be included (so the result can be empty if $addon
	 * is also already activated).
	 *
	 * @param  string $addon  addon name
	 * @param  array  $list   current stack (used internally)
	 * @return array          install list
	 */
	private function getInstallList($addon, array $list = array()) {
		extract($this->getServices());

		$idx          = array_search($addon, $list);
		$requirements = $aservice->getRequirements($addon);

		if ($idx !== false) {
			unset($list[$idx]);
			$list = array_values($list);
		}

		if (!$aservice->isAvailable($addon)) {
			array_unshift($list, $addon);
		}

		foreach ($requirements as $requirement) {
			$list = $this->getInstallList($requirement, $list);
		}

		return $list;
	}

	private function buildDataList() {
		extract($this->getServices());
		$result = array();

		foreach ($aservice->getRegisteredAddOns() as $addon) {
			$details = $this->getAddOnDetails($addon);

			$details['children'] = array();
			$result[$addon]      = $details;
		}

		return $result;
	}

	private function buildStatusList(array $packageData) {
		$result = array();

		foreach ($packageData as $package => $info) {
			$classes = array('sly-addon');

			// build class list for all relevant stati

			if (!empty($info['children'])) {
				$classes[] = 'ch1'; // children yes

				foreach ($info['children'] as $childInfo) {
					if ($childInfo['activated']) {
						$classes[] = 'ca1';
						$classes[] = 'd1';  // assume implicit dependency of packages from their parent packages
						break;
					}
				}
			}
			else {
				$classes[] = 'ch0'; // childen no
			}

			// if there are no active children, dependency status is based on required status
			if (!in_array('ca1', $classes)) {
				$classes[] = 'd'.intval($info['required']);
			}
			else {
				$classes[] = 'ca0'; // children active no
			}

			$classes[] = 'i'.intval($info['installed']);
			$classes[] = 'a'.intval($info['activated']);
			$classes[] = 'c'.intval($info['compatible']);
			$classes[] = 'r'.intval($info['requirements']);
			$classes[] = 'ro'.(empty($info['missing']) ? 1 : 0);
			$classes[] = 'u'.intval($info['usable']);

			$result[$package] = array(
				'classes' => implode(' ', $classes),
				'deps'    => $this->buildDepsInfo($info)
			);

			foreach ($info['children'] as $childPkg => $childInfo) {
				$classes = array('sly-addon-child');

				$childInfo['requirements'][] = $package;
				$childInfo['requirements'] = array_unique($childInfo['requirements']);

				$classes[] = 'i'.intval($childInfo['installed']);
				$classes[] = 'a'.intval($childInfo['activated']);
				$classes[] = 'd'.intval($childInfo['required']);
				$classes[] = 'c'.intval($childInfo['compatible']);
				$classes[] = 'r'.intval($childInfo['requirements']);
				$classes[] = 'ro'.(empty($childInfo['missing']) ? 1 : 0);
				$classes[] = 'u'.intval($childInfo['usable']);

				$result[$childPkg] = array(
					'classes' => implode(' ', $classes),
					'deps'    => $this->buildDepsInfo($childInfo)
				);
			}
		}

		return $result;
	}

	/**
	 * Build a HTML string describing the requirements and dependencies
	 *
	 * @param  array $info  addOn info
	 * @return string
	 */
	private function buildDepsInfo(array $info) {
		$texts = array();

		if ($info['required']) {
			if (count($info['dependencies']) === 1) {
				$text = t('is_required', reset($info['dependencies']));
			}
			else {
				$list = sly_Util_String::humanImplode($info['dependencies']);
				$text = t('is_required', count($info['dependencies']));
				$text = '<span title="'.sly_html($list).'">'.$text.'</span>';
			}

			$texts[] = $text;
		}

		if ($info['requirements']) {
			if (count($info['requirements']) === 1) {
				$text = t('requires').' '.reset($info['requirements']);
			}
			else {
				$list = sly_Util_String::humanImplode($info['requirements']);
				$text = t('requires').' '.count($info['requirements']);
				$text = '<span title="'.sly_html($list).'">'.$text.'</span>';
			}

			$texts[] = $text;
		}

		if (empty($texts)) {
			return t('no_dependencies');
		}

		return implode(' &amp; ', $texts);
	}

	/**
	 * Transform addOn list by using parents
	 *
	 * This method scans through the flat list of addOns and moved all addOns,
	 * that have a "parent" declaration into the parent's "children" key. This is
	 * done to make the view simpler.
	 *
	 * @param  array $data
	 * @return array
	 */
	private function resolveParentRelationships(array $data) {
		do {
			$changes = false;

			foreach ($data as $package => $info) {
				if ($info['parent']) {
					$data[$info['parent']]['children'][$package] = $info;
					unset($data[$package]);
					$changes = true;
					break;
				}
			}
		} while ($changes);

		return $data;
	}
}
