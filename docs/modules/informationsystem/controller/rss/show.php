<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Показ RSS-ленты информационной системы.
 *
 * Доступные методы:
 *
 * - channelEntities(array) массив дополнительных элементов, добавляемых в channel
 * - group($id) идентификатор информационной группы, если FALSE, то вывод инофрмационных элементов
 * осуществляется из всех групп
 * - yandex(TRUE|FALSE) экспорт в Яндекс.Новости, по умолчанию FALSE
 * - cache(TRUE|FALSE) использовать кэширование, по умолчанию TRUE
 * - tag($path) путь тега, с использованием которого ведется отбор информационных элементов
 * - offset($offset) смещение, с которого выводить информационные элементы. По умолчанию 0
 * - limit($limit) количество выводимых элементов
 *
 * <code>
 * $Informationsystem_Controller_Rss_Show = new Informationsystem_Controller_Rss_Show(
 * 		Core_Entity::factory('Informationsystem', 1)
 * 	);
 *
 * 	$Informationsystem_Controller_Rss_Show
 * 		->limit(10)
 * 		->show();
 * </code>
 *
 * @package HostCMS
 * @subpackage Informationsystem
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Informationsystem_Controller_Rss_Show extends Core_Controller
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'title',
		'description',
		'link',
		'image',
		'channelEntities',
		'group',
		'tag',
		'offset',
		'limit',
		'yandex',
		'stripTags',
		'cache',
	);

	/**
	 * Information system's items object
	 * @var array
	 */
	protected $_Informationsystem_Items = array();

	/**
	 * RSS
	 * @var Core_Rss
	 */
	protected $_Core_Rss = array();

	/**
	 * Path
	 * @var string
	 */
	protected $_path = NULL;

	/**
	 * Constructor.
	 * @param Informationsystem_Model $oInformationsystem information system
	 */
	public function __construct(Informationsystem_Model $oInformationsystem)
	{
		parent::__construct($oInformationsystem->clearEntities());

		$this->_Informationsystem_Items = $oInformationsystem->Informationsystem_Items;

		$siteuser_id = 0;

		$aSiteuserGroups = array(0, -1);
		if (Core::moduleIsActive('siteuser'))
		{
			$oSiteuser = Core_Entity::factory('Siteuser')->getCurrent();

			if ($oSiteuser)
			{
				$siteuser_id = $oSiteuser->id;

				$aSiteuser_Groups = $oSiteuser->Siteuser_Groups->findAll();
				foreach ($aSiteuser_Groups as $oSiteuser_Group)
				{
					$aSiteuserGroups[] = $oSiteuser_Group->id;
				}
			}
		}

		switch ($oInformationsystem->items_sorting_direction)
		{
			case 1:
				$items_sorting_direction = 'DESC';
			break;
			case 0:
			default:
				$items_sorting_direction = 'ASC';
		}

		// Определяем поле сортировки информационных элементов
		switch ($oInformationsystem->items_sorting_field)
		{
			case 1:
				$this->_Informationsystem_Items
					->queryBuilder()
					->clearOrderBy()
					->orderBy('informationsystem_items.name', $items_sorting_direction);
				break;
			case 2:
				$this->_Informationsystem_Items
					->queryBuilder()
					->clearOrderBy()
					->orderBy('informationsystem_items.sorting', $items_sorting_direction)
					->orderBy('informationsystem_items.name', $items_sorting_direction);
				break;
			case 0:
			default:
				$this->_Informationsystem_Items
					->queryBuilder()
					->clearOrderBy()
					->orderBy('informationsystem_items.datetime', $items_sorting_direction);
		}

		$dateTime = Core_Date::timestamp2sql(time());
		$this->_Informationsystem_Items
			->queryBuilder()
			->sqlCalcFoundRows()
			->select('informationsystem_items.*')
			->where('informationsystem_items.active', '=', 1)
			->open()
			->where('informationsystem_items.start_datetime', '<', $dateTime)
			->setOr()
			->where('informationsystem_items.start_datetime', '=', '0000-00-00 00:00:00')
			->close()
			->setAnd()
			->open()
			->where('informationsystem_items.end_datetime', '>', $dateTime)
			->setOr()
			->where('informationsystem_items.end_datetime', '=', '0000-00-00 00:00:00')
			->close()
			->where('informationsystem_items.siteuser_group_id', 'IN', $aSiteuserGroups);

		$this->group = FALSE;
		$this->offset = 0;
		$this->stripTags = TRUE;
		$this->yandex = FALSE;
		$this->cache = TRUE;

		$this->_Core_Rss = new Core_Rss();
	}

	/**
	 * Get information items
	 * @return array
	 */
	public function informationsystemItems()
	{
		return $this->_Informationsystem_Items;
	}

	/**
	 * Get RSS
	 * @return Core_Rss
	 */
	public function coreRss()
	{
		return $this->_Core_Rss;
	}

	/**
	 * Current <item>
	 * @var array
	 */
	protected $_currentItem = array();

	/**
	 * Set $this->_currentItem
	 * @param array $aItem
	 * @return self
	 */
	public function setCurrentItem(array $aItem)
	{
		$this->_currentItem = $aItem;
		return $this;
	}

	/**
	 * Get $this->_currentItem
	 * @return array
	 */
	public function getCurrentItem()
	{
		return $this->_currentItem;
	}

	/**
	 * Show RSS
	 * @return self
	 * @hostcms-event Informationsystem_Controller_Rss_Show.onBeforeRedeclaredShow
	 * @hostcms-event Informationsystem_Controller_Rss_Show.onBeforeAddItem
	 */
	public function show()
	{
		Core_Event::notify(get_class($this) . '.onBeforeRedeclaredShow', $this);

		$oInformationsystem = $this->getEntity();

		$sProtocol = $oInformationsystem->Structure->https ? 'https://' : 'http://';

		$oSiteAlias = $oInformationsystem->Site->getCurrentAlias();
		$oSiteAlias
			&& $this->_path = $sProtocol . $oSiteAlias->name . $oInformationsystem->Structure->getPath();

		$this->_Core_Rss
			->add('title', !is_null($this->title) ? $this->title : $oInformationsystem->name)
			->add('description', !is_null($this->description) ? $this->description : ($this->stripTags
				? strip_tags($oInformationsystem->description)
				: $oInformationsystem->description));

		$this->_Core_Rss->add('link', !is_null($this->link)
			? $this->link
			: $this->_path
		);

		if (is_array($this->image) && count($this->image))
		{
			$this->_Core_Rss->add('image', $this->image);
		}

		// Additional entities
		if (is_array($this->channelEntities))
		{
			foreach ($this->channelEntities as $aEntity)
			{
				// еще foreach по $entityValue
				$this->_Core_Rss->add(
					Core_Array::get($aEntity, 'name'),
					Core_Array::get($aEntity, 'value'),
					Core_Array::get($aEntity, 'attributes', array())
				);
			}
		}

		if ($this->cache && Core::moduleIsActive('cache'))
		{
			$oCore_Cache = Core_Cache::instance(Core::$mainConfig['defaultCache']);
			$inCache = $oCore_Cache->get($cacheKey = strval($this), $cacheName = 'informationsystem_rss');

			if (!is_null($inCache))
			{
				$this->_Core_Rss->showWithHeader($inCache);
				return $this;
			}
		}

		$this->yandex && $this->_Core_Rss->xmlns('yandex', 'http://news.yandex.ru');

		if (!is_null($this->tag) && Core::moduleIsActive('tag'))
		{
			$oTag = Core_Entity::factory('Tag')->getByPath($this->tag);

			if ($oTag)
			{
				$this->_Informationsystem_Items
					->queryBuilder()
					->leftJoin('tag_informationsystem_items', 'informationsystem_items.id', '=', 'tag_informationsystem_items.informationsystem_item_id')
					->where('tag_informationsystem_items.tag_id', '=', $oTag->id);

				// В корне при фильтрации по меткам вывод идет из всех групп ИС
				$this->group == 0 && $this->group = FALSE;
			}
		}

		if ($this->group !== FALSE)
		{
			$this->_Informationsystem_Items
				->queryBuilder()
				->where('informationsystem_group_id', '=', intval($this->group));
		}

		// Load model columns BEFORE FOUND_ROWS()
		Core_Entity::factory('Informationsystem_Item')->getTableColums();

		// Load user BEFORE FOUND_ROWS()
		$oUserCurrent = Core_Entity::factory('User', 0)->getCurrent();

		if ($this->limit)
		{
			$this->_Informationsystem_Items
				->queryBuilder()
				->offset(intval($this->offset))
				->limit(intval($this->limit));
		}

		$aInformationsystem_Items = $this->_Informationsystem_Items->findAll();

		$oSiteAlias = $oInformationsystem->Site->getCurrentAlias();
		$sitePath = $oSiteAlias
			? $sProtocol . $oSiteAlias->name
			: NULL;

		foreach ($aInformationsystem_Items as $oInformationsystem_Item)
		{
			$this->_currentItem = array();
			$this->_currentItem['pubDate'] = date('r', Core_Date::sql2timestamp($oInformationsystem_Item->datetime));
			$this->_currentItem['title'] = Core_Str::str2ncr(
				Core_Str::xml($this->stripTags
					? strip_tags($oInformationsystem_Item->name)
					: $oInformationsystem_Item->name
				)
			);

			$this->_currentItem['description'] = Core_Str::str2ncr(
				Core_Str::xml($this->stripTags
					? strip_tags($oInformationsystem_Item->description)
					: $oInformationsystem_Item->description)
			);

			if ($this->yandex)
			{
				$this->_currentItem['yandex:full-text'] = Core_Str::str2ncr(
					Core_Str::xml($this->stripTags
						? strip_tags($oInformationsystem_Item->text)
						: $oInformationsystem_Item->text)
				);

				if ($oInformationsystem_Item->Informationsystem_Group->id)
				{
					$this->_currentItem['category'] = Core_Str::str2ncr(Core_Str::xml($oInformationsystem_Item->Informationsystem_Group->name));
				}
			}

			$this->_currentItem['link'] = $this->_currentItem['guid'] = Core_Str::str2ncr(Core_Str::xml($this->_path . $oInformationsystem_Item->getPath()));

			if ($oInformationsystem_Item->image_large)
			{
				$file_enclosure = $oInformationsystem_Item->getLargeFilePath();

				$enclosure = array(
					'name' => 'enclosure',
					'value' => NULL,
					'attributes' => array(
						'url' => $sitePath . $oInformationsystem_Item->getLargeFileHref(),
						'type' => Core_Mime::getFileMime($file_enclosure)
					)
				);

				if (is_file($file_enclosure))
				{
					$enclosure['attributes']['length'] = filesize($file_enclosure);
				}

				$this->_currentItem[] = $enclosure;
			}

			Core_Event::notify(get_class($this) . '.onBeforeAddItem', $this, array($oInformationsystem_Item, $this->_currentItem));

			$this->_Core_Rss->add('item', $this->_currentItem);
		}

		$content = $this->_Core_Rss->get();
		$this->_Core_Rss->showWithHeader($content);
		$this->cache && Core::moduleIsActive('cache') && $oCore_Cache->set($cacheKey, $content, $cacheName);

		return $this;
	}
}