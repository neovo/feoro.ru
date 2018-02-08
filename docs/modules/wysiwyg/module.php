<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Wysiwyg Module.
 *
 * @package HostCMS
 * @subpackage Wysiwyg
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Wysiwyg_Module extends Core_Module
{
	/**
	 * Module version
	 * @var string
	 */
	public $version = '6.6';

	/**
	 * Module date
	 * @var date
	 */
	public $date = '2016-09-12';
	
	/**
	 * Module name
	 * @var string
	 */
	protected $_moduleName = 'wysiwyg';
	
	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->menu = array(
			array(
				'sorting' => 0,
				'block' => -1,
				'ico' => 'fa fa-file-code-o',
			)
		);
	}
}