<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup authorisation
 */
class sly_Authorisation_ModuleListProvider implements sly_Authorisation_ListProvider {
	protected $moduleService;

	public function __construct() {
		$this->moduleService = sly_Core::getContainer()->getModuleService();
	}
	/**
	 * get object IDs
	 *
	 * @return array
	 */
	public function getObjectIds() {
		$ids = array_keys($this->moduleService->getModules());
		array_unshift($ids, self::ALL);
		return $ids;
	}

	/**
	 * get object title
	 *
	 * @throws sly_Exception  if the ID was not found
	 * @param  string $id     module identifier
	 * @return string
	 */
	public function getObjectTitle($id) {
		if ($id === self::ALL) return t('all');
		return $this->moduleService->getTitle($id);
	}
}
