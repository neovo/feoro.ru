<?php

$oShop = Core_Entity::factory('Shop', Core_Array::get(Core_Page::instance()->libParams, 'shopId'));

// Проверять остаток на складе при добавлении в корзину
$bCheckStock = FALSE;

Shop_Payment_System_Handler::checkBeforeContent($oShop);

// Добавление товара в корзину
if (Core_Array::getRequest('add'))
{
	$add = Core_Array::getRequest('add');
	!is_array($add) && $add = array($add);

	$count = Core_Array::getRequest('count', 1);
	!is_array($count) && $count = array($count);

	$oShop_Cart_Controller = Shop_Cart_Controller::instance();

	foreach ($add as $key => $shop_item_id)
	{
		$oShop_Cart_Controller
			->checkStock($bCheckStock)
			->shop_item_id($shop_item_id)
			->quantity(Core_Array::get($count, $key, 1))
			->add();
	}
}

// Ajax
if (Core_Array::getRequest('_', FALSE)
	&& (Core_Array::getRequest('add') || Core_Array::getRequest('loadCart')))
{
	ob_start();

	// Краткая корзина
	$Shop_Cart_Controller_Show = new Shop_Cart_Controller_Show(
		$oShop
	);
	$Shop_Cart_Controller_Show
		->xsl(
			Core_Entity::factory('Xsl')->getByName(
				Core_Array::get(Core_Page::instance()->libParams, 'littleCartXsl')
			)
		)
		->couponText(Core_Array::get($_SESSION, 'coupon_text'))
		->show();

	echo json_encode(ob_get_clean());
	exit();
}

if (Core_Array::getGet('action') == 'repeat')
{
	$guid = Core_Array::getGet('guid');
	if (strlen($guid))
	{
		$oShop_Order = $oShop->Shop_Orders->getByGuid($guid);

		if (!is_null($oShop_Order))
		{
			$aShop_Order_Items = $oShop_Order->Shop_Order_Items->findAll();

			$oShop_Cart_Controller = Shop_Cart_Controller::instance();

			foreach ($aShop_Order_Items as $oShop_Order_Item)
			{
				$oShop_Order_Item->shop_item_id && $oShop_Cart_Controller
					->checkStock($bCheckStock)
					->shop_item_id($oShop_Order_Item->shop_item_id)
					->quantity($oShop_Order_Item->quantity)
					->marking($oShop_Order_Item->marking)
					->add();
			}
		}
	}
}

if (!is_null(Core_Array::getGet('ajaxLoad')))
{
	$aObjects = array();

	if (Core_Array::getGet('shop_country_id'))
	{
		$oShop_Country_Location = Core_Entity::factory('Shop_Country_Location');
		$oShop_Country_Location
			->queryBuilder()
			->where('shop_country_id', '=', intval(Core_Array::getGet('shop_country_id')));
		$aObjects = $oShop_Country_Location->findAll();
	}
	elseif (Core_Array::getGet('shop_country_location_id'))
	{
		$oShop_Country_Location_City = Core_Entity::factory('Shop_Country_Location_City');
		$oShop_Country_Location_City
			->queryBuilder()
			->where('shop_country_location_id', '=', intval(Core_Array::getGet('shop_country_location_id')));
		$aObjects = $oShop_Country_Location_City->findAll();
	}
	elseif (Core_Array::getGet('shop_country_location_city_id'))
	{
		$oShop_Country_Location_City_Area = Core_Entity::factory('Shop_Country_Location_City_Area');
		$oShop_Country_Location_City_Area
			->queryBuilder()
			->where('shop_country_location_city_id', '=', intval(Core_Array::getGet('shop_country_location_city_id')));
		$aObjects = $oShop_Country_Location_City_Area->findAll();
	}

	$aArray = array('…');
	foreach ($aObjects as $Object)
	{
		//$aArray['_' . $Object->id] = $Object->name;
		$aArray['_' . $Object->id] = $Object->getName();
	}

	echo json_encode($aArray);
	exit();
}

// Удаляение товара из корзины
if (Core_Array::getGet('delete'))
{
	$shop_item_id = intval(Core_Array::getGet('delete'));

	if ($shop_item_id)
	{
		$oShop_Cart_Controller = Shop_Cart_Controller::instance();
		$oShop_Cart_Controller
			->shop_item_id($shop_item_id)
			->delete();
	}
}

// Запоминаем купон
if (!is_null(Core_Array::getRequest('coupon_text')))
{
	$_SESSION['hostcmsOrder']['coupon_text'] = trim(strval(Core_Array::getRequest('coupon_text')));
}

if (Core_Array::getPost('recount') || Core_Array::getPost('step') == 1)
{
	$oShop_Cart_Controller = Shop_Cart_Controller::instance();
	$aCart = $oShop_Cart_Controller->getAll($oShop);

	// Склад по умолчанию
	$oShop_Warehouse = $oShop->Shop_Warehouses->getDefault();

	foreach ($aCart as $oShop_Cart)
	{
		$quantity = Core_Array::getPost('quantity_' . $oShop_Cart->shop_item_id);

		// Количество было передано
		if (!is_null($quantity))
		{
			$oShop_Cart_Controller
				->checkStock($bCheckStock)
				->shop_item_id($oShop_Cart->shop_item_id)
				->quantity($quantity)
				->postpone(is_null(Core_Array::getPost('postpone_' . $oShop_Cart->shop_item_id)) ? 0 : 1)
				->shop_warehouse_id(
					Core_Array::getPost('warehouse_' . $oShop_Cart->shop_item_id, !is_null($oShop_Warehouse) ? $oShop_Warehouse->id : 0)
				)
				->update();
		}
	}
}

$Shop_Cart_Controller_Show = new Shop_Cart_Controller_Show($oShop);

Core_Page::instance()->object = $Shop_Cart_Controller_Show;