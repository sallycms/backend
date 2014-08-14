<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Credits_Bugreport extends sly_Controller_Credits implements sly_Controller_Interface {
	public function indexAction() {
		$request = $this->getRequest();
		$server  = $request->server('SERVER_SOFTWARE', 'N/A');
		$ua      = $request->getUserAgent() ? $request->getUserAgent() : 'N/A';
		$caching = $this->getContainer()->get('sly-config')->get('caching_strategy');

		$this->init();
		$this->render('credits/bugreport.phtml', array('server' => $server, 'ua' => $ua, 'caching' => $caching), false);
	}

	protected function getLanguages() {
		$langs = sly_Util_Language::findAll();

		foreach ($langs as $idx => $lang) {
			$langs[$idx] = sprintf('[%d] %s (%s)', $lang->getId(), $lang->getName(), $lang->getLocale());
		}

		return $langs;
	}

	protected function getDatabaseVersion() {
		$driver = strtolower($this->getContainer()->getConfig()->get('database/driver'));

		switch ($driver) {
			case 'mysql':
			case 'pgsql':
				$db = $this->getContainer()->getPersistence();
				$db->query('SELECT VERSION()');
				foreach ($db->all() as $row) $version = reset($row);
				break;

			case 'sqlite':
				$version = SQLite3::version();
				$version = $version['versionString'];
				break;

			case 'oci':
			default:
				$version = 'N/A';
		}

		return compact('driver', 'version');
	}

	protected function getExtensions() {
		$extensions = get_loaded_extensions();
		$extnum     = count($extensions);
		$extlists   = array();

		natcasesort($extensions);

		for ($i = 0; $i < $extnum; $i += 7) {
			$extlists[] = implode(', ', array_slice($extensions, $i, 7));
		}

		return $extlists;
	}

	public function checkPermission($action) {
		$user = $this->getCurrentUser();
		return $user && $user->isAdmin();
	}
}
