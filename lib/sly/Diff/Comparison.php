<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Diff_Comparison {
	protected $slotKey;
	protected $slotName;
	protected $diff;
	protected $revA;
	protected $revB;

	/**
	 * @return string
	 */
	public function getSlotKey() {
		return $this->slotKey;
	}

	/**
	 * @return string
	 */
	public function getSlotName() {
		return $this->slotName ? $this->slotName : $this->slotKey;
	}

	/**
	 * @return array
	 */
	public function getDiff() {
		return $this->diff;
	}

	/**
	 * @return int
	 */
	public function getRevA() {
		return $this->revA;
	}

	/**
	 * @return int
	 */
	public function getRevB() {
		return $this->revB;
	}

	/**
	 * @param string $slotKey
	 */
	public function setSlotKey($slotKey) {
		$this->slotKey = $slotKey;
	}

	/**
	 * @param string $slotName
	 */
	public function setSlotName($slotName) {
		$this->slotName = $slotName;
	}

	/**
	 * @param array $diff
	 */
	public function setDiff(array $diff = array()) {
		$this->diff = $diff;
	}

	/**
	 * @param string $revA
	 */
	public function setRevA($revA) {
		$this->revA = (int) $revA;
	}

	/**
	 * @param string $revB
	 */
	public function setRevB($revB) {
		$this->revB = (int) $revB;
	}
}
