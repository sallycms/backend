<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup authorisation
 */
class sly_Authorisation_MediaCategoryListProvider implements sly_Authorisation_ListProvider {
	/**
	 * get object IDs
	 *
	 * @return array
	 */
	public function getObjectIds() {
		$categories = sly_Core::getContainer()->getMediaCategoryService()->findAll();
		$res        = array(self::ALL);

		foreach ($categories as $category) {
			$res[] = (int) $category->getId();
		}

		return $res;
	}

	/**
	 * get object title
	 *
	 * @throws sly_Exception  if the ID was not found
	 * @param  int $id        category ID
	 * @return string
	 */
	public function getObjectTitle($id) {
		if ($id === self::ALL) return t('all');
		$cat = sly_Util_MediaCategory::findById($id);

		if (!$cat) {
			throw new sly_Exception(t('category_not_found', $id));
		}

		return $cat->getName();
	}
}
