<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Document_Version Backend Editing Controller.
 *
 * @package HostCMS
 * @subpackage Document
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Document_Version_Controller_Edit extends Admin_Form_Action_Controller_Type_Edit
{
	/**
	 * Set object
	 * @param object $object object
	 * @return self
	 */
	public function setObject($object)
	{
		$this
			->addSkipColumn('id')
			->addSkipColumn('datetime');

		if (!$object->id)
		{
			$object->document_id = intval(Core_Array::getGet('document_id'));
		}

		return parent::setObject($object);
	}

	/**
	 * Prepare backend item's edit form
	 *
	 * @return self
	 */
	protected function _prepareForm()
	{
		parent::_prepareForm();

		$oMainTab = $this->getTab('main');
		$oAdditionalTab = $this->getTab('additional');

		// Объект вкладки 'Атрибуты документа'
		$oAttrTab = Admin_Form_Entity::factory('Tab')
			->caption(Core::_('Document.tab_1'))
			->name('tab_1');

		$this->addTabAfter($oAttrTab, $oMainTab);

		$title = $this->_object->id
			? Core::_('Document_Version.edit')
			: Core::_('Document_Version.add');

		$oMainTab
			->add($oMainRow1 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow2 = Admin_Form_Entity::factory('Div')->class('row'));

		$oDocument_Name = Admin_Form_Entity::factory('Input')
			->value(
				$this->_object->Document->Name
			)
			->caption(Core::_('Document.name'))
			->name('name')
			->class('form-control input-lg');

		$oMainRow1->add($oDocument_Name);

		$oTextarea_Document = Admin_Form_Entity::factory('Textarea')
			->value(
				!is_null($this->_object->id) ? $this->_object->loadFile() : ''
			)
			->rows(15)
			->caption(Core::_('Document_Version.text'))
			->name('text')
			->wysiwyg(TRUE)
			->template_id($this->_object->template_id);

		$oMainRow2->add($oTextarea_Document);

		if (Core::moduleIsActive('typograph'))
		{
			$oTextarea_Document->value(
				Typograph_Controller::instance()->eraseOpticalAlignment($oTextarea_Document->value)
			);

			$oUseTypograph = Admin_Form_Entity::factory('Checkbox')
				->name("use_typograph")
				->caption(Core::_('Document.use_typograph'))
				->value(1)
				->divAttr(array('class' => 'form-group col-sm-12 col-md-6 col-lg-6'));

			$oUseTrailingPunctuation = Admin_Form_Entity::factory('Checkbox')
				->name("use_trailing_punctuation")
				->caption(Core::_('Document.use_trailing_punctuation'))
				->value(1)
				->divAttr(array('class' => 'form-group col-sm-12 col-md-6 col-lg-6'));

			$oMainTab
				->add($oMainRow3 = Admin_Form_Entity::factory('Div')->class('row'));

			$oMainRow3
				->add($oUseTypograph)
				->add($oUseTrailingPunctuation);
		}

		$oAttrTab
			->add($oAttrRow1 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oAttrRow2 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oAttrRow3 = Admin_Form_Entity::factory('Div')->class('row'));

		// Выбор макета
		$oAdditionalTab->delete($this->getField('template_id'));
		$Template_Controller_Edit = new Template_Controller_Edit($this->_Admin_Form_Action);

		$aTemplateOptions = $Template_Controller_Edit->fillTemplateList($this->_object->Document->site_id);

		$oSelect_Template_Id = Admin_Form_Entity::factory('Select')
			->options(
				count($aTemplateOptions) ? $aTemplateOptions : array(' … ')
			)
			->name('template_id')
			->value(
				!is_null($this->_object->id)
				? $this->_object->template_id
				: 0
			)
			->caption(Core::_('Document_Version.template_id'));

		$oAttrRow1->add($oSelect_Template_Id);

		$oMainTab
			->move($this->getField('current'), $oAttrRow2)
			->move($this->getField('description'), $oAttrRow3);

		$this->title($title);

		return $this;
	}

	/**
	 * Processing of the form. Apply object fields.
	 * @hostcms-event Document_Version_Controller_Edit.onAfterRedeclaredApplyObjectProperty
	 */
	protected function _applyObjectProperty()
	{
		// Create new document version
		$this->_object = clone $this->_object;

		parent::_applyObjectProperty();

		$text = Core_Array::getPost('text');

		if (Core::moduleIsActive('typograph') && Core_Array::getPost('use_typograph'))
		{
			$text = Typograph_Controller::instance()->process($text, Core_Array::getPost('use_trailing_punctuation'));
		}

		$this->_object->saveFile($text);
		if ($this->_object->current)
		{
			$this->_object->setCurrent();
		}

		Core_Event::notify(get_class($this) . '.onAfterRedeclaredApplyObjectProperty', $this, array($this->_Admin_Form_Controller));
	}
}