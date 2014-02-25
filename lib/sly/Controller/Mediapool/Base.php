<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use sly\Assets\Util;

abstract class sly_Controller_Mediapool_Base extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $args;
	protected $category;
	protected $selectBox;
	protected $categories = null;
	protected $popupHelper;

	private $init = false;

	protected function init() {
		if ($this->init) return;
		$this->init = true;

		// init custom query string params
		$this->initPopupHelper();

		// init category filter
		$cats = $this->popupHelper->getArgument('categories');

		// do NOT use empty(), as '0' is a valid value!
		if (strlen($cats) > 0) {
			$cats             = array_unique(array_map('intval', explode('|', $cats)));
			$this->categories = count($cats) === 0 ? null : $cats;
		}

		$this->getCurrentCategory();

		// build navigation

		$app    = $this->getContainer()->getApplication();
		$cur    = $app->getCurrentControllerName();
		$menu   = new sly_Layout_Navigation_Page('');
		$values = $this->popupHelper->getValues();

		$menu->addSubpage('mediapool',        t('media_list'));
		$menu->addSubpage('mediapool_upload', t('upload_file'));

		if ($this->isMediaAdmin()) {
			$menu->addSubpage('mediapool_structure', t('categories'));
			$menu->addSubpage('mediapool_sync',      t('sync_files'));
		}

		if (!empty($values)) {
			foreach ($menu->getSubpages() as $sp) {
				$sp->setExtraParams($values);

				// ignore the extra params when detecting the current page
				if ($cur === $sp->getPageParam()) $sp->forceStatus(true);
			}
		}

		$layout = $this->getContainer()->getLayout();
		$layout->showNavigation(false);
		$layout->pageHeader(t('media_list'), $menu);
		$layout->setBodyAttr('class', 'sly-popup sly-mediapool');

		$this->render('mediapool/javascript.phtml', array(), false);
	}

	protected function initPopupHelper() {
		$request    = $this->getRequest();
		$dispatcher = $this->container->getDispatcher();

		// init custom query string params
		$params = array('callback' => 'string', 'args' => 'array');

		$this->popupHelper = new sly_Helper_Popup($params, 'SLY_MEDIAPOOL_URL_PARAMS');
		$this->popupHelper->init($request, $dispatcher);
	}

	protected function appendQueryString($url, $separator = '&amp;') {
		return $this->popupHelper->appendQueryString($url, $separator);
	}

	protected function appendParamsToForm(sly_Form $form) {
		return $this->popupHelper->appendParamsToForm($form);
 	}

	protected function getCurrentCategory() {
		if ($this->category === null) {
			$request  = $this->getRequest();
			$category = $request->request('category', 'int', -1);
			$service  = $this->getContainer()->getMediaCategoryService();
			$session  = sly_Core::getSession();

			if ($category === -1) {
				$category = $session->get('sly-media-category', 'int', 0);
			}

			// respect category filter
			if (!empty($this->categories) && !in_array($category, $this->categories)) {
				$category = reset($this->categories);
			}

			$category = $service->findById($category);
			$category = $category ? $category->getId() : 0;

			$session->set('sly-media-category', $category);
			$this->category = $category;
		}

		return $this->category;
	}

	protected function getOpenerLink(sly_Model_Medium $file) {
		$link     = '';
		$callback = $this->popupHelper->get('callback');

		if (!empty($callback)) {
			$filename = $file->getFilename();
			$title    = $file->getTitle();
			$link     = '<a href="#" data-filename="'.sly_html($filename).'" data-title="'.sly_html($title).'">'.t('apply_file').'</a>';
		}

		return $link;
	}

	protected function getFiles() {
		$cat   = $this->getCurrentCategory();
		$where = 'f.category_id = '.$cat;
		$where = sly_Core::dispatcher()->filter('SLY_MEDIA_LIST_QUERY', $where, array('category_id' => $cat));
		$where = '('.$where.')';
		$types = $this->popupHelper->getArgument('types');

		if (!empty($types)) {
			$types = explode('|', preg_replace('#[^a-z0-9/+.-|]#i', '', $types));

			if (!empty($types)) {
				$where .= ' AND filetype IN ("'.implode('","', $types).'")';
			}
		}

		$db     = $this->getContainer()->getPersistence();
		$prefix = $db->getPrefix();
		$query  = 'SELECT f.id FROM '.$prefix.'file f LEFT JOIN '.$prefix.'file_category c ON f.category_id = c.id WHERE '.$where.' ORDER BY f.title ASC';
		$files  = array();

		$db->query($query);

		foreach ($db as $row) {
			$files[$row['id']] = sly_Util_Medium::findById($row['id']);
		}

		return $files;
	}

	protected function deleteMedium(sly_Model_Medium $medium, sly_Util_FlashMessage $msg) {
		$filename = $medium->getFileName();
		$user     = $this->getCurrentUser();
		$service  = $this->getContainer()->getMediumService();

		if ($this->canAccessCategory($medium->getCategoryId())) {
			$usages = $this->isInUse($medium);

			if ($usages === false) {
				try {
					$service->deleteByMedium($medium);
					$msg->appendInfo($filename.': '.t('medium_deleted'));
				}
				catch (sly_Exception $e) {
					$msg->appendWarning($filename.': '.$e->getMessage());
				}
			}
			else {
				$tmp   = array();
				$tmp[] = t('file_is_in_use', $filename);
				$tmp[] = '<ul>';

				foreach ($usages as $usage) {
					$title = sly_html($usage['title']);

					if (!empty($usage['link'])) {
						$tmp[] = '<li><a class="sly-blank" target="_blank" href="'.$usage['link'].'">'.$title.'</a></li>';
					}
					else {
						$tmp[] = '<li>'.$title.'</li>';
					}
				}

				$tmp[] = '</ul>';
				$msg->appendWarning(implode("\n", $tmp));
			}
		}
		else {
			$msg->appendWarning($filename.': '.t('no_permission'));
		}
	}

	public function checkPermission($action) {
		$user = $this->getCurrentUser();

		if (!$user || (!$user->isAdmin() && !$user->hasRight('pages', 'mediapool'))) {
			return false;
		}

		if ($action === 'batch') {
			sly_Util_Csrf::checkToken();
		}

		return true;
	}

	protected function isMediaAdmin() {
		$user = $this->getCurrentUser();
		return $user->isAdmin() || $user->hasRight('mediacategory', 'access', sly_Authorisation_ListProvider::ALL);
	}

	protected function canAccessFile(sly_Model_Medium $medium) {
		return $this->canAccessCategory($medium->getCategoryId());
	}

	protected function canAccessCategory($cat) {
		$user = $this->getCurrentUser();
		return $this->isMediaAdmin() || $user->hasRight('mediacategory', 'access', intval($cat));
	}

	protected function getCategorySelect() {
		$user = $this->getCurrentUser();

		if ($this->selectBox === null) {
			$this->selectBox = sly_Backend_Form_Helper::getMediaCategorySelect('category', null, $user);
			$this->selectBox->setLabel(t('categories'));
			$this->selectBox->setMultiple(false);
			$this->selectBox->setAttribute('value', $this->getCurrentCategory());

			// filter categories
			if (!empty($this->categories)) {
				$values = array_keys($this->selectBox->getValues());

				foreach ($values as $catID) {
					if (!in_array($catID, $this->categories)) {
						$this->selectBox->removeValue($catID);
					}
				}
			}
		}

		return $this->selectBox;
	}

	protected function getDimensions($width, $height, $maxWidth, $maxHeight) {
		if ($width > $maxWidth) {
			$factor  = (float) $maxWidth / $width;
			$width   = $maxWidth;
			$height *= $factor;
		}

		if ($height > $maxHeight) {
			$factor  = (float) $maxHeight / $height;
			$height  = $maxHeight;
			$width  *= $factor;
		}

		return array(ceil($width), ceil($height));
	}

	protected function getMimeIcon(sly_Model_Medium $medium) {
		$mapping = array(
			'compressed' => array('gz', 'gzip', 'tar', 'zip', 'tgz', 'bz', 'bz2', '7zip', '7z', 'rar'),
			'audio'      => array('mp3', 'flac', 'aac', 'wav', 'ac3', 'ogg', 'wma'),
			'document'   => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'rtf'),
			'executable' => array('sh', 'exe', 'bin', 'com', 'bat'),
			'pdf'        => array('pdf'),
			'text'       => array('txt', 'java', 'css', 'markdown', 'textile', 'md'),
			'vector'     => array('svg', 'eps'),
			'video'      => array('mkv', 'avi', 'swf', 'mov', 'flv', 'ogv', 'vp8')
		);

		$extension = $medium->getExtension();
		$base      = 'mime/';

		if (!$medium->exists()) {
			return Util::appUri($base.'missing.png');
		}

		foreach ($mapping as $type => $exts) {
			if (in_array($extension, $exts, true)) {
				return Util::appUri($base.$type.'.png');
			}
		}

		return Util::appUri($base.'unknown.png');
	}

	protected function isImage(sly_Model_Medium $medium) {
		$exts = array('gif', 'jpeg', 'jpg', 'png', 'bmp', 'tif', 'tiff', 'webp');
		return in_array($medium->getExtension(), $exts);
	}

	protected function getThumbnailTag(sly_Model_Medium $medium, $width, $height) {
		if (!$medium->exists()) {
			$thumbnail = '<img src="'.$this->getMimeIcon($medium).'" width="44" height="38" alt="'.ht('file_not_found').'" />';
		}
		else {
			$icon_src  = $this->getMimeIcon($medium);
			$alt       = $medium->getTitle();
			$thumbnail = '<img src="'.$icon_src.'" alt="'.sly_html($alt).'" title="'.sly_html($alt).'" />';

			if ($this->isImage($medium)) {
				$mwidth    = $medium->getWidth();
				$mheight   = $medium->getHeight();
				$timestamp = $medium->getUpdateDate();
				$encoded   = urlencode($medium->getFilename());

				list($width, $height) = $this->getDimensions($mwidth, $mheight, $width, $height);

				$attrs = array(
					'alt'    => $alt,
					'title'  => $alt,
					'width'  => $width,
					'height' => $height,
					'src'    => sly\Assets\Util::mediapoolUri($encoded.'?t='.$timestamp)
				);

				$thumbnail = '<img '.sly_Util_HTML::buildAttributeString($attrs, array('alt')).' />';
			}
		}

		$dispatcher = $this->getContainer()->getDispatcher();
		$thumbnail  = $dispatcher->filter('SLY_BACKEND_MEDIAPOOL_THUMBNAIL', $thumbnail, array(
			'medium'  => $medium,
			'width'   => $width,
			'height'  => $height,
			'isImage' => $this->isImage($medium)
		));

		return $thumbnail;
	}

	protected function isInUse(sly_Model_Medium $medium) {
		$container = $this->getContainer();
		$service   = $container->getMediumService();
		$router    = $container->getApplication()->getRouter();
		$usages    = $service->getUsages($medium);

		foreach ($usages as $idx => $usage) {
			// properly setup object
			if (!empty($usage['link']) && !empty($usage['title'])) {
				continue;
			}

			switch ($usage['type']) {
				case 'sly-article':
					$article = $usage['object'];
					$title   = $article->getName();
					$link    = $router->getPlainUrl('content', null, array(
						'article_id' => $article->getId(),
						'clang'      => $article->getClang(),
						'revision'   => $article->getRevision()
					));

					break;

				case 'sly-category':
					$category = $usage['object'];
					$title    = $category->getCatName();
					$link     = $router->getPlainUrl('structure', null,  array(
						'category_id' => $category->getId(),
						'clang'       => $category->getClang()
					));

					break;

				case 'sly-medium':
					$medium = $usage['object'];
					$title  = $medium->getTitle();
					$link   = $router->getPlainUrl('mediapool_detail', null, array('file_id' => $medium->getId()));

					break;

				case 'sly-user':
					$user  = $usage['object'];
					$title = $obj->getName()?: $obj->getLogin();
					$link  = $router->getPlainUrl('user', 'edit', array('id' => $user->getId()));
			}

			$usages[$idx]['link']  = $link;
			$usages[$idx]['title'] = $title;
		}

		return empty($usages) ? false : $usages;
	}

	protected function redirect($params = array(), $page = null, $code = 302) {
		if (!$this->popupHelper) {
			$this->initPopupHelper();
		}

		$values = $this->popupHelper->getValues();
		$params = array_merge($values, sly_makeArray($params));

		$this->container->getApplication()->redirect($page, $params, $code);
	}

	protected function redirectResponse($params = array(), $controller = null, $action = null, $code = 302) {
		if (!$this->popupHelper) {
			$this->initPopupHelper();
		}

		$values = $this->popupHelper->getValues();
		$params = array_merge($values, sly_makeArray($params));

		return $this->container->getApplication()->redirectResponse($controller, $action, $params, $code);
	}
}
