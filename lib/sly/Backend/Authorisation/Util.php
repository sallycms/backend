<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Backend_Authorisation_Util {
	/**
	 * @param  sly_Model_User $user
	 * @param  int            $categoryID
	 * @return boolean
	 */
	public static function canReadCategory(sly_Model_User $user, $categoryID) {
		if ($user->isAdmin()) return true;
		static $canReadCache;

		$userID = $user->getId();

		if (!isset($canReadCache[$userID])) {
			$canReadCache[$userID] = array();
		}

		if (!isset($canReadCache[$userID][$categoryID])) {
			$canReadCache[$userID][$categoryID] = false;

			if (self::canEditContent($user, $categoryID)) {
				$canReadCache[$userID][$categoryID] = true;
			}
			else {
				// check all children for write rights
				$article = sly_Util_Category::findById($categoryID);

				if ($article) {
					$path = $article->getPath().$article->getId().'|%';
				}
				else {
					$path = '|%';
				}

				$query  = sly_DB_Persistence::getInstance();
				$prefix = $query->getPrefix();
				$query->query('SELECT DISTINCT id FROM '.$prefix.'article WHERE path LIKE ?', array($path));

				foreach ($query as $row) {
					if (self::canEditContent($user, $row['id'])) {
						$canReadCache[$userID][$categoryID] = true;
						break;
					}
				}
			}
		}

		return isset($canReadCache[$userID][$categoryID]) ? $canReadCache[$userID][$categoryID] : false;
	}

	/**
	 * @param  sly_Model_User $user
	 * @param  int            $articleID
	 * @return boolean
	 */
	public static function canReadArticle(sly_Model_User $user, $articleID) {
		return self::canReadCategory($user, $articleID);
	}

	/**
	 * @param  sly_Model_User $user
	 * @param  int            $articleID
	 * @return boolean
	 */
	public static function canEditArticle(sly_Model_User $user, $articleID) {
		if ($user->isAdmin()) return true;
		if ($user->hasRight('article', 'edit', sly_Authorisation_ListProvider::ALL)) return true;
		return $user->hasRight('article', 'edit', $articleID);
	}

	/**
	 * @param  sly_Model_User $user
	 * @param  int            $articleID
	 * @return boolean
	 */
	public static function canEditContent(sly_Model_User $user, $articleID) {
		if ($user->isAdmin()) return true;
		if ($user->hasRight('article', 'editcontent', sly_Authorisation_ListProvider::ALL)) return true;
		return $user->hasRight('article', 'editcontent', $articleID);
	}
}
