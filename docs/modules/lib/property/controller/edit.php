<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Lib_Property Backend Editing Controller.
 *
 * @package HostCMS
 * @subpackage Lib
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Lib_Property_Controller_Edit extends Admin_Form_Action_Controller_Type_Edit
{
	/**
	 * Constructor.
	 * @param Admin_Form_Action_Model $oAdminFormAction action
	 */
	public function __construct(Admin_Form_Action_Model $oAdminFormAction)
	{
		parent::__construct($oAdminFormAction);
	}

	/**
	 * Set object
	 * @param object $object object
	 * @return self
	 */
	public function setObject($object)
	{
		// При добавлении объекта
		if (!$object->id)
		{
			$object->lib_id = Core_Array::getGet('lib_id');
		}

		parent::setObject($object);

		$this->title($this->_object->id
			? Core::_('Lib_Property.lib_property_form_title_edit')
			: Core::_('Lib_Property.lib_property_form_title_add'));

		$windowId = $this->_Admin_Form_Controller->getWindowId();

		$oHtmlFormSelect = Admin_Form_Entity::factory('Select')
			->options(array(
				Core::_('Lib_Property.lib_property_type_0'),
				Core::_('Lib_Property.lib_property_type_1'),
				Core::_('Lib_Property.lib_property_type_2'),
				Core::_('Lib_Property.lib_property_type_3'),
				Core::_('Lib_Property.lib_property_type_4'),
				Core::_('Lib_Property.lib_property_type_5'),
				Core::_('Lib_Property.lib_property_type_6')
			))
			->name('type')
			->value($this->_object->type)
			->caption(Core::_('Lib_Property.type'))
			->onchange("ShowRowsLibProperty('{$windowId}', this.options[this.selectedIndex].value)");

		// Явно определяем ID <div>
		$this->getField('sql_request')->divAttr(
			array('id' => 'sql_request')
		);
		$this->getField('sql_caption_field')->divAttr(
			array('id' => 'sql_caption_field')
		);
		$this->getField('sql_value_field')->divAttr(
			array('id' => 'sql_value_field')
		);

		// Получаем основную вкладку
		$oMainTab = $this->getTab('main');

		$oMainTab
			->add($oMainRow1 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow2 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow3 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow4 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow5 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow6 = Admin_Form_Entity::factory('Div')->class('row'))
			->add($oMainRow7 = Admin_Form_Entity::factory('Div')->class('row'))
			;

		$this->getField('sql_request')->divAttr(array('id' => 'sql_request', 'class' => 'form-group col-xs-12'));
		$this->getField('sql_caption_field')->divAttr(array('id' => 'sql_caption_field', 'class' => 'form-group col-md-6 col-xs-12'));
		$this->getField('sql_value_field')->divAttr(array('id' => 'sql_value_field', 'class' => 'form-group col-md-6 col-xs-12'));

		$oMainTab
			->move($this->getField('name'), $oMainRow1)
			->move($this->getField('description'), $oMainRow2)
			->move($this->getField('varible_name')->divAttr(array('class' => 'form-group col-md-6 col-xs-12')), $oMainRow3)
			->move($this->getField('default_value')->divAttr(array('class' => 'form-group col-md-6 col-xs-12')), $oMainRow5)
			->move($this->getField('sorting')->divAttr(array('class' => 'form-group col-md-3 col-xs-12')), $oMainRow5)
			->move($this->getField('sql_request'), $oMainRow6)
			->move($this->getField('sql_caption_field'), $oMainRow7)
			->move($this->getField('sql_value_field'), $oMainRow7);

		// Удаляем стандартный <input>
		$oMainTab->delete($this->getField('type'));

		//$oMainTab->addAfter($oHtmlFormSelect, $this->getField('varible_name'));
		$oMainRow3->add($oHtmlFormSelect->divAttr(array('class' => 'form-group col-md-6 col-xs-12')));

		$oAdmin_Form_Entity_Code = Admin_Form_Entity::factory('Code');
		$oAdmin_Form_Entity_Code->html(
			"<script>ShowRowsLibProperty('{$windowId}', " . intval($this->_object->type) . ")</script>"
		);

		$oMainTab->add($oAdmin_Form_Entity_Code);

		return $this;
	}
}
