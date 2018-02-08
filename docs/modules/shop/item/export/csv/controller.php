<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Online shop.
 *
 * @package HostCMS
 * @subpackage Shop
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Shop_Item_Export_Csv_Controller extends Core_Servant_Properties
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'separator',
		'encoding',
		'parentGroup',
		'exportItemExternalProperties',
		'exportGroupExternalProperties',
		'exportItemModifications',
		'exportOrders',
		'shopId',
		'start_order_date',
		'end_order_date'
	);

	/**
	 * User prices
	 * Цены групп пользователей
	 * @var array
	 */
	private $_aShopPrices = array();

	/**
	 * Warehouses
	 * Склады
	 * @var array
	 */
	private $_aShopWarehouses = array();

	/**
	 * Additional properties of items
	 * Дополнительные свойства товаров
	 * @var array
	 */
	private $_aItem_Properties	= array();

	/**
	 * Additional properties of item groups
	 * Дополнительные свойства групп товаров
	 * @var array
	 */
	private $_aGroup_Properties = array();

	/**
	 * Item properties count
	 * Требуется хранить количество свойств отдельно, т.к. количество полей файла CSV для свойств не равно количеству свойств (из-за файлов)
	 * @var int
	 */
	private $_iItem_Properties_Count;

	/**
	 * Group properties count
	 * @var int
	 */
	private $_iGroup_Properties_Count;

	/**
	 * Base properties of items
	 * Основные свойства товара
	 * @var array
	 */
	private $_aItemBase_Properties;

	/**
	 * Base properties of item groups
	 * Основные свойства групп товаров
	 * @var array
	 */
	private $_aGroupBase_Properties;

	/**
	 * Special prices of item
	 * Основные свойства дополнительных цен товаров
	 * @var array
	 */
	private $_aSpecialPriceBase_Properties;

	/**
	 * CSV data
	 * @var array
	 */
	private $_aCurrentData;

	/**
	 * Data pointer
	 * @var int
	 */
	private $_iCurrentDataPosition;

	/**
	 * Constructor.
	 * @param int $iShopId shop ID
	 * @param boolean $bItemPropertiesExport export item properties mode
	 * @param boolean $bGroupPropertiesExport export group properties mode
	 * @param boolean $bExportItemModifications export item modifications
	 * @param boolean $bExportOrders export orders instead of catalog
	 */
	public function __construct(
		$iShopId,
		$bItemPropertiesExport = TRUE,
		$bGroupPropertiesExport = TRUE,
		$bExportItemModifications = TRUE,
		$bExportOrders = FALSE)
	{
		parent::__construct();

		$this->shopId = $iShopId;
		$this->exportItemExternalProperties = $bItemPropertiesExport;
		$this->exportGroupExternalProperties = $bGroupPropertiesExport;
		$this->exportItemModifications = $bExportItemModifications;
		$this->_iItem_Properties_Count = 0;
		$this->_iGroup_Properties_Count = 0;
		$this->start_order_date = NULL;
		$this->end_order_date = NULL;

		$this->exportOrders = $bExportOrders;

		// Устанавливаем лимит времени выполнения в 1 час
		(!defined('DENY_INI_SET') || !DENY_INI_SET) && function_exists('set_time_limit') && ini_get('safe_mode') != 1 && @set_time_limit(3600);

		if(!$this->exportOrders)
		{
			// Заполняем склады
			$this->_aShopWarehouses = Core_Entity::factory('Shop', $this->shopId)
				->Shop_Warehouses
				->findAll(FALSE);

			// Заполняем дополнительные свойства товара
			$this->exportItemExternalProperties && $this->_aItem_Properties = Core_Entity::factory('Shop_Item_Property_List', $this->shopId)->Properties->findAll(FALSE);

			// Заполняем дополнительные свойства групп товаров
			$this->exportGroupExternalProperties && $this->_aGroup_Properties = Core_Entity::factory('Shop_Group_Property_List', $this->shopId)->Properties->findAll(FALSE);

			$this->_aSpecialPriceBase_Properties = array(
				"", "", "", ""
			);

			$this->_aGroupBase_Properties = array(
				"","","","","","","","",""
			);

			$this->_aItemBase_Properties = array(
				"","","","","","","","","",""
				,"","","","","","","","","",""
				,"","","","","","","","","",""
				,""
			);

			$this->_iCurrentDataPosition = 0;

			$this->_aShopPrices = Core_Entity::factory('Shop', $this->shopId)->Shop_prices->findAll(FALSE);

			// 0-вая строка - заголовок CSV-файла
			$this->_aCurrentData[$this->_iCurrentDataPosition] = array(
				'"Название раздела"',
				'"CML GROUP ID идентификатор группы товаров"',
				'"CML GROUP ID идентификатор родительской группы товаров"',
				'"Заголовок раздела(title)"',
				'"Описание раздела(description)"',
				'"Ключевые слова раздела(keywords)"',
				'"Описание раздела"',
				'"Путь для раздела"',
				'"Порядок сортировки раздела"',
				'"CML ID идентификатор товара"',
				'"Артикул товара"',
				'"Артикул родительского товара"',
				'"Название товара"',
				'"Описание товара"',
				'"Текст для товара"',
				'"Вес товара"',
				'"Тип товара"',
				'"Метки"',
				'"Цена товара"',
				'"Активность товара"',
				'"Порядок сортировки товара"',
				'"Путь к товару"',
				'"Идентификатор налога для товара"',
				'"Идентификатор валюты"',
				'"Название продавца"',
				'"Название производителя"',
				'"Название единицы измерения"',
				'"Заголовок (title)"',
				'"Значение мета-тега description для страницы с товаром"',
				'"Значение мета-тега keywords для страницы с товаром"',
				'"Флаг индексации"',
				'"Флаг ""Экспортировать в Яндекс.Маркет"""',
				'"Яндекс.Маркет основная расценка"',
				'"Яндекс.Маркет расценка для карточек моделей"',
				'"Дата"',
				'"Дата публикации"',
				'"Дата завершения публикации"',
				'"Файл изображения для товара"',
				'"Файл малого изображения для товара"',
				'"Количество товара от"',
				'"Количество товара до"',
				'"Значение цены"',
				'"Процент от цены"',
				'"Идентификатор пользователя сайта"'
			);

			// Добавляем в заголовок информацию о свойствах товара
			foreach ($this->_aItem_Properties as $oItem_Property)
			{
				$this->_aCurrentData[$this->_iCurrentDataPosition][] = sprintf('"%s"', $this->prepareString($oItem_Property->name));
				$this->_iItem_Properties_Count++;

				if ($oItem_Property->type == 2)
				{
					$this->_aCurrentData[$this->_iCurrentDataPosition][] = sprintf('"%s"', $this->prepareString(Core::_('Shop_item.import_small_images') . $oItem_Property->name));
					$this->_iItem_Properties_Count++;
				}
			}

			// Добавляем в заголовок информацию о свойствах группы товаров
			foreach ($this->_aGroup_Properties as $oGroup_Property)
			{
				$this->_aCurrentData[$this->_iCurrentDataPosition][] = sprintf('"%s"', $this->prepareString($oGroup_Property->name));
				$this->_iGroup_Properties_Count++;

				if ($oGroup_Property->type == 2)
				{
					$this->_aCurrentData[$this->_iCurrentDataPosition][] = sprintf('"%s"', $this->prepareString(Core::_('Shop_item.import_small_images') . $oGroup_Property->name));
					$this->_iGroup_Properties_Count++;
				}
			}

			// Добавляем в заголовок информацию о складах
			foreach ($this->_aShopWarehouses as $oWarehouse)
			{
				$this->_aCurrentData[$this->_iCurrentDataPosition][] = Core::_('Shop_Item.warehouse_import_field', $this->prepareString($oWarehouse->name));
			}

			// Добавляем информацию о ценах на группы пользователя
			foreach ($this->_aShopPrices as $oShopPrice)
			{
				$this->_aCurrentData[$this->_iCurrentDataPosition][] = $oShopPrice->name;
			}
		}
	}

	/**
	 * Get special prices data for item
	 * @param Shop_Item $oShopItem item
	 */
	private function getSpecialPriceData($oShopItem)
	{
		// Получаем список специальных цен товара
		$aShop_Specialprices = $oShopItem->Shop_Specialprices->findAll(FALSE);

		$aTmpArray = array_merge($this->_aGroupBase_Properties, $this->_aItemBase_Properties, $this->_aSpecialPriceBase_Properties);

		// CML ID ТОВАРА
		$aTmpArray[9] = $oShopItem->guid;

		foreach ($aShop_Specialprices as $oShop_Specialprice)
		{
			$aTmpArray[39] = $oShop_Specialprice->min_quantity;
			$aTmpArray[40] = $oShop_Specialprice->max_quantity;
			$aTmpArray[41] = $oShop_Specialprice->price;
			$aTmpArray[42] = $oShop_Specialprice->percent;

			//echo implode($this->separator,array_merge($this->_aGroupBase_Properties, $aTmpArray)) . "\n";
			$this->_printRow($aTmpArray);
		}

		return $this;
	}

	/**
	 * Get item data
	 * @param int $oShopItem item
	 * @return array
	 */
	private function getItemData($oShopItem)
	{
		$aItemProperties = array();
		$aGroupProperties = array();
		$aWarehouses = array();
		$aShopPrices = array();

		foreach ($this->_aItem_Properties as $oItem_Property)
		{
			$aProperty_Values = $oItem_Property->getValues($oShopItem->id, FALSE);
			$iProperty_Values_Count = count($aProperty_Values);

			$aItemProperties[] = sprintf('"%s"', $this->prepareString($iProperty_Values_Count > 0
				? ($oItem_Property->type != 2
					? ($oItem_Property->type == 3 && $aProperty_Values[0]->value != 0 && Core::moduleIsActive('list')
						? $aProperty_Values[0]->List_Item->value
						: ($oItem_Property->type == 8
							? Core_Date::sql2date($aProperty_Values[0]->value)
							: ($oItem_Property->type == 9
								? Core_Date::sql2datetime($aProperty_Values[0]->value)
								: $aProperty_Values[0]->value)))
								: ($aProperty_Values[0]->file == '' ? '' : $aProperty_Values[0]->setHref($oShopItem->getItemHref())->getLargeFileHref())
								)
								: ''));

			if ($oItem_Property->type == 2)
			{
				if ($iProperty_Values_Count)
				{
					$aItemProperties[] = ($aProperty_Values[0]->file_small == '' ? '' : sprintf('"%s"', $aProperty_Values[0]->getSmallFileHref()));
				}
				else
				{
					$aItemProperties[] = '';
				}
			}
		}

		for ($i = 0; $i < $this->_iGroup_Properties_Count; $i++)
		{
			$aGroupProperties[] = "";
		}

		foreach ($this->_aShopWarehouses as $oWarehouse)
		{
			$oShop_Warehouse_Item = $oShopItem->Shop_Warehouse_Items->getByWarehouseId($oWarehouse->id, FALSE);
			$aWarehouses[] = !is_null($oShop_Warehouse_Item) ? $oShop_Warehouse_Item->count : 0;
		}

		foreach ($this->_aShopPrices as $oShopPrice)
		{
			$oShop_Price = $oShopItem->Shop_Item_Prices->getByPriceId($oShopPrice->id, FALSE);
			$aShopPrices[] = !is_null($oShop_Price) ? $oShop_Price->value : 0;
		}

		$aTmpArray = $this->_aGroupBase_Properties;

		$aTmpArray[1] = is_null($oShopItem->Shop_Group->id) ? 'ID00000000' : $oShopItem->Shop_Group->guid;

		if ($oShopItem->Shop_Group->id)
		{
			/*$aTmpArray[3] = $oShopItem->Shop_Group->seo_title;
			$aTmpArray[4] = $oShopItem->Shop_Group->seo_description;
			$aTmpArray[5] = $oShopItem->Shop_Group->seo_keywords;*/
			$aTmpArray[3] = sprintf('"%s"', $this->prepareString($oShopItem->Shop_Group->seo_title));
			$aTmpArray[4] = sprintf('"%s"', $this->prepareString($oShopItem->Shop_Group->seo_description));
			$aTmpArray[5] = sprintf('"%s"', $this->prepareString($oShopItem->Shop_Group->seo_keywords));
		}

		return array_merge($aTmpArray, array(
		sprintf('"%s"', $this->prepareString($oShopItem->guid)),
		sprintf('"%s"', $this->prepareString($oShopItem->marking)),
		sprintf('"%s"', $this->prepareString($oShopItem->Modification->marking)),
		sprintf('"%s"', $this->prepareString($oShopItem->name)),
		sprintf('"%s"', $this->prepareString($oShopItem->description)),
		sprintf('"%s"', $this->prepareString($oShopItem->text)),
		sprintf('"%s"', $oShopItem->weight),
		sprintf('"%s"', $oShopItem->type),
		sprintf('"%s"', (Core::moduleIsActive('tag') ? $this->prepareString(implode(",", $oShopItem->Tags->findAll(FALSE))) : "")),
		sprintf('"%s"', $oShopItem->price),
		sprintf('"%s"', $oShopItem->active),
		sprintf('"%s"', $oShopItem->sorting),
		sprintf('"%s"', $this->prepareString($oShopItem->path)),
		sprintf('"%s"', $oShopItem->shop_tax_id),
		sprintf('"%s"', $oShopItem->shop_currency_id),
		sprintf('"%s"', $this->prepareString($oShopItem->Shop_Seller->name)),
		sprintf('"%s"', $this->prepareString($oShopItem->Shop_Producer->name)),
		sprintf('"%s"', $this->prepareString($oShopItem->Shop_Measure->name)),
		sprintf('"%s"', $this->prepareString($oShopItem->seo_title)),
		sprintf('"%s"', $this->prepareString($oShopItem->seo_description)),
		sprintf('"%s"', $this->prepareString($oShopItem->seo_keywords)),
		sprintf('"%s"', $this->prepareString($oShopItem->indexing)),
		sprintf('"%s"', $oShopItem->yandex_market),
		sprintf('"%s"', $oShopItem->yandex_market_bid),
		sprintf('"%s"', $oShopItem->yandex_market_cid),
		sprintf('"%s"', $oShopItem->datetime == '0000-00-00 00:00:00'
			? '0000-00-00 00:00:00'
			: Core_Date::sql2datetime($oShopItem->datetime)
		),
		sprintf('"%s"', $oShopItem->start_datetime == '0000-00-00 00:00:00'
			? '0000-00-00 00:00:00'
			: Core_Date::sql2datetime($oShopItem->start_datetime)
		),
		sprintf('"%s"', $oShopItem->end_datetime == '0000-00-00 00:00:00'
			? '0000-00-00 00:00:00'
			: Core_Date::sql2datetime($oShopItem->end_datetime)
		),
		sprintf('"%s"', ($oShopItem->image_large == '') ? '' : $oShopItem->getLargeFileHref()),
		sprintf('"%s"', ($oShopItem->image_small == '') ? '' : $oShopItem->getSmallFileHref())), $this->_aSpecialPriceBase_Properties,
		array(sprintf('"%s"', $oShopItem->siteuser_id)), $aItemProperties, $aGroupProperties, $aWarehouses, $aShopPrices);
	}

	/**
	 * Array of titile line
	 * @var array
	 */
	protected $_aCurrentRow = array();

	/**
	 * Get Current Row
	 * @return array
	 */
	public function getCurrentRow()
	{
		return $this->_aCurrentRow;
	}

	/**
	 * Set Current Row
	 * @param array $array
	 * @return self
	 */
	public function setCurrentRow(array $array)
	{
		$this->_aCurrentRow = $array;
		return $this;
	}

	/**
	 * Executes the business logic.
	 * @hostcms-event Shop_Item_Export_Csv_Controller.onBeforeExportOrdersTitleProperties
	 * @hostcms-event Shop_Item_Export_Csv_Controller.onAfterExportOrdersTitleProperties
	 * @hostcms-event Shop_Item_Export_Csv_Controller.onBeforeExportOrderProperties
	 * @hostcms-event Shop_Item_Export_Csv_Controller.onAfterExportOrderProperties
	 */
	public function execute()
	{
		$sFilename = 'CSV_' . date("Y_m_d_H_i_s") . '.csv';

		header("Pragma: public");
		header("Content-Description: File Transfer");
		header("Content-Type: application/force-download");
		header("Content-Disposition: attachment; filename = " . $sFilename . ";");
		header("Content-Transfer-Encoding: binary");

		Core_Log::instance()->clear()
			->status(Core_Log::$ERROR)
			->write('Start CSV export ' . $sFilename);

		$oShop = Core_Entity::factory('Shop', $this->shopId);

		if(!$this->exportOrders)
		{
			foreach ($this->_aCurrentData as $aData)
			{
				$this->_printRow($aData);
			}
			$this->_aCurrentData = array();

			if ($this->parentGroup == 0)
			{
				$oShop_Groups = $oShop->Shop_Groups;
				$oShop_Groups->queryBuilder()
					->where('parent_id', '=', 0);
			}
			else
			{
				$oShop_Groups = Core_Entity::factory('Shop_Group', $this->parentGroup)->Shop_Groups;
			}

			$aShopGroupsId = array_merge(array($this->parentGroup), $oShop_Groups->getGroupChildrenId());

			foreach ($aShopGroupsId as $iShopGroupId)
			{
				$aTmpArray = array();

				$oShopGroup = Core_Entity::factory('Shop_Group', $iShopGroupId);

				$oShopItems = $oShopGroup->Shop_Items;
				$oShopItems->queryBuilder()
					->where('modification_id', '=', 0)
					->where('shortcut_id', '=', 0);

				if ($iShopGroupId != 0)
				{
					$aTmpArray = array(
						sprintf('"%s"', $this->prepareString($oShopGroup->name)),
						sprintf('"%s"', $this->prepareString($oShopGroup->guid)),
						sprintf('"%s"', $this->prepareString(is_null($oShopGroup->Shop_Group->id) ? 'ID00000000' : $oShopGroup->Shop_Group->guid)),
						sprintf('"%s"', $this->prepareString($oShopGroup->seo_title)),
						sprintf('"%s"', $this->prepareString($oShopGroup->seo_description)),
						sprintf('"%s"', $this->prepareString($oShopGroup->seo_keywords)),
						sprintf('"%s"', $this->prepareString($oShopGroup->description)),
						sprintf('"%s"', $this->prepareString($oShopGroup->path)),
						sprintf('"%s"', $this->prepareString($oShopGroup->sorting))
					);

					// Пропускаем поля товара
					foreach ($this->_aItemBase_Properties as $sNullData)
					{
						$aTmpArray[] = $sNullData;
					}

					// Пропускаем поля специальных цен товара
					foreach ($this->_aSpecialPriceBase_Properties as $sNullData)
					{
						$aTmpArray[] = $sNullData;
					}

					// Пропускаем поля дополнительных свойств товара
					for ($i = 0; $i < $this->_iItem_Properties_Count; $i++)
					{
						$aTmpArray[] = "";
					}

					// Выводим данные о дополнительных свойствах групп
					foreach ($this->_aGroup_Properties as $oGroup_Property)
					{
						$aProperty_Values = $oGroup_Property->getValues($oShopGroup->id);
						$iProperty_Values_Count = count($aProperty_Values);

						$aTmpArray[] = sprintf('"%s"', $this->prepareString($iProperty_Values_Count > 0 ? ($oGroup_Property->type != 2
							? ($oGroup_Property->type == 3 && $aProperty_Values[0]->value != 0 && Core::moduleIsActive('list')
								? $aProperty_Values[0]->List_Item->value
								: ($oGroup_Property->type == 8
									? Core_Date::sql2date($aProperty_Values[0]->value)
									: ($oGroup_Property->type == 9
										? Core_Date::sql2datetime($aProperty_Values[0]->value)
										: $aProperty_Values[0]->value)))
										: ($aProperty_Values[0]->file == ''
											? ''
											: $aProperty_Values[0]->setHref($oShopGroup->getGroupHref())->getLargeFileHref()))
												: ''));

						if ($oGroup_Property->type == 2)
						{
							$aTmpArray[] = $iProperty_Values_Count
								? ($aProperty_Values[0]->file_small == ''
									? ''
									: $aProperty_Values[0]->setHref($oShopGroup->getGroupHref())->getSmallFileHref()
								)
								: '';
						}
					}

					$this->_printRow($aTmpArray);
				}
				else
				{
					$oShopItems->queryBuilder()->where('shop_id', '=', $this->shopId);
				}

				$offset = 0;
				$limit = 100;

				do {
					$oShopItems->queryBuilder()->offset($offset)->limit($limit);
					$aShopItems = $oShopItems->findAll(FALSE);

					foreach ($aShopItems as $oShopItem)
					{				
						$this->_printRow($this->getItemData($oShopItem));

						$iPropertyFieldOffset = count($this->_aGroupBase_Properties) + count($this->_aItemBase_Properties) + count($this->_aSpecialPriceBase_Properties);

						$aCurrentPropertyLine = array_fill(0, $iPropertyFieldOffset, '""');

						// CML ID ТОВАРА
						$aCurrentPropertyLine[9] = $oShopItem->guid;

						foreach ($this->_aItem_Properties as $oItem_Property)
						{
							$aProperty_Values = $oItem_Property->getValues($oShopItem->id, FALSE);
							array_shift($aProperty_Values);

							if(count($aProperty_Values))
							{
								foreach ($aProperty_Values as $oProperty_Value)
								{
									$aCurrentPropertyLine[$iPropertyFieldOffset] = sprintf('"%s"', $this->prepareString(($oItem_Property->type != 2
										? ($oItem_Property->type == 3 && $oProperty_Value->value != 0 && Core::moduleIsActive('list')
											? $oProperty_Value->List_Item->value
											: ($oItem_Property->type == 8
												? Core_Date::sql2date($oProperty_Value->value)
												: ($oItem_Property->type == 9
													? Core_Date::sql2datetime($oProperty_Value->value)
													: $oProperty_Value->value)))
													: ($oProperty_Value->file == '' ? '' : $oProperty_Value->setHref($oShopItem->getItemHref())->getLargeFileHref())
													)));

									if ($oItem_Property->type == 2)
									{
										$aCurrentPropertyLine[$iPropertyFieldOffset+1] = sprintf('"%s"', $this->prepareString($oProperty_Value->setHref($oShopItem->getItemHref())->getSmallFileHref()));
									}

									$this->_printRow($aCurrentPropertyLine);
								}
							}

							if ($oItem_Property->type==2)
							{
								$aCurrentPropertyLine[$iPropertyFieldOffset] = '""';
								$aCurrentPropertyLine[$iPropertyFieldOffset+1] = '""';
								$iPropertyFieldOffset+=2;
							}
							else
							{
								$aCurrentPropertyLine[$iPropertyFieldOffset] = '""';
								$iPropertyFieldOffset++;
							}
						}


						$this->getSpecialPriceData($oShopItem);

						$aItemModifications = array();

						// Получаем список всех модификаций
						$this->exportItemModifications && $aItemModifications = $oShopItem->Modifications->findAll(FALSE);

						// Добавляем информацию о модификациях
						foreach ($aItemModifications as $oItemModification)
						{
							$this->_printRow(
								$this->getItemData($oItemModification)
							);
						}
					}
					$offset += $limit;
				}
				while (count($aShopItems));
			}
		}
		else
		{
			$this->_aCurrentRow = array(
				'"GUID заказа"',
				'"Номер заказа"',
				'"Страна"',
				'"Область"',
				'"Город"',
				'"Район"',
				'"Имя заказчика"',
				'"Фамилия заказчика"',
				'"Отчество заказчика"',
				'"E-mail заказчика"',
				'"Акт"',
				'"Счет-фактура"',
				'"Название компании"',
				'"ИНН"',
				'"КПП"',
				'"Телефон"',
				'"Факс"',
				'"Адрес"',
				'"Статус заказа"',
				'"Валюта заказа"',
				'"Идентификатор платежной системы"',
				'"Дата заказа"',
				'"Статус оплаты заказа"',
				'"Дата оплаты заказа"',
				'"Описание заказа"',
				'"Информация о заказе"',
				'"Заказ отменен"',
				'"Дата изменения статуса заказа"',
				'"Информация о доставке"',
				'"Артикул товара заказа"',
				'"Название товара заказа"',
			);

			Core_Event::notify(get_class($this) . '.onBeforeExportOrdersTitleProperties', $this, array($oShop));

			$linkedObject = Core_Entity::factory('Shop_Item_Property_List', $this->shopId);
			$aProperties = $linkedObject->Properties->findAll();

			$aCheckedProperties = array();

			foreach ($aProperties as $oProperty)
			{
				if (Core_Array::getPost('property_' . $oProperty->id))
				{
					$this->_aCurrentRow[] = '"' . $oProperty->name . '"';
					$aCheckedProperties[] = $oProperty;
				}
			}

			Core_Event::notify(get_class($this) . '.onAfterExportOrdersTitleProperties', $this, array($oShop));

			$this->_aCurrentRow = array_merge($this->_aCurrentRow, array(
				'"Количество товара заказа"',
				'"Цена товара заказа"',
				'"Налог на товар заказа"',
				'"Тип товара"')
			);

			$this->_printRow($this->_aCurrentRow);

			$offset = 0;
			$limit = 100;

			$oShop_Orders = $oShop->Shop_Orders;

			if(!is_null($this->start_order_date) && !is_null($this->end_order_date))
			{
				$sStartDate = Core_Date::timestamp2sql(Core_Date::datetime2timestamp($this->start_order_date . " 00:00:00"));
				$sEndDate = Core_Date::timestamp2sql(Core_Date::datetime2timestamp($this->end_order_date . " 23:59:59"));

				$oShop_Orders
					->queryBuilder()
					->where('datetime', 'BETWEEN', array($sStartDate, $sEndDate));
			}

			do {
				$oShop_Orders
					->queryBuilder()
					->orderBy('id', 'ASC')
					->offset($offset)->limit($limit);

				$aShop_Orders = $oShop_Orders->findAll(FALSE);

				foreach ($aShop_Orders as $oShop_Order)
				{
					$this->_printRow(array(
						sprintf('"%s"', $this->prepareString($oShop_Order->guid)),
						sprintf('"%s"', $this->prepareString($oShop_Order->invoice)),
						sprintf('"%s"', $this->prepareString($oShop_Order->Shop_Country->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->Shop_Country_Location->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->Shop_Country_Location_City->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->Shop_Country_Location_City_Area->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->surname)),
						sprintf('"%s"', $this->prepareString($oShop_Order->patronymic)),
						sprintf('"%s"', $this->prepareString($oShop_Order->email)),
						sprintf('"%s"', $this->prepareString($oShop_Order->acceptance_report)),
						sprintf('"%s"', $this->prepareString($oShop_Order->vat_invoice)),
						sprintf('"%s"', $this->prepareString($oShop_Order->company)),
						sprintf('"%s"', $this->prepareString($oShop_Order->tin)),
						sprintf('"%s"', $this->prepareString($oShop_Order->kpp)),
						sprintf('"%s"', $this->prepareString($oShop_Order->phone)),
						sprintf('"%s"', $this->prepareString($oShop_Order->fax)),
						sprintf('"%s"', $this->prepareString($oShop_Order->address)),
						sprintf('"%s"', $this->prepareString($oShop_Order->Shop_Order_Status->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->Shop_Currency->name)),
						sprintf('"%s"', $this->prepareString($oShop_Order->shop_payment_system_id)),
						sprintf('"%s"', $this->prepareString($oShop_Order->datetime)),
						sprintf('"%s"', $this->prepareString($oShop_Order->paid)),
						sprintf('"%s"', $this->prepareString($oShop_Order->payment_datetime)),
						sprintf('"%s"', $this->prepareString($oShop_Order->description)),
						sprintf('"%s"', $this->prepareString($oShop_Order->system_information)),
						sprintf('"%s"', $this->prepareString($oShop_Order->canceled)),
						sprintf('"%s"', $this->prepareString($oShop_Order->status_datetime)),
						sprintf('"%s"', $this->prepareString($oShop_Order->delivery_information))
					));

					// Получаем все товары заказа
					$aShop_Order_Items = $oShop_Order->Shop_Order_Items->findAll(FALSE);
					foreach ($aShop_Order_Items as $oShop_Order_Item)
					{
						$this->_aCurrentRow = array(
							sprintf('"%s"', $this->prepareString($oShop_Order->guid)),
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							'""',
							sprintf('"%s"', $this->prepareString($oShop_Order_Item->marking)),
							sprintf('"%s"', $this->prepareString($oShop_Order_Item->name))
						);

						Core_Event::notify(get_class($this) . '.onBeforeExportOrderProperties', $this, array($oShop, $oShop_Order_Item));

						foreach ($aCheckedProperties as $oProperty)
						{
							$aPropertyValues = $oProperty->getValues($oShop_Order_Item->Shop_Item->id);

							if (count($aPropertyValues))
							{
								switch ($oProperty->type)
								{
									case 0: // Int
									case 1: // String
									case 4: // Textarea
									case 6: // Wysiwyg
									case 7: // Checkbox
									case 10: // Hidden field
									case 11: // Float
										$this->_aCurrentRow[] = sprintf('"%s"', $this->prepareString($aPropertyValues[0]->value));
									break;
									case 3: // List
										if (Core::moduleIsActive('list'))
										{
											$oListItems = $oProperty->List->List_Items->getById($aPropertyValues[0]->value);

											$this->_aCurrentRow[] = !is_null($oListItems)
												? sprintf('"%s"', $this->prepareString($oListItems->value))
												: '""';
										}
									break;
									case 8: // Date
										$this->_aCurrentRow[] = sprintf('"%s"', $this->prepareString(
											Core_Date::sql2date($aPropertyValues[0]->value)
										));
									break;
									case 9: // Datetime
										$this->_aCurrentRow[] = sprintf('"%s"', $this->prepareString(
											Core_Date::sql2datetime($aPropertyValues[0]->value)
										));
									break;
									case 2: // File
									default:
										$this->_aCurrentRow[] = '""';
								}
							}
							else
							{
								$this->_aCurrentRow[] = '""';
							}
						}

						Core_Event::notify(get_class($this) . '.onAfterExportOrderProperties', $this, array($oShop, $oShop_Order_Item));

						$this->_aCurrentRow = array_merge($this->_aCurrentRow, array(
							sprintf('"%s"', $this->prepareString($oShop_Order_Item->quantity)),
							sprintf('"%s"', $this->prepareString($oShop_Order_Item->price)),
							sprintf('"%s"', $this->prepareString($oShop_Order_Item->rate)),
							sprintf('"%s"', $this->prepareString($oShop_Order_Item->type))
						));

						$this->_printRow($this->_aCurrentRow);
					}
				}
				$offset += $limit;
			}
			while (count($aShop_Orders));
		}

		Core_Log::instance()->clear()
			->status(Core_Log::$ERROR)
			->write('End CSV export ' . $sFilename);

		exit();
	}

	/**
	 * Prepare string
	 * @param string $string
	 * @return string
	 */
	public function prepareString($string)
	{
		return str_replace('"', '""', trim($string));
	}

	/**
	 * Print array
	 * @param array $aData
	 * @return self
	 */
	protected function _printRow($aData)
	{
		echo Shop_Item_Import_Csv_Controller::CorrectToEncoding(implode($this->separator, $aData)."\n", $this->encoding);
		return $this;
	}
}