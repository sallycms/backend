<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System_Environment extends sly_Controller_System {
	public function indexAction() {
		$this->init();

		$container = $this->getContainer();
		$database  = $container->getConfig()->get('database');
		$database  = array_merge($database, $this->getDatabaseVersion());

		unset($database['password']);

		$this->render('system/environment.phtml', compact('database'), false);
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

		return compact('version');
	}
}
