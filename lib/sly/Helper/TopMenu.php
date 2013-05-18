<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Helper_TopMenu {
	/**
	 * Generate the HTML for the top menu
	 *
	 * This method will render one menu item for each subpage of the given page.
	 *
	 * @param  sly_Layout_Navigation_Page $root
	 * @param  sly_Router_Backend         $router
	 * @return string
	 */
	public function render(sly_Layout_Navigation_Page $root, sly_Router_Backend $router) {
		$subPages = $root->getSubpages();
		$result   = array();
		$numPages = count($subPages);
		$format   = '<a href="%s"%s>%s</a>';

		foreach ($subPages as $idx => $subpage) {
			$page      = $subpage->getPageParam();
			$label     = $subpage->getTitle();
			$extra     = $subpage->getExtraParams();
			$className = '';
			$params    = !empty($extra) ? sly_Util_HTTP::queryString($extra, '&amp;', false) : '';
			$linkClass = array();
			$liClass   = array();

			if ($className) {
				$liClass[] = $className;
			}

			if ($idx === 0) {
				$liClass[] = 'sly-first';
			}

			if ($idx === $numPages-1) {
				$liClass[] = 'sly-last';
			}

			if ($subpage->isActive()) {
				$linkClass[] = 'sly-active';
				$liClass[]   = 'sly-active';
			}

			$linkAttr  = ' rel="page-'.urlencode($page).'"';
			$linkAttr .= empty($linkClass) ? '' : ' class="'.implode(' ', $linkClass).'"';
			$liAttr    = empty($liClass)   ? '' : ' class="'.implode(' ', $liClass).'"';
			$link      = sprintf($format, $router->getUrl($page, null, $params), $linkAttr, $label);

			$result[] = '<li'.$liAttr.'>'.$link.'</li>';
		}

		if (empty($result)) return '';

		return '<div id="sly-navi-page"><ul>'.implode("\n", $result).'</ul></div>';
	}
}
