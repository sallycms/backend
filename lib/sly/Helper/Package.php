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
 * @author zozi@webvariants.de
 */
class sly_Helper_Package {
	/**
	 * Get string with links to support pages
	 *
	 * @param  string $package   package name
	 * @return string            a comma separated list of links to authors
	 */
	public static function getSupportPage($package) {
		$container = sly_Core::getContainer();
		$service   = $container->getAddOnPackageService();

		if (!$service->exists($package)) {
			$service = $container->getVendorPackageService();

			if (!$service->exists($package)) {
				return '';
			}
		}

		$authors  = $service->getKey($package, 'authors');
		$homepage = $service->getHomepage($package, '');

		if (empty($authors)) {
			$links[] = array($homepage, $homepage);
		}
		else {
			foreach ($authors as $author) {
				$name = isset($author['name']) ? $author['name'] : (isset($author['email']) ? $author['email'] : 'Anon Ymous');
				$url  = isset($author['homepage']) ? $author['homepage'] : $homepage;

				$links[] = array($name, $url);
			}
		}

		$result = array();

		foreach ($links as $link) {
			list ($name, $url) = $link;
			$name = sly_Util_String::cutText($name, 40);

			// try to parse the homepage URL
			$infos = parse_url($url);

			if (!isset($infos['host'])) {
				$infos = parse_url('http://'.$url);
			}

			if (!isset($infos['host'])) {
				$result[] = sly_html($name);
			}
			else {
				$url      = sprintf('%s://%s%s', $infos['scheme'], $infos['host'], isset($infos['path']) ? $infos['path'] : '/');
				$result[] = '<a href="'.sly_html($url).'" class="sly-blank">'.sly_html($name).'</a>';
			}
		}

		return implode(', ', $result);
	}
}
