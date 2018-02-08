<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Экспорт в Yandex.Market для магазина.
 *
 * Доступные методы:
 *
 * - itemsProperties(TRUE|FALSE|array()) выводить значения дополнительных свойств товаров, по умолчанию TRUE.
 * - modifications(TRUE|FALSE) экспортировать модификации, по умолчанию TRUE.
 * - type('offer'|'vendor.model'|'book'|'audiobook'|'artist.title'|'tour'|'event-ticket') тип товара, по умолчанию 'offer'
 * - onStep(3000) количество товаров, выбираемых запросом за 1 шаг, по умолчанию 100
 *
 * <code>
 * $Shop_Controller_YandexMarket = new Shop_Controller_YandexMarket(
 * 	Core_Entity::factory('Shop', 1)
 * );
 *
 * $Shop_Controller_YandexMarket->show();
 * </code>
 *
 * @package HostCMS
 * @subpackage Shop
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Shop_Controller_YandexMarket extends Core_Controller
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'itemsProperties',
		'modifications',
		'deliveryOptions',
		'type',
		'onStep',
		'protocol'
	);

	/**
	 * Shop's items object
	 * @var Shop_Item_Model
	 */
	protected $_Shop_Items = NULL;

	/**
	 * Shop's groups object
	 * @var Shop_Group_Model
	 */
	protected $_Shop_Groups = NULL;

	/**
	 * Array of siteuser's groups allowed for current siteuser
	 * @var array
	 */
	protected $_aSiteuserGroups = array();

	/**
	 * List's offer tags
	 * @var array
	 */
	public $aOfferTags = array(
		'adult' => 'adult',
		'cpa' => 'cpa',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
		'barcode' => 'barcode',
	);

	/**
	 * List's vendor tags
	 * @var array
	 */
	public $aVendorTags = array(
		'typePrefix' => 'typePrefix',
		'model' => 'model',
		'adult' => 'adult',
		'cpa' => 'cpa',
		'rec' => 'rec',
		'expiry' => 'expiry',
		'weight' => 'weight',
		'dimensions' => 'dimensions',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
	);

	/**
	 * List's book tags
	 * @var array
	 */
	public $aBookTags = array(
		//http://help.yandex.ru/partnermarket/offers.xml#book
		'author' => 'author',
		'publisher' => 'publisher',
		'series' => 'series',
		'year' => 'year',
		'ISBN' => 'ISBN',
		'volume' => 'volume',
		'part' => 'part',
		'language' => 'language',
		'binding' => 'binding',
		'page_extent' => 'page_extent',
		'table_of_contents' => 'table_of_contents',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
	);

	/**
	 * List's audiobook tags
	 * @var array
	 */
	public $aAudiobookTags = array(
		//http://help.yandex.ru/partnermarket/offers.xml#audiobook
		'author' => 'author',
		'publisher' => 'publisher',
		'series' => 'series',
		'year' => 'year',
		'ISBN' => 'ISBN',
		'volume' => 'volume',
		'part' => 'part',
		'language' => 'language',
		'table_of_contents' => 'table_of_contents',
		'performed_by' => 'performed_by',
		'performance_type' => 'performance_type',
		'storage' => 'storage',
		'format' => 'format', //Время звучания задается в формате mm.ss (минуты.секунды).
		'recording_length' => 'recording_length',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
	);

	/**
	 * List's artist.title tags
	 * @var array
	 */
	public $aArtistTitleTags = array(
		'artist' => 'artist',
		'title' => 'title',
		'year' => 'year',
		'media' => 'media',
		'starring' => 'starring',
		'director' => 'director',
		'originalName' => 'originalName',
		'country' => 'country',
		'adult' => 'adult',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
		'barcode' => 'barcode',
	);

	/**
	 * List's tour tags
	 * @var array
	 */
	public $aTourTags = array(
		'worldRegion' => 'worldRegion',
		'country' => 'country',
		'region' => 'region',
		'days' => 'days',
		'dataTour' => 'dataTour', //Даты заездов. Предпочтительный формат: YYYY-MM-DD hh:mm:ss.
		'hotel_stars' => 'hotel_stars',
		'room' => 'room',
		'meal' => 'meal',
		'included' => 'included',
		'transport' => 'transport',
		'price_min' => 'price_min',
		'price_max' => 'price_max',
		'options' => 'options',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
	);

	/**
	 * List's event ticket tags
	 * @var array
	 */
	public $aEventTicketTags = array(
		'place' => 'place',
		'hall' => 'hall',
		'hall_part' => 'hall_part',
		'date' => 'date', //Дата и время сеанса. Предпочтительный формат: YYYY-MM-DD hh:mm:ss.
		'is_premiere' => 'is_premiere',
		'is_kids' => 'is_kids',
		'age-year' => 'age-year',
		'age-month' => 'age-month',
	);

	/**
	 * Shop_Item_Controller
	 * @var Shop_Item_Controller
	 */
	protected $_Shop_Item_Controller = NULL;

	/**
	 * Constructor.
	 * @param Shop_Model $oShop shop
	 */
	public function __construct(Shop_Model $oShop)
	{
		parent::__construct($oShop->clearEntities());

		$this->protocol = Core::httpsUses() ? 'https' : 'http';

		$siteuser_id = 0;

		$this->_aSiteuserGroups = array(0, -1);
		if (Core::moduleIsActive('siteuser'))
		{
			$oSiteuser = Core_Entity::factory('Siteuser')->getCurrent();

			if ($oSiteuser)
			{
				$siteuser_id = $oSiteuser->id;

				$aSiteuser_Groups = $oSiteuser->Siteuser_Groups->findAll(FALSE);
				foreach ($aSiteuser_Groups as $oSiteuser_Group)
				{
					$this->_aSiteuserGroups[] = $oSiteuser_Group->id;
				}
			}
		}

		$this->_setShopItems();

		$this->_setShopGroups();

		$this->itemsProperties = $this->modifications = $this->deliveryOptions = TRUE;

		$this->type = 'offer';
		$this->onStep = 10;

		$this->_Shop_Item_Controller = new Shop_Item_Controller();
		
		Core_Session::close();
	}

	/**
	 * Prepare $this->Shop_Groups
	 * @return self
	 */
	protected function _setShopGroups()
	{
		$oShop = $this->getEntity();

		$this->_Shop_Groups = $oShop->Shop_Groups;
		$this->_Shop_Groups
			->queryBuilder()
			->where('shop_groups.siteuser_group_id', 'IN', $this->_aSiteuserGroups)
			//->where('shop_groups.active', '=', 1)
			->clearOrderBy()
			->orderBy('shop_groups.parent_id', 'ASC');

		return $this;
	}

	/**
	 * Prepare $this->_Shop_Items
	 * @return self
	 */
	protected function _setShopItems()
	{
		$oShop = $this->getEntity();

		$this->_Shop_Items = $oShop->Shop_Items;
		$this->_Shop_Items
			->queryBuilder()
			->clearOrderBy()
			->orderBy('shop_items.id', 'ASC');

		$dateTime = Core_Date::timestamp2sql(time());
		$this->_Shop_Items
			->queryBuilder()
			->select('shop_items.*')
			->leftJoin('shop_groups', 'shop_groups.id', '=', 'shop_items.shop_group_id'/*,
				array(
						array('AND' => array('shop_groups.active', '=', 1)),
						array('OR' => array('shop_items.shop_group_id', '=', 0))
					)*/
			)
			// Активность группы или группа корневая
			->open()
			->where('shop_groups.active', '=', 1)
			->setOr()
			->where('shop_groups.id', 'IS', NULL)
			->close()

			->where('shop_items.shortcut_id', '=', 0)
			->where('shop_items.active', '=', 1)
			->where('shop_items.siteuser_id', 'IN', $this->_aSiteuserGroups)
			->open()
			->where('shop_items.start_datetime', '<', $dateTime)
			->setOr()
			->where('shop_items.start_datetime', '=', '0000-00-00 00:00:00')
			->close()
			->setAnd()
			->open()
			->where('shop_items.end_datetime', '>', $dateTime)
			->setOr()
			->where('shop_items.end_datetime', '=', '0000-00-00 00:00:00')
			->close()
			->where('shop_items.yandex_market', '=', 1)
			->where('shop_items.price', '>', 0)
			->where('shop_items.modification_id', '=', 0);

		return $this;
	}

	/**
	 * Get items set
	 * @return Shop_Item_Model
	 */
	public function shopItems()
	{
		return $this->_Shop_Items;
	}

	/**
	 * Get groups set
	 * @return Shop_Item_Model
	 */
	public function shopGroups()
	{
		return $this->_Shop_Groups;
	}

	/**
	 * Show currencies
	 * @return self
	 */
	protected function _currencies()
	{
		echo '<currencies>'. "\n";
		$aShop_Currencies = Core_Entity::factory('Shop_Currency')->findAll(FALSE);

		$aCurrenciesCodes = array(
			'RUR',
			'RUB',
			'USD',
			'BYR',
			'BYN',
			'KZT',
			'EUR',
			'UAH',
		);

		foreach ($aShop_Currencies as $oShop_Currency)
		{
			if (trim($oShop_Currency->code) != ''
			&& in_array($oShop_Currency->code, $aCurrenciesCodes))
			{
				echo '<currency id="' . Core_Str::xml($oShop_Currency->code) .
					'" rate="' . Core_Str::xml($oShop_Currency->exchange_rate) .'"'. "/>\n";
			}
		}
		echo '</currencies>'. "\n";

		return $this;
	}

	/**
	 * Cache of categories IDs
	 */
	protected $_aCategoriesId = array();

	/**
	 * get max group id
	 * @return int
	 */
	protected function _getMaxGroupId()
	{
		$oShop = $this->getEntity();

		$oCore_QueryBuilder_Select = Core_QueryBuilder::select(array('MAX(id)', 'max_id'));
		$oCore_QueryBuilder_Select
			->from('shop_groups')
			->where('shop_groups.shop_id', '=', $oShop->id)
			->where('shop_groups.deleted', '=', 0);

		$aRow = $oCore_QueryBuilder_Select->execute()->asAssoc()->current();

		return $aRow['max_id'];
	}

	/**
	 * Show categories
	 * @return self
	 */
	protected function _categories()
	{
		echo "<categories>\n";

		// Название магазина
		$oShop = $this->getEntity();

		echo '<category id="0">' . Core_Str::xml(!empty($oShop->yandex_market_name) ? $oShop->yandex_market_name : $oShop->Site->name) . "</category>\n";

		// Массив активных ID групп
		$this->_aCategoriesId = array();

		// Массив отключенных ID групп
		$aDisabledCategoriesId = array();

		$maxId = $this->_getMaxGroupId();

		$iFrom = 0;

		do {
			$this->_setShopGroups();
			$this->_Shop_Groups->queryBuilder()
				->where('shop_groups.id', 'BETWEEN', array($iFrom + 1, $iFrom + $this->onStep));

			$aShop_Groups = $this->_Shop_Groups->findAll(FALSE);
			foreach ($aShop_Groups as $oShop_Group)
			{
				if ($oShop_Group->active
					// Группа в корневой или в списке отключенных нет ее родителя
					&& ($oShop_Group->parent_id == 0 || !isset($aDisabledCategoriesId[$oShop_Group->parent_id]))
				)
				{
					$this->_aCategoriesId[$oShop_Group->id] = $oShop_Group->id;

					$group_parent_id = $oShop_Group->parent_id == '' || $oShop_Group->parent_id == 0
						? ''
						: ' parentId="' . $oShop_Group->parent_id . '"';

					echo '<category id="' . $oShop_Group->id . '"' . $group_parent_id . '>' . Core_Str::xml($oShop_Group->name) . "</category>\n";
				}
				else
				{
					// Группа в отключенные если она сама отключена или родитель отключен
					$aDisabledCategoriesId[$oShop_Group->id] = $oShop_Group->id;
				}
			}

			$iFrom += $this->onStep;
		}
		while ($iFrom < $maxId);

		echo "</categories>\n";

		unset($aShop_Groups);

		return $this;
	}

	/**
	 * Property_Model for <market_category>
	 * @var mixed
	 */
	protected $_MarketCategory = NULL;

	/**
	 * get max item id
	 * @return int
	 */
	protected function _getMaxId()
	{
		$oShop = $this->getEntity();

		$oCore_QueryBuilder_Select = Core_QueryBuilder::select(array('MAX(id)', 'max_id'));
		$oCore_QueryBuilder_Select
			->from('shop_items')
			->where('shop_items.shop_id', '=', $oShop->id)
			->where('shop_items.deleted', '=', 0);

		$aRow = $oCore_QueryBuilder_Select->execute()->asAssoc()->current();

		return $aRow['max_id'];
	}

	/**
	 * Show offers
	 * @return self
	 * @hostcms-event Shop_Controller_YandexMarket.onBeforeOffer
	 * @hostcms-event Shop_Controller_YandexMarket.onAfterOffer
	 */
	protected function _offers()
	{
		echo "<offers>\n";

		//$offset = 0;

		$oShop = $this->getEntity();
		$oShop_Item_Property_List = Core_Entity::factory('Shop_Item_Property_List', $oShop->id);

		$this->_MarketCategory = $oShop_Item_Property_List->Properties->getByTag_name('market_category');

		$maxId = $this->_getMaxId();

		$iFrom = 0;

		do {
			$this->_setShopItems();
			$this->_Shop_Items->queryBuilder()
				->where('shop_items.id', 'BETWEEN', array($iFrom + 1, $iFrom + $this->onStep));

			$aShop_Items = $this->_Shop_Items->findAll(FALSE);

			foreach ($aShop_Items as $oShop_Item)
			{
				if (isset($this->_aCategoriesId[$oShop_Item->shop_group_id]))
				{
					$this->_showOffer($oShop_Item);

					if ($this->modifications)
					{
						$oModifications = $oShop_Item->Modifications;
						$oModifications->queryBuilder()
							->where('shop_items.yandex_market', '=', 1);

						$aModifications = $oModifications->findAll(FALSE);
						foreach ($aModifications as $oModification)
						{
							$this->_showOffer($oModification);
						}
					}
				}
			}

			Core_File::flush();
			$iFrom += $this->onStep;
		}
		while ($iFrom < $maxId);

		echo '</offers>'. "\n";

		return $this;
	}

	protected function _showOffer($oShop_Item)
	{
		$oShop = $this->getEntity();

		/* Устанавливаем атрибуты тега <offer>*/
		$tag_bid = $oShop_Item->yandex_market_bid
			? ' bid="' . Core_Str::xml($oShop_Item->yandex_market_bid) . '"'
			: '';

		$tag_cbid = $oShop_Item->yandex_market_cid
			? ' cbid="' . Core_Str::xml($oShop_Item->yandex_market_cid) . '"'
			: '';

		$available = $oShop_Item->getRest() > 0 ? 'true' : 'false';

		$sType = $this->type != 'offer'
			? ' type="' . Core_Str::xml($this->type) . '"'
			: '';

		echo '<offer id="' . $oShop_Item->id . '"'. $tag_bid . $tag_cbid . $sType . " available=\"{$available}\">\n";

		Core_Event::notify(get_class($this) . '.onBeforeOffer', $this, array($oShop_Item));

		/* URL */
		echo '<url>' . Core_Str::xml($this->_shopPath . $oShop_Item->getPath()) . '</url>'. "\n";

		/* Определяем цену со скидкой */
		$aPrices = $this->_Shop_Item_Controller->calculatePriceInItemCurrency($oShop_Item->price, $oShop_Item);

		/* Цена */
		echo '<price>' . $aPrices['price_discount'] . '</price>'. "\n";

		if ($aPrices['price'] > $aPrices['price_discount'])
		{
			/* Старая цена */
			echo '<oldprice>' . $aPrices['price'] . '</oldprice>'. "\n";
		}

		/* CURRENCY */
		// Обязательно поле в модели:
		// (url?,buyurl?,price,wprice?,currencyId,xCategory?,categoryId+ ...
		echo '<currencyId>'. Core_Str::xml($oShop_Item->Shop_Currency->code) . '</currencyId>'. "\n";

		/* Идентификатор категории */
		// Основной товар
		if ($oShop_Item->modification_id == 0)
		{
			$categoryId = $oShop_Item->shop_group_id;
		}
		else // Модификация, берем ID родительской группы
		{
			$categoryId = $oShop_Item->Modification->Shop_Group->id
				? $oShop_Item->Modification->Shop_Group->id
				: 0;
		}
		echo '<categoryId>' . $categoryId . '</categoryId>'. "\n";

		if (!is_null($this->_MarketCategory))
		{
			$aProperty_Value_Market_Category = $this->_MarketCategory->getValues($oShop_Item->id);
			if (isset($aProperty_Value_Market_Category[0]))
			{
				echo '<market_category>' .
					Core_Str::xml($aProperty_Value_Market_Category[0]->value) .
				'</market_category>'. "\n";
			}
		}

		/* PICTURE */
		if ($oShop_Item->image_large != '')
		{
			echo '<picture>' . $this->protocol . '://' . Core_Str::xml($this->_siteAlias->name . $oShop_Item->getLargeFileHref()) . '</picture>'. "\n";
		}

		/* Delivery options */
		if ($this->deliveryOptions)
		{
			echo '<store>' . ($oShop_Item->store == 1 ? 'true' : 'false') . '</store>'. "\n";
			echo '<pickup>' . ($oShop_Item->pickup == 1 ? 'true' : 'false') . '</pickup>'. "\n";
			echo '<delivery>' . ($oShop_Item->delivery == 1 ? 'true' : 'false') . '</delivery>'. "\n";

			$this->_deliveryOptions($oShop, $oShop_Item);
		}

		// (name, vendor?, vendorCode?)
		if (mb_strlen($oShop_Item->name) > 0)
		{
			/* NAME */
			echo '<name>' . Core_Str::xml($oShop_Item->name) . '</name>'. "\n";

			if ($oShop_Item->shop_producer_id)
			{
				echo '<vendor>' . Core_Str::xml($oShop_Item->Shop_Producer->name) . '</vendor>'. "\n";
			}

			if ($oShop_Item->vendorcode != '')
			{
				echo '<vendorCode>' . Core_Str::xml($oShop_Item->vendorcode) . '</vendorCode>'. "\n";
			}
		}

		/* DESCRIPTION */
		$description = !empty($oShop_Item->description)
			? $oShop_Item->description
			: $oShop_Item->text;

		if (strlen($description))
		{
			$description = Core_Str::cutSentences(
				html_entity_decode(strip_tags($description), ENT_COMPAT, 'UTF-8'), 175
			);

			echo '<description>' . Core_Str::xml($description) . '</description>'. "\n";
		}

		/* sales_notes */
		$sales_notes = mb_strlen($oShop_Item->yandex_market_sales_notes) > 0
			? $oShop_Item->yandex_market_sales_notes
			: $oShop->yandex_market_sales_notes_default;

		echo '<sales_notes>' . Core_Str::xml(html_entity_decode(strip_tags($sales_notes), ENT_COMPAT, 'UTF-8')) . '</sales_notes>'. "\n";

		if ($oShop_Item->manufacturer_warranty)
		{
			echo '<manufacturer_warranty>true</manufacturer_warranty>' . "\n";
		}

		if (trim($oShop_Item->country_of_origin) != '')
		{
			echo '<country_of_origin>' . Core_Str::xml(html_entity_decode(strip_tags($oShop_Item->country_of_origin), ENT_COMPAT, 'UTF-8')) . '</country_of_origin>'. "\n";
		}

		// Элемент предназначен для обозначения товара, который можно скачать. Если указано значение параметра true, товарное предложение показывается во всех регионах независимо от регионов доставки, указанных магазином на странице Параметры размещения.
		if ($oShop_Item->type == 1)
		{
			echo '<downloadable>true</downloadable>'. "\n";
		}

		$this->itemsProperties && $this->_addPropertyValue($oShop_Item);

		Core_Event::notify(get_class($this) . '.onAfterOffer', $this, array($oShop_Item));

		echo '</offer>'. "\n";
	}

	/**
	 * Show delivery options
	 * @param Shop_Model $oShop
	 * @param Shop_Item_Model $oShop_Item
	 * @return self
	 */
	protected function _deliveryOptions(Shop_Model $oShop, $oShop_Item = NULL)
	{
		$oShop_Item_Delivery_Options = $oShop->Shop_Item_Delivery_Options;

		$oShop_Item_Delivery_Options->queryBuilder()
			->where('shop_item_delivery_options.shop_item_id', '=', !is_null($oShop_Item) ? $oShop_Item->id : 0);

		$aShop_Item_Delivery_Options = $oShop_Item_Delivery_Options->findAll(FALSE);

		if (count($aShop_Item_Delivery_Options))
		{
			echo '<delivery-options>';

			foreach ($aShop_Item_Delivery_Options as $oShop_Item_Delivery_Option)
			{
				echo '<option cost="' . $oShop_Item_Delivery_Option->cost . '" days="' . $oShop_Item_Delivery_Option->day . '" order-before="' . $oShop_Item_Delivery_Option->order_before . '"/>' . "\n";
			}

			echo '</delivery-options>';
		}

		return count($aShop_Item_Delivery_Options);
	}

	/**
	 * Допустимые значения возраста в годах
	 * @var array
	 */
	protected $_aAgeYears = array(0, 6, 12, 16, 18);

	/**
	 * Допустимые значения возраста в месяцах
	 * @var array
	 */
	protected $_aAgeMonthes = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

	/**
	 * Исключаемые теги
	 * @var array
	 */
	protected $_aForbid = array('age-month', 'age-year');

	/**
	 * Cache Properties
	 * @var array
	 */
	protected $_cacheProperties = array();

	/**
	 * Get Property_Model by ID
	 * @param int $property_id
	 */
	protected function _getProperty($property_id)
	{
		if (!isset($this->_cacheProperties[$property_id]))
		{
			$this->_cacheProperties[$property_id] = Core_Entity::factory('Property', $property_id);
		}

		return $this->_cacheProperties[$property_id];
	}

	/**
	 * Cache List Items
	 * @var array
	 */
	protected $_cacheListItems = array();

	/**
	 * Get List_Item value by ID
	 * @param int $property_id
	 */
	protected function _getCacheListItem($oProperty, $listItemId)
	{
		if (!isset($this->_cacheListItems[$oProperty->list_id][$listItemId]))
		{
			$this->_cacheListItems[$oProperty->list_id][$listItemId] = NULL;

			if ($listItemId)
			{
				$oList_Item = $oProperty->List->List_Items->getById(
					$listItemId/*, FALSE*/
				);

				!is_null($oList_Item)
					&& $this->_cacheListItems[$oProperty->list_id][$listItemId] = $oList_Item->value;
			}
		}

		return $this->_cacheListItems[$oProperty->list_id][$listItemId];
	}

	/**
	 * Print Shop_Item properties
	 * @param Shop_Item_Model $oShop_Item
	 * @return self
	 */
	protected function _addPropertyValue(Shop_Item_Model $oShop_Item)
	{
		// Доп. св-ва выводятся в <param>
		// <param name="Максимальный формат">А4</param>
		//$aProperty_Values = $oShop_Item->getPropertyValues(FALSE);

		$aProperty_Values = is_array($this->itemsProperties)
			? Property_Controller_Value::getPropertiesValues($this->itemsProperties, $oShop_Item->id, FALSE)
			: $oShop_Item->getPropertyValues(FALSE);

		$bAge = FALSE;

		foreach ($aProperty_Values as $oProperty_Value)
		{
			//$oProperty = $oProperty_Value->Property;
			$oProperty = $this->_getProperty($oProperty_Value->property_id);

			switch ($oProperty->type)
			{
				case 0: // Int
				case 1: // String
				case 4: // Textarea
				case 6: // Wysiwyg
				case 8: // Date
				case 9: // Datetime
					$value = $oProperty_Value->value;
				break;

				case 3: // List
					//$oList_Item = $oProperty->List->List_Items->getById(
					//	$oProperty_Value->value/*, FALSE*/
					//);

					//$value = !is_null($oList_Item)
					//	? $oList_Item->value
					//	: NULL;
					$value = $this->_getCacheListItem($oProperty, $oProperty_Value->value);
				break;

				case 7: // Checkbox
					$value = $oProperty_Value->value == 1 ? 'есть' : NULL;
				break;

				case 2: // File
				case 5: // ИС
				case 10: // Hidden field
				default:
					$value = NULL;
				break;
			}

			if (!is_null($value))
			{
				$sTagName = 'param';

				$unit = $oProperty->type == 0 && $oProperty->Shop_Item_Property->shop_measure_id
					? ' unit="' . Core_Str::xml($oProperty->Shop_Item_Property->Shop_Measure->name) . '"'
					: '';

				$sAttr = ' name="' . Core_Str::xml($oProperty->name) . '"' . $unit;

				if ($this->type != 'offer')
				{
					switch ($this->type)
					{
						case 'vendor.model':
							$aTmpArray = $this->aVendorTags;
						break;
						case 'book':
							$aTmpArray = $this->aBookTags;
						break;
						case 'audiobook':
							$aTmpArray = $this->aAudiobookTags;
						break;
						case 'artist.title':
							$aTmpArray = $this->aArtistTitleTags;
						break;
						case 'tour':
							$aTmpArray = $this->aTourTags;
						break;
						case 'event-ticket':
							$aTmpArray = $this->aEventTicketTags;
						break;
						default:
							throw new Core_Exception("Wrong type '%type'",
								array('%type' => $this->type)
							);
					}

					if (isset($aTmpArray[$oProperty->tag_name]))
					{
						$sTagName = $aTmpArray[$oProperty->tag_name];
						$sAttr = '';
					}
				}

				if ($value !== '')
				{
					if (!in_array($sTagName, $this->_aForbid))
					{
						echo '<' . $sTagName . $sAttr . '>'
							. Core_Str::xml(html_entity_decode(strip_tags($value), ENT_COMPAT, 'UTF-8'))
						. '</' . $sTagName . '>'. "\n";
					}
					elseif ($sTagName == 'age-year' && $value !== '' && in_array($value, $this->_aAgeYears))
					{
						echo '<age unit="year">' . intval($value) . '</age>'. "\n";
						$bAge = TRUE;
					}
					elseif (!$bAge && $sTagName == 'age-month' && $value !== '' && in_array($value, $this->_aAgeMonthes))
					{
						echo '<age unit="month">' . intval($value) . '</age>'. "\n";
					}
				}
			}
		}

		return $this;
	}

	/**
	 * Current site alias
	 * @var string
	 */
	protected $_siteAlias = NULL;

	/**
	 * Shop URL
	 * @var string
	 */
	protected $_shopPath = NULL;

	/**
	 * Show built data
	 * @return self
	 * @hostcms-event Shop_Controller_YandexMarket.onBeforeRedeclaredShow
	 */
	public function show()
	{
		Core_Event::notify(get_class($this) . '.onBeforeRedeclaredShow', $this);

		$oShop = $this->getEntity();
		$oSite = $oShop->Site;

		!is_null(Core_Page::instance()->response) && Core_Page::instance()->response
			->header('Content-Type', "text/xml; charset={$oSite->coding}")
			->sendHeaders();

		echo '<?xml version="1.0" encoding="' . $oSite->coding . '"?>' . "\n";
		echo '<!DOCTYPE yml_catalog SYSTEM "shops.dtd">' . "\n";
		echo '<yml_catalog date="' . date("Y-m-d H:i") . '">' . "\n";
		echo "<shop>\n";

		// Название магазина
		$shop_name = trim(
			!empty($oShop->yandex_market_name)
				? $oShop->yandex_market_name
				: $oSite->name
		);

		echo "<name>" . Core_Str::xml(mb_substr($shop_name, 0, 20)) . "</name>\n";

		// Название компании.
		echo "<company>" . Core_Str::xml($oShop->Shop_Company->name) . "</company>\n";

		$this->_siteAlias = $oSite->getCurrentAlias();
		$this->_shopPath = $this->protocol . '://' . $this->_siteAlias->name . $oShop->Structure->getPath();

		echo "<url>" . Core_Str::xml($this->_shopPath) . "</url>\n";
		echo "<platform>HostCMS</platform>\n";
		echo "<version>" . Core_Str::xml(CURRENT_VERSION) . "</version>\n";

		/* Валюты */
		$this->_currencies();

		/* Категории */
		$this->_categories();

		/* Delivery options */
		$this->deliveryOptions
			// Disable if there aren't shop's delivery options
			&& $this->deliveryOptions = $this->_deliveryOptions($oShop) > 0;

		Core_File::flush();

		/* Товары */
		$this->_offers();

		echo "</shop>\n";
		echo '</yml_catalog>';

		Core_File::flush();
	}
}