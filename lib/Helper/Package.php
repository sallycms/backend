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
 * @author zozi@webvariants.de
 */
class sly_Helper_Package {
	/**
	 * Get string with links to support pages
	 *
	 * @param  string $package   package name
	 * @return string            a comma separated list of URLs
	 */
	public static function getSupportPage($package) {
		$service     = sly_Service_Factory::getPackageService();
		$supportPage = $service->getHomepage($package, '');
		$author      = $service->getAuthor($package);

		if ($supportPage) {
			$supportPages = sly_makeArray($supportPage);
			$links        = array();

			foreach ($supportPages as $idx => $page) {
				$infos = parse_url($page);
				if (!isset($infos['host'])) $infos = parse_url('http://'.$page);
				if (!isset($infos['host'])) continue;

				$page = sprintf('%s://%s%s', $infos['scheme'], $infos['host'], isset($infos['path']) ? $infos['path'] : '');
				$host = substr($infos['host'], 0, 4) == 'www.' ? substr($infos['host'], 4) : $infos['host'];
				$name = $idx === 0 && !empty($author) ? $author : $host;
				$name = sly_Util_String::cutText($name, 40);

				$links[] = '<a href="'.sly_html($page).'" class="sly-blank">'.sly_html($name).'</a>';
			}

			$supportPage = implode(', ', $links);
		}

		return $supportPage;
	}
}
