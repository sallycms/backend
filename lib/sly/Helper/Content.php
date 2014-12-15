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
class sly_Helper_Content {
	public static function printAddSliceForm($slot, $module, $position, sly_Model_Base_Article $article) {
		$container     = sly_Core::getContainer();
		$moduleService = $container['sly-service-module'];
		$router        = $container['sly-app']->getRouter();

		if (!$moduleService->exists($module)) {
			print sly_Helper_Message::warn(ht('module_not_found', $module));
			return;
		}

		try {
			ob_start();

			$moduleTitle = $moduleService->getTitle($module);
			$form        = new sly_Form($router->getPlainUrl('content', 'addArticleSlice'), 'post', t('add_slice').': '.sly_translate($moduleTitle), '', 'addslice');

			$form->setEncType('multipart/form-data');
			$form->addHiddenValue('article_id', $article->getId());
			$form->addHiddenValue('clang', $article->getClang());
			$form->addHiddenValue('revision', $article->getRevision());
			$form->addHiddenValue('slot', $slot);
			$form->addHiddenValue('module', $module);
			$form->addHiddenValue('pos', $position);
			$form->setSubmitButton(new sly_Form_Input_Button('submit', 'btn_save', t('add_slice')));

			$renderer   = $container['sly-slice-renderer'];
			$sliceinput = new sly_Form_Fragment();

			// prepare a fake slice object for the input module, so it has
			// access to the slot and template via the same interface as the
			// output module
			$now          = time();
			$articleSlice = new sly_Model_ArticleSlice(array(
				'id'         => -2,
				'pos'        => $position,
				'article_id' => $article->getId(),
				'clang'      => $article->getClang(),
				'revision'   => $article->getRevision(),
				'slot'       => $slot,
				'updateuser' => '',
				'createuser' => '',
				'createdate' => $now,
				'updatedate' => $now
			));

			$articleSlice->setSlice(new sly_Model_Slice(array(
				'id'                => -2,
				'module'            => $module,
				'serialized_values' => json_encode(array())
			)));

			$sliceinput->setContent('<div class="sly-contentpage-slice-input">'.$renderer->renderInput($articleSlice, 'slicevalue').'</div>');

			$form->add($sliceinput);
			$form->addClass('sly-slice-form');

			print $form->render();

			self::focusFirstElement();

			$container['sly-dispatcher']->notify('SLY_SLICE_POSTVIEW_ADD', array(), array(
				'module'     => $module,
				'article_id' => $article->getId(),
				'clang'      => $article->getClang(),
				'slot'       => $slot
			));

			ob_end_flush();
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}
	}

	public static function printEditSliceForm(sly_Model_ArticleSlice $articleSlice, $values = array()) {
		$container     = sly_Core::getContainer();
		$moduleService = $container['sly-service-module'];
		$router        = $container['sly-app']->getRouter();
		$module        = $articleSlice->getModule();
		$moduleTitle   = $moduleService->getTitle($module);

		try {
			ob_start();

			// @edge save space
			// $form = new sly_Form($router->getPlainUrl('content', 'editArticleSlice'), 'post', t('edit_slice').': '.sly_translate($moduleTitle, true), '', 'editslice');
			$form = new sly_Form($router->getPlainUrl('content', 'editArticleSlice'), 'post', sly_translate($moduleTitle, true), '', 'editslice');
			$form->setEncType('multipart/form-data');
			$form->addHiddenValue('article_id', $articleSlice->getArticleId());
			$form->addHiddenValue('clang', $articleSlice->getClang());
			$form->addHiddenValue('slice_id', $articleSlice->getId());
			$form->addHiddenValue('slot', $articleSlice->getSlot());
			$form->setSubmitButton(new sly_Form_Input_Button('submit', 'btn_save', t('save')));
			$form->setApplyButton(new sly_Form_Input_Button('submit', 'btn_update', t('apply')));
			$form->setResetButton(new sly_Form_Input_Button('reset', 'reset', t('reset')));

			$container  = sly_Core::getContainer();
			$renderer   = $container['sly-slice-renderer'];
			$sliceinput = new sly_Form_Fragment();
			$sliceinput->setContent('<div class="sly-contentpage-slice-input">'.$renderer->renderInput($articleSlice, 'slicevalue').'</div>');

			$form->add($sliceinput);
			$form->addClass('sly-slice-form');

			print $form->render();

			self::focusFirstElement();

			$container['sly-dispatcher']->notify('SLY_SLICE_POSTVIEW_EDIT', $values, array(
				'module'     => $articleSlice->getModule(),
				'article_id' => $articleSlice->getArticleId(),
				'clang'      => $articleSlice->getClang(),
				'slot'       => $articleSlice->getSlot(),
				'slice'      => $articleSlice
			));

			ob_end_flush();
		}
		catch (Exception $e) {
			ob_end_clean();
			throw $e;
		}
	}

	private static function focusFirstElement() {
		$layout = sly_Core::getLayout();
		$layout->addJavaScript('jQuery(function($) { $(".sly-slice-form").find(":input:visible:enabled:not([readonly]):first").focus(); });');
	}

	public static function metaFormAddButtonBar($form, $label, $name) {
		$button = new sly_Form_Input_Button('submit', $name, $label);
		$button->setAttribute('onclick', 'return confirm('.json_encode($label.'?').')');
		$form->add(new sly_Form_ButtonBar(array('submit' => $button)));
	}
}
