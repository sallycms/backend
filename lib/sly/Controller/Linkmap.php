<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Linkmap extends sly_Controller_Backend implements sly_Controller_Interface {
	protected $globals     = null;
	protected $tree        = array();
	protected $categories  = array();
	protected $types       = array();
	protected $roots       = array();
	protected $forced      = array();
	protected $popupHelper = array();
	protected $category    = null;
	protected $router      = null;
	protected $path        = null; // @edge navi path

	public function setContainer(sly_Container $container) {
		parent::setContainer($container);
		$this->router = $this->getContainer()->getApplication()->getRouter();
	}

	protected function init() {
		$request    = $this->getRequest();
		$dispatcher = $this->container->getDispatcher();
		$params     = array('callback' => 'string', 'args' => 'array');

		$this->popupHelper = new sly_Helper_Popup($params, 'SLY_LINKMAP_URL_PARAMS');
		$this->popupHelper->init($request, $dispatcher);

		// init category filter
		$cats = $this->popupHelper->getArgument('categories');

		// do NOT use empty(), as '0' is a valid value!
		if (strlen($cats) > 0) {
			$cats = array_map('intval', explode('|', $cats));

			foreach (array_unique($cats) as $catID) {
				$cat = sly_Util_Category::findById($catID);
				if ($cat) $this->categories[] = $catID;
			}
		}

		// init article type filter
		$types = $this->popupHelper->getArgument('types');

		if (!empty($types)) {
			$types       = explode('|', $types);
			$this->types = array_unique($types);
		}

		// generate list of categories that have to be opened (in case we have
		// a deeply nested allow category that would otherwise be unreachable)

		foreach ($this->categories as $catID) {
			if (in_array($catID, $this->forced)) continue;

			$category = sly_Util_Category::findById($catID);
			if (!$category) continue;

			$root = null;

			foreach ($category->getParentTree() as $cat) {
				if ($root === null) $root = $cat->getId();
				$this->forced[] = $cat->getId();
			}

			$this->roots[] = $root;
			$this->forced  = array_unique($this->forced);
			$this->roots   = array_unique($this->roots);
		}

		$catID     = $this->getGlobals('category_id', 0);
		$naviPath  = '<ul class="sly-navi-path">';
		$isRoot    = $catID === 0;
		$category  = $isRoot ? null : sly_Util_Category::findById($catID);

		// respect category filter
		if ($category === null || (!empty($this->categories) && !in_array($category->getId(), $this->forced))) {
			$category = reset($this->categories);
			$category = sly_Util_Category::findById($category);
		}

		$naviPath .= '<li>'.t('path').'</li>';

		if (empty($this->categories) || in_array(0, $this->categories)) {
			$link      = $this->url(array('category_id' => 0));
			$naviPath .= '<li><a href="'.$link.'">'.t('home').'</a></li>';
		}
		else {
			$naviPath .= '<li><span>'.t('home').'</span></li>';
		}

		if ($category) {
			$root = null;

			foreach ($category->getParentTree() as $cat) {
				$id = $cat->getId();

				$this->tree[]   = $id;
				$this->forced[] = $id;

				if (empty($this->categories) || in_array($id, $this->categories)) {
					$link      = $this->url(array('category_id' => $id));
					$naviPath .= '<li><a href="'.$link.'">'.sly_html($cat->getName()).'</a></li>';
				}
				else {
					$naviPath .= '<li><span>'.sly_html($cat->getName()).'</span></li>';
				}

				if ($root === null) $root = $id;
			}

			$this->roots[] = $root;
			$this->forced  = array_unique($this->forced);
			$this->roots   = array_unique($this->roots);
		}

		if (empty($this->categories)) {
			$this->roots = sly_Util_Category::getRootCategories();
		}

		$this->category = $category;

		$naviPath .= '</ul>';
		$layout    = $this->getContainer()->getLayout();

		// @edge navi path
		$this->path = $naviPath;

		$layout->setBodyAttr('class', 'sly-popup');
		$layout->showNavigation(false);
		$layout->pageHeader(t('linkmap')/*, $naviPath*/);
	}

	protected function getGlobals($key = null, $default = null) {
		if ($this->globals === null) {
			$this->globals = array(
				'page'        => 'linkmap',
				'category_id' => $this->getRequest()->request('category_id', 'int', 0),
				'clang'       => $this->container->getCurrentLanguageID()
			);
		}

		if ($key !== null) {
			return isset($this->globals[$key]) ? $this->globals[$key] : $default;
		}

		return $this->globals;
	}

	public function indexAction() {
		$this->init();

		$this->render('linkmap/javascript.phtml', array(), false);
		$this->render('linkmap/index.phtml', array(), false);
	}

	public function checkPermission($action) {
		$user = $this->getCurrentUser();
		return $user && ($user->isAdmin() || $user->hasRight('pages', 'structure'));
	}

	protected function url($local = array()) {
		$globals = $this->getGlobals();
		$extra   = $this->popupHelper->getValues();
		$params  = array_merge($globals, $extra, $local);

		return $this->router->getUrl('linkmap', 'index', $params);
	}

	protected function formatLabel($object) {
		$user  = $this->getCurrentUser();
		$label = trim($object->getName());

		if (empty($label)) $label = '&nbsp;';

		if (sly_Util_Article::isValid($object) && !$object->hasType()) {
			$label .= ' ['.t('no_articletype').']';
		}

		return $label;
	}

	protected function tree($children, $level = 1) {
		$ul = '';

		if (is_array($children)) {
			foreach ($children as $idx => $cat) {
				if (!($cat instanceof sly_Model_Category)) {
					$cat = sly_Util_Category::findById($cat);
				}

				if (!empty($this->categories) && !in_array($cat->getId(), $this->forced)) {
					unset($children[$idx]);
					continue;
				}

				$children[$idx] = $cat;
			}

			$children = array_values($children);
			$len      = count($children);
			$li       = '';

			foreach ($children as $idx => $cat) {
				$cat_children = $cat->getChildren();
				$cat_id       = $cat->getId();
				$classes      = array('lvl-'.$level);
				$sub_li       = '';

				if ($idx === 0) {
					$classes[] = 'first';
				}

				if ($idx === $len-1) {
					$classes[] = 'last';
				}

				$hasForcedChildren = false;
				$isVisitable       = empty($this->categories) || in_array($cat_id, $this->categories);

				foreach ($cat_children as $child) {
					if (in_array($child->getId(), $this->forced)) {
						$hasForcedChildren = true;
						break;
					}
				}

				if ($hasForcedChildren) {
					$classes[] = 'children';
				}
				else {
					$classes[] = 'empty';
				}

				if (in_array($cat_id, $this->tree) || ($hasForcedChildren && !$isVisitable)) {
					$sub_li    = $this->tree($cat_children, $level + 1);
					$classes[] = 'active';

					if ($cat_id == end($this->tree)) {
						$classes[] = 'leaf';
					}
				}

				$classes[] = $cat->isOnline() ? 'sly-online' : 'sly-offline';
				$label     = $this->formatLabel($cat);

				if (!empty($classes)) $classes = ' class="'.implode(' ', $classes).'"';
				else $classes = '';

				$li .= '<li class="lvl-'.$level.'">';

				if ($isVisitable) {
					$li .= '<a'.$classes.' href="'.$this->url(array('category_id' => $cat_id)).'">'.sly_html($label).'</a>';
				}
				else {
					$li .= '<span'.$classes.'>'.sly_html($label).'</span>';
				}

				$li .= $sub_li;
				$li .= '</li>';
			}

			if (!empty($li)) {
				$ul = "<ul>$li</ul>";
			}
		}

		return $ul;
	}
}
