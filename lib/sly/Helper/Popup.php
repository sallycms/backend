<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Helper_Popup {
	protected $params;
	protected $values;
	protected $event;

	public function __construct(array $params, $event) {
		$this->params = $params;
		$this->values = array();
		$this->event  = $event;
	}

	public function init(sly_Request $request, sly_Event_IDispatcher $dispatcher) {
		$params = $dispatcher->filter($this->event, $this->params);

		$this->values = array();

		foreach ($params as $param => $type) {
			if ($type === 'array') {
				$value = $request->requestArray($param, 'string');
			}
			else {
				$value = $request->request($param, $type);
			}

			if ($value === null || $value === array()) {
				continue;
			}

			$this->values[$param] = $value;
		}
	}

	public function appendQueryString($url, $separator = '&amp;') {
		if (empty($this->values)) {
			return $url;
		}

		if (is_array($url)) {
			return array_merge($this->values, $url);
		}

		$query = http_build_query($this->values, '', $separator);
		$url   = rtrim($url, '?&');

		if (strpos($url, '?') === false) {
			$separator = '?';
		}

		return $url.$separator.$query;
	}

	public function getValues() {
		return $this->values;
	}

	public function get($name) {
		return isset($this->values[$name]) ? $this->values[$name] : null;
	}

	public function getArgument($name) {
		return isset($this->values['args'][$name]) ? $this->values['args'][$name] : null;
	}

	public function appendParamsToForm(sly_Form $form) {
		if (empty($this->values)) {
			return;
		}

		foreach ($this->values as $param => $value) {
			if (is_array($value)) {
				foreach ($value as $key => $v) {
					$form->addHiddenValue($param.'['.$key.']', $v);
				}
			}
			else {
				$form->addHiddenValue($param, $value);
			}
		}
	}
}
