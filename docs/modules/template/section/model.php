<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Template_Section_Model
 *
 * @package HostCMS
 * @subpackage Template
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Template_Section_Model extends Core_Entity{
	/**
	 * Backend property
	 * @var int
	 */
	public $template_section_libs = 3;

	/**
	 * Belongs to relations
	 * @var array
	 */
	protected $_belongsTo = array(
		'template' => array(),
	);

	/**
	 * One-to-many or many-to-many relations
	 * @var array
	 */
	protected $_hasMany = array(
		'template_section_lib' => array()
	);

	/**
	 * List of preloaded values
	 * @var array
	 */
	protected $_preloadValues = array(
		'sorting' => 0,
		'color' => '#35d4ef',
	);

	/**
	 * Default sorting for models
	 * @var array
	 */
	protected $_sorting = array(
		'template_sections.sorting' => 'ASC'
	);

	/**
	 * Delete object from database
	 * @param mixed $primaryKey primary key for deleting object
	 * @return Core_Entity
	 */
	public function delete($primaryKey = NULL)
	{
		if (is_null($primaryKey))
		{
			$primaryKey = $this->getPrimaryKey();
		}

		$this->id = $primaryKey;
		
		$this->Template_Section_Libs->deleteAll(FALSE);

		return parent::delete($primaryKey);
	}
	
	/**
	 * Backend callback method
	 * @param Admin_Form_Field $oAdmin_Form_Field
	 * @param Admin_Form_Controller $oAdmin_Form_Controller
	 * @return string
	 */
	public function template_section_libsBadge($oAdmin_Form_Field, $oAdmin_Form_Controller)
	{
		$count = $this->Template_Section_Libs->getCount();

		$count && Core::factory('Core_Html_Entity_Span')
			->class('badge badge-ico badge-azure white')
			->value($count < 100 ? $count : '∞')
			->title($count)
			->execute();
	}}