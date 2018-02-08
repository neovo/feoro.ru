<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Показ заказов пользователя в магазине.
 *
 * Доступные методы:
 *
 * - itemsProperties(TRUE|FALSE|array()) выводить значения дополнительных свойств товаров, по умолчанию FALSE. Может принимать массив с идентификаторами дополнительных свойств, значения которых необходимо вывести.
 * - offset($offset) смещение, с которого выводить товары. По умолчанию 0
 * - limit($limit) количество выводимых товаров
 * - page(2) текущая страница, по умолчанию 0, счет ведется с 0
 * - pattern($pattern) шаблон разбора данных в URI, см. __construct()
 *
 * @package HostCMS
 * @subpackage Shop
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Shop_Order_Controller_Show extends Core_Controller
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'itemsProperties',
		'offset',
		'limit',
		'page',
		'total',
		'pattern',
		'patternExpressions',
		'patternParams',
	);

	/**
	 * Shop orders
	 * @var Shop_Orders
	 */
	protected $_Shop_Orders = NULL;

	/**
	 * Constructor.
	 * @param Shop_Model $oShop shop
	 */
	public function __construct(Shop_Model $oShop)
	{
		parent::__construct($oShop->clearEntities());

		$this->_Shop_Orders = $oShop->Shop_Orders;

		$oSiteuser = Core_Entity::factory('Siteuser')->getCurrent();

		if (!is_null($oSiteuser))
		{
			$siteuser_id = $oSiteuser->id;
		}
		else
		{
			throw new Core_Exception('Siteuser does not exist.');
		}

		$this->addEntity(
			Core::factory('Core_Xml_Entity')
				->name('siteuser_id')
				->value($siteuser_id)
		);

		$this->_Shop_Orders
			->queryBuilder()
			->where('shop_orders.siteuser_id', '=', $siteuser_id)
			->orderBy('shop_orders.datetime', 'DESC');

		$this->itemsProperties = FALSE;
		$this->limit = 999;
		$this->offset = 0;
		$this->page = 0;

		$oStructure = Core_Entity::factory('Structure', CURRENT_STRUCTURE_ID);

		$sPath = $oStructure->getPath();

		$this->addEntity(
			Core::factory('Core_Xml_Entity')
				->name('path')
				->value($sPath)
		);

		$this->pattern = rawurldecode($sPath) . '(page-{page}/)';
		$this->patternExpressions = array(
			'page' => '\d+',
		);
	}

	/**
	 * Get orders
	 * @return Shop_Order_Model
	 */
	public function shopOrders()
	{
		return $this->_Shop_Orders;
	}

	/**
	 * Show built data
	 * @return self
	 * @hostcms-event Shop_Order_Controller_Show.onBeforeRedeclaredShow
	 */
	public function show()
	{
		Core_Event::notify(get_class($this) . '.onBeforeRedeclaredShow', $this);

		$oShop = $this->getEntity();

		$this->addEntity(
			Core::factory('Core_Xml_Entity')
				->name('page')
				->value(intval($this->page))
		)->addEntity(
			Core::factory('Core_Xml_Entity')
				->name('limit')
				->value(intval($this->limit))
		);

		// Load model columns BEFORE FOUND_ROWS()
		Core_Entity::factory('Shop_Order')->getTableColums();

		// Load user BEFORE FOUND_ROWS()
		$oUserCurrent = Core_Entity::factory('User', 0)->getCurrent();

		$this->_Shop_Orders
			->queryBuilder()
			->sqlCalcFoundRows()
			->offset(intval($this->offset))
			->limit(intval($this->limit));

		$aShop_Orders = $this->_Shop_Orders->findAll(FALSE);

		if ($this->page && !count($aShop_Orders))
		{
			return $this->error404();
		}

		$row = Core_QueryBuilder::select(array('FOUND_ROWS()', 'count'))->execute()->asAssoc()->current();
		$this->total = $row['count'];

		$this->addEntity(
			Core::factory('Core_Xml_Entity')
				->name('total')
				->value(intval($this->total))
		);

		// Paymentsystems
		$oShopPaymentSystemsEntity = Core::factory('Core_Xml_Entity')
			->name('shop_payment_systems');

		$this->addEntity(
			$oShopPaymentSystemsEntity
		);

		$aShop_Payment_Systems = $oShop->Shop_Payment_Systems->getAllByActive(1);
		foreach ($aShop_Payment_Systems as $oShop_Payment_System)
		{
			$oShopPaymentSystemsEntity->addEntity(
				$oShop_Payment_System->clearEntities()
			);
		}

		foreach ($aShop_Orders as $oShop_Order)
		{
			$oShop_Order
				->clearEntities()
				->showXmlCurrency(TRUE)
				->showXmlCountry(TRUE)
				->showXmlItems(TRUE)
				->showXmlDelivery(TRUE)
				->showXmlPaymentSystem(TRUE)
				->showXmlOrderStatus(TRUE);

			$this->itemsProperties && $oShop_Order->showXmlProperties($this->itemsProperties);

			$this->addEntity($oShop_Order);
		}

		return parent::show();
	}

	/**
	 * Parse URL and set controller properties
	 * @return self
	 * @hostcms-event Shop_Controller_Show.onBeforeParseUrl
	 * @hostcms-event Shop_Controller_Show.onAfterParseUrl
	 */
	public function parseUrl()
	{
		Core_Event::notify(get_class($this) . '.onBeforeParseUrl', $this);

		$oShop = $this->getEntity();

		$Core_Router_Route = new Core_Router_Route($this->pattern, $this->patternExpressions);
		$this->patternParams = $matches = $Core_Router_Route->applyPattern(Core::$url['path']);

		if (isset($matches['page']) && is_numeric($matches['page']))
		{
			if ($matches['page'] > 1)
			{
				$this->page($matches['page'] - 1)
					->offset($this->limit * $this->page);
			}
			else
			{
				return $this->error404();
			}
		}

		Core_Event::notify(get_class($this) . '.onAfterParseUrl', $this);

		return $this;
	}

	/**
	 * Define handler for 404 error
	 * @return self
	 */
	public function error404()
	{
		Core_Page::instance()->error404();

		return $this;
	}
}