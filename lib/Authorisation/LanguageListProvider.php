<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup authorisation
 */
class sly_Authorisation_LanguageListProvider implements sly_Authorisation_ListProvider {
	/**
	 * get object IDs
	 *
	 * @return array
	 */
	public function getObjectIds() {
		return sly_Util_Language::findAll(true);
	}

	/**
	 * get object title
	 *
	 * @throws sly_Exception  if the ID was not found
	 * @param  int $id        language ID
	 * @return string
	 */
	public function getObjectTitle($id) {
		$lng = sly_Util_Language::findById($id);

		if (!$lng) {
			throw new sly_Exception(t('language_not_found', $id));
		}

		return $lng->getName();
	}
}
