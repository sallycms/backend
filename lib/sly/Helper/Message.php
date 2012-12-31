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
 * @author zozi@webvariants.de
 */
class sly_Helper_Message {
	public static function message($message, $cssClass = '', $msgwrapper = 'p') {
		$return = '<div class="sly-message '.$cssClass.'"><'.$msgwrapper.'>';
		$return .= '<span>'.$message.'</span>';
		$return .= '</'.$msgwrapper.'></div>';
		return $return;
	}

	public static function info($message) {
		return self::message($message, 'sly-info');
	}

	public static function warn($message) {
		return self::message($message, 'sly-warn');
	}

	public static function renderFlashMessage(sly_Util_Flash $message = null) {
		$msg      = $message === null ? sly_Core::getFlashMessage() : $message;
		$messages = $msg->getMessages();
		$result   = array();

		foreach ($messages as $type => $msgs) {
			foreach ($msgs as $m) {
				if (is_array($m)) $m = implode("<br />\n", $m);

				if ($type === sly_Util_FlashMessage::TYPE_INFO) {
					$result[] = self::info($m);
				}
				elseif ($type === sly_Util_FlashMessage::TYPE_WARNING) {
					$result[] = self::warn($m);
				}
			}
		}

		$msg->clear();

		return implode("\n", $result);
	}
}
