<?php
/**
* Online shop.
*
* @package HostCMS
* @version 6.x
* @author Hostmake LLC
* @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
*/
require_once('../../../../bootstrap.php');

Core_Auth::authorization($sModule = 'shop');

// Получаем параметры
$oShop = Core_Entity::factory('Shop', Core_Array::getRequest('shop_id', 0));
$oShopDir = $oShop->Shop_Dir;
$oShopGroup = Core_Entity::factory('Shop_Group', Core_Array::getRequest('shop_group_id', 0));

if(Core_Array::getPost('action') == 'export')
{
	switch(Core_Array::getPost('export_type'))
	{
		case 0:
			$aSeparator = array(",", ";");
			$iSeparator = Core_Array::getPost('export_price_separator', 0);
			$oShop_Item_Export_Csv_Controller = new Shop_Item_Export_Csv_Controller(Core_Array::getPost('shop_id', 0), !is_null(Core_Array::getPost('export_external_properties_allow_items')), !is_null(Core_Array::getPost('export_external_properties_allow_groups')), !is_null(Core_Array::getPost('export_modifications_allow')));
			$oShop_Item_Export_Csv_Controller
				->separator($iSeparator > 1 ? "" : $aSeparator[$iSeparator])
				->encoding(Core_Array::getPost('import_price_encoding', "UTF-8"))
				->parentGroup(Core_Array::getPost('shop_groups_parent_id', 0))
				->execute();
		break;
		case 1:

			$aSeparator = array(",", ";");
			$iSeparator = Core_Array::getPost('export_price_separator', 0);

			$oShop_Item_Export_Csv_Controller = new Shop_Item_Export_Csv_Controller(Core_Array::getPost('shop_id', 0), FALSE, FALSE, FALSE, TRUE);
			$oShop_Item_Export_Csv_Controller
				->separator($iSeparator > 1 ? "" : $aSeparator[$iSeparator])
				->start_order_date(Core_Array::getPost('order_begin_date', '01.01.1970'))
				->end_order_date(Core_Array::getPost('order_end_date', '01.01.1970'))
				->encoding(Core_Array::getPost('import_price_encoding', "UTF-8"))
				->execute();
		break;
		case 2:

			$oShop_Item_Export_Cml_Controller = new Shop_Item_Export_Cml_Controller(Core_Entity::factory('Shop', Core_Array::getPost('shop_id', 0)));
			$oShop_Item_Export_Cml_Controller->group = Core_Entity::factory('Shop_Group', $oShopGroup->id);
			$oShop_Item_Export_Cml_Controller->exportItemModifications = !is_null(Core_Array::getPost('export_modifications_allow'));
			$oShop_Item_Export_Cml_Controller->exportItemExternalProperties = !is_null(Core_Array::getPost('export_external_properties_allow_items'));

			header("Pragma: public");
			header("Content-Description: File Transfer");
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename = " . 'import_' .date("Y_m_d_H_i_s").'.xml'. ";");
			header("Content-Transfer-Encoding: binary");

			echo $oShop_Item_Export_Cml_Controller->exportImport();

			exit();
		break;
		case 3:
			$oShop_Item_Export_Cml_Controller = new Shop_Item_Export_Cml_Controller(Core_Entity::factory('Shop', Core_Array::getPost('shop_id', 0)));
			$oShop_Item_Export_Cml_Controller->group = Core_Entity::factory('Shop_Group', $oShopGroup->id);
			$oShop_Item_Export_Cml_Controller->exportItemModifications = !is_null(Core_Array::getPost('export_modifications_allow'));
			$oShop_Item_Export_Cml_Controller->exportItemExternalProperties = !is_null(Core_Array::getPost('export_external_properties_allow_items'));

			header("Pragma: public");
			header("Content-Description: File Transfer");
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename = " . 'offers_' .date("Y_m_d_H_i_s").'.xml'. ";");
			header("Content-Transfer-Encoding: binary");

			echo $oShop_Item_Export_Cml_Controller->exportOffers();

			exit();
		break;
	}
}

// Создаем экземпляры классов
$oAdmin_Form_Controller = Admin_Form_Controller::create();

// Контроллер формы
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path('/admin/shop/item/export/index.php')
;

ob_start();

$oAdmin_View = Admin_View::create();
$oAdmin_View
	->module(Core_Module::factory($sModule))
	->pageTitle(Core::_('Shop_Item.export_shop'))
	;

$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');

// Первая крошка на список магазинов
$oAdmin_Form_Entity_Breadcrumbs->add(
Admin_Form_Entity::factory('Breadcrumb')
	->name(Core::_('Shop.menu'))
	->href($oAdmin_Form_Controller->getAdminLoadHref(
		'/admin/shop/index.php'
	))
	->onclick($oAdmin_Form_Controller->getAdminLoadAjax(
					'/admin/shop/index.php'
	))
);

// Крошки по директориям магазинов
if($oShopDir->id)
{
	$oShopDirBreadcrumbs = $oShopDir;

	$aBreadcrumbs = array();

	do
	{
		$aBreadcrumbs[] = Admin_Form_Entity::factory('Breadcrumb')
		->name($oShopDirBreadcrumbs->name)
		->href($oAdmin_Form_Controller->getAdminLoadHref(
				'/admin/shop/index.php', NULL, NULL, "shop_dir_id={$oShopDirBreadcrumbs->id}"
		))
		->onclick($oAdmin_Form_Controller->getAdminLoadAjax(
				'/admin/shop/index.php', NULL, NULL, "shop_dir_id={$oShopDirBreadcrumbs->id}"
		));
	}while($oShopDirBreadcrumbs = $oShopDirBreadcrumbs->getParent());

	$aBreadcrumbs = array_reverse($aBreadcrumbs);

	foreach ($aBreadcrumbs as $oBreadcrumb)
	{
		$oAdmin_Form_Entity_Breadcrumbs->add($oBreadcrumb);
	}
}

// Крошка на список товаров и групп товаров магазина
$oAdmin_Form_Entity_Breadcrumbs->add(
Admin_Form_Entity::factory('Breadcrumb')
	->name($oShop->name)
	->href($oAdmin_Form_Controller->getAdminLoadHref(
		'/admin/shop/item/index.php', NULL, NULL, "shop_id={$oShop->id}"
	))
	->onclick($oAdmin_Form_Controller->getAdminLoadAjax(
		'/admin/shop/item/index.php', NULL, NULL, "shop_id={$oShop->id}"
	))
);

// Крошки по группам товаров
if($oShopGroup->id)
{
	$oShopGroupBreadcrumbs = $oShopGroup;

	$aBreadcrumbs = array();

	do
	{
		$aBreadcrumbs[] = Admin_Form_Entity::factory('Breadcrumb')
		->name($oShopGroupBreadcrumbs->name)
		->href($oAdmin_Form_Controller->getAdminLoadHref(
				'/admin/shop/item/index.php', NULL, NULL, "shop_id={$oShop->id}&shop_group_id={$oShopGroupBreadcrumbs->id}"
		))
		->onclick($oAdmin_Form_Controller->getAdminLoadAjax(
				'/admin/shop/item/index.php', NULL, NULL, "shop_id={$oShop->id}&shop_group_id={$oShopGroupBreadcrumbs->id}"
		));
	} while($oShopGroupBreadcrumbs = $oShopGroupBreadcrumbs->getParent());

	$aBreadcrumbs = array_reverse($aBreadcrumbs);

	foreach ($aBreadcrumbs as $oBreadcrumb)
	{
		$oAdmin_Form_Entity_Breadcrumbs->add($oBreadcrumb);
	}
}

// Крошка на текущую форму
$oAdmin_Form_Entity_Breadcrumbs->add(
Admin_Form_Entity::factory('Breadcrumb')
	->name(Core::_('Shop_Item.export_shop'))
	->href($oAdmin_Form_Controller->getAdminLoadHref(
	$oAdmin_Form_Controller->getPath(), NULL, NULL, "shop_id={$oShop->id}&shop_group_id={$oShopGroup->id}"
	))
	->onclick($oAdmin_Form_Controller->getAdminLoadAjax(
	$oAdmin_Form_Controller->getPath(), NULL, NULL, "shop_id={$oShop->id}&shop_group_id={$oShopGroup->id}"
	))
);

//ob_start();

/*// Заголовок
Admin_Form_Entity::factory('Title')
	->name(Core::_('Shop_Item.export_shop'))
	->execute();*/

$oAdmin_Form_Entity_Form = Admin_Form_Entity::factory('Form')
	->controller($oAdmin_Form_Controller)
	->action($oAdmin_Form_Controller->getPath())
	->target('_blank');

//$oAdmin_Form_Entity_Form->add($oAdmin_Form_Entity_Breadcrumbs);
$oAdmin_View->addChild($oAdmin_Form_Entity_Breadcrumbs);
$windowId = $oAdmin_Form_Controller->getWindowId();

$oMainTab = Admin_Form_Entity::factory('Tab')->name('main');

$oAdmin_Form_Entity_Form->add($oMainTab);

$oMainTab->add(
	Admin_Form_Entity::factory('Div')->class('row')
	->add(
		Admin_Form_Entity::factory('Radiogroup')
			->radio(array(
				Core::_('Shop_Item.import_price_list_file_type1_items'),
				Core::_('Shop_Item.import_price_list_file_type1_orders'),
				Core::_('Shop_Item.export_price_list_file_type3_import'),
				Core::_('Shop_Item.export_price_list_file_type3_offers')
			))
			->ico(array(
				'fa-asterisk',
				'fa-asterisk',
				'fa-asterisk',
				'fa-asterisk'
			))
			->caption(Core::_('Shop_Item.export_file_type'))
			->divAttr(array('class' => 'form-group col-lg-12 col-md-12 col-sm-12', 'id' => 'export_types'))
			->name('export_type')
			->onchange("ShowExport('{$windowId}', $(this).val())")
	))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Code')
			->html("<script>$(function() {
				$('#{$windowId} #export_types').buttonset();
			});</script>")))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Radiogroup')
			->radio(array(
				Core::_('Shop_Item.import_price_list_separator1'),
				Core::_('Shop_Item.import_price_list_separator2')
			))
			->ico(array(
				'fa-bolt',
				'fa-bolt'
			))
			->name('export_price_separator')
			->divAttr(array('class' => 'form-group col-lg-12 col-md-12 col-sm-12', 'id' => 'export_price_separator'))
			->caption(Core::_('Shop_Item.import_price_list_separator'))))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Code')
			->html("<script>$(function() {
				$('#{$windowId} #import_price_list_separator').buttonset();
			});</script>")))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Date')
			->caption(Core::_('Shop_Item.start_order_date'))
			->name('order_begin_date')
			->value(Core_Date::timestamp2sql(strtotime("-2 months")))
			->divAttr(array('class' => 'form-group col-lg-3 col-md-3 col-sm-3', 'id'=>'order_begin_date'))
	)->add(
		Admin_Form_Entity::factory('Date')
			->caption(Core::_('Shop_Item.stop_order_date'))
			->name('order_end_date')
			->value(Core_Date::timestamp2sql(time()))
			->divAttr(array('class' => 'form-group col-lg-3 col-md-3 col-sm-3','id'=>'order_end_date'))
	));

	class Shop_Item_Export_Csv_Property {

		protected $_linkedObject = NULL;

		public function __construct(Shop_Model $oShop)
		{
			$this->_linkedObject = Core_Entity::factory('Shop_Item_Property_List', $oShop->id);
		}

		public function setPropertyDirs($parent_id = 0, $parentObject)
		{
			$oAdmin_Form_Entity_Section = Admin_Form_Entity::factory('Section')
				->caption($parent_id == 0
					? Core::_('Property_Dir.main_section')
					: Core_Entity::factory('Property_Dir', $parent_id)->name
				)
				->id('accordion_' . $parent_id);

			// Properties
			$oProperties = $this->_linkedObject->Properties;
			$oProperties
				->queryBuilder()
				->where('property_dir_id', '=', $parent_id);

			$aProperties = $oProperties->findAll();

			foreach ($aProperties as $oProperty)
			{
				$oAdmin_Form_Entity_Section->add(
					Admin_Form_Entity::factory('Checkbox')
						->name("property_" . $oProperty->id)
						->caption($oProperty->name)
						->divAttr(array(
							'class' => 'form-group col-xs-12 col-sm-6 col-md-4 col-lg-4',
							'id' => 'property_' . $oProperty->id)
						)
						->value(FALSE)
				);
			}

			// Property Dirs
			$oProperty_Dirs = $this->_linkedObject->Property_Dirs;

			$oProperty_Dirs
				->queryBuilder()
				->where('parent_id', '=', $parent_id);

			$aProperty_Dirs = $oProperty_Dirs->findAll();
			foreach ($aProperty_Dirs as $oProperty_Dir)
			{
				$this->setPropertyDirs($oProperty_Dir->id,  $parent_id == 0 ? $parentObject : $oAdmin_Form_Entity_Section);
			}

			$oAdmin_Form_Entity_Section->getCountChildren() && $parentObject->add($oAdmin_Form_Entity_Section);
		}
	}

	$oMainTab->add($oPropertyBlock = Admin_Form_Entity::factory('Div')->class('well with-header'));

	$oPropertyBlock
		->id('property_block')
		->add(Admin_Form_Entity::factory('Div')
			->class('header bordered-warning')
			->value(Core::_("Shop_Item.property_header"))
		)
		->add($oPropertyCurrentRow = Admin_Form_Entity::factory('Div')->class('row'));

	$oShop_Item_Export_Csv_Property = new Shop_Item_Export_Csv_Property($oShop);
	$oShop_Item_Export_Csv_Property->setPropertyDirs(0, $oPropertyCurrentRow);

	// /Properties

	$oMainTab->add(
		Admin_Form_Entity::factory('Div')->class('row')->add(
			Admin_Form_Entity::factory('Select')
				->name("import_price_encoding")
				->options(array(
					'Windows-1251' => Core::_('Shop_Item.input_file_encoding0'),
					'UTF-8' => Core::_('Shop_Item.input_file_encoding1')
				))
				->divAttr(array('class' => 'form-group col-lg-6 col-md-6 col-sm-6', 'id' => 'import_price_encoding'))
				->caption(Core::_('Shop_Item.price_list_encoding')))
		)
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Select')
			->name("shop_groups_parent_id")
			->options(array(' … ') + Shop_Item_Controller_Edit::fillShopGroup($oShop->id))
			->divAttr(array('class' => 'form-group col-lg-6 col-md-6 col-sm-6', 'id' => 'shop_groups_parent_id'))
			->caption(Core::_('Shop_Item.import_price_list_parent_group'))
			->value($oShopGroup->id)))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Checkbox')
			->name("export_external_properties_allow_items")
			->caption(Core::_('Shop_Item.export_external_properties_allow_items'))
			->divAttr(array('class' => 'form-group col-lg-12 col-md-12 col-sm-12', 'id' => 'export_external_properties_allow_items'))
			->value(TRUE)))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Checkbox')
			->name("export_external_properties_allow_groups")
			->caption(Core::_('Shop_Item.export_external_properties_allow_groups'))
			->divAttr(array('class' => 'form-group col-lg-12 col-md-12 col-sm-12', 'id' => 'export_external_properties_allow_groups'))
			->value(TRUE)))
	->add(Admin_Form_Entity::factory('Div')->class('row')->add(
		Admin_Form_Entity::factory('Checkbox')
			->name("export_modifications_allow")
			->caption(Core::_('Shop_Item.export_modifications_allow'))
			->divAttr(array('class' => 'form-group col-lg-12 col-md-12 col-sm-12', 'id' => 'export_modifications_allow'))
			->value(TRUE)))
	->add(Admin_Form_Entity::factory('Div')->class('row')
		->add(Core::factory('Core_Html_Entity_Input')->type('hidden')->name('action')->value('export'))
		->add(Core::factory('Core_Html_Entity_Input')->type('hidden')->name('shop_group_id')->value(Core_Array::getGet('shop_group_id')))
		->add(Core::factory('Core_Html_Entity_Input')->type('hidden')->name('shop_id')->value(Core_Array::getGet('shop_id', 0)))
	);

$oAdmin_Form_Entity_Form->add(
		Admin_Form_Entity::factory('Button')
		->name('show_form')
		->type('submit')
		->class('applyButton btn btn-blue')
	)
	->add(
	Core::factory('Core_Html_Entity_Script')
		->type("text/javascript")
		->value("ShowExport('{$windowId}', 0)")
	);

$oAdmin_Form_Entity_Form->execute();
$content = ob_get_clean();

ob_start();
$oAdmin_View
	->content($content)
	->show();

/*$oAdmin_Answer = Core_Skin::instance()->answer();

$oAdmin_Answer
	->ajax(Core_Array::getRequest('_', FALSE))
	->content(ob_get_clean())
	//->message()
	->title(Core::_('Shop_Item.export_shop'))
	->execute();*/

Core_Skin::instance()
	->answer()
	->ajax(Core_Array::getRequest('_', FALSE))
	//->content(iconv("UTF-8", "UTF-8//IGNORE//TRANSLIT", ob_get_clean()))
	->content(ob_get_clean())
	->title(Core::_('Shop_Item.export_shop'))
	->execute();