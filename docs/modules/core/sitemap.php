<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Google sitemap
 * http://www.sitemaps.org/protocol.html
 *
 * - rebuildTime время в секундах, которое должно пройти с момента создания sitemap.xml для его перегенерации. По умолчанию 14400
 * - limit ограничение на единичную выборку элементов, по умолчанию 1000. При наличии достаточного объема памяти рекомендуется увеличить параметр
 * - createIndex разбивать карту на несколько файлов
 * - perFile Count of nodes per one file
 *
 * @package HostCMS
 * @subpackage Core
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Core_Sitemap extends Core_Servant_Properties
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'showInformationsystemGroups',
		'showInformationsystemItems',
		'showShopGroups',
		'showShopItems',
		'showModifications',
		'rebuildTime',
		'limit',
		'createIndex',
		'perFile'
	);

	/**
	 * Site
	 * @var Site_Model
	 */
	protected $_oSite = NULL;

	/**
	 * Constructor
	 * @param Site_Model $oSite Site object
	 */
	public function __construct(Site_Model $oSite)
	{
		parent::__construct();

		if (!defined('DENY_INI_SET') || !DENY_INI_SET)
		{
			@set_time_limit(90000);
			ini_set('max_execution_time', '90000');
		}

		$this->_oSite = $oSite;

		$this->_aSiteuserGroups = array(0, -1);
		if (Core::moduleIsActive('siteuser'))
		{
			$oSiteuser = Core_Entity::factory('Siteuser')->getCurrent();

			if ($oSiteuser)
			{
				$aSiteuser_Groups = $oSiteuser->Siteuser_Groups->findAll(FALSE);
				foreach ($aSiteuser_Groups as $oSiteuser_Group)
				{
					$this->_aSiteuserGroups[] = $oSiteuser_Group->id;
				}
			}
		}

		$this->rebuildTime = 14400; // 4 часа
		$this->limit = 1000;
		$this->createIndex = FALSE;
	}

	/**
	 * List of user groups
	 * @var array
	 */
	protected $_aSiteuserGroups = NULL;

	/**
	 * List of information systems
	 * @var array
	 */
	protected $_Informationsystems = array();

	/**
	 * List of shops
	 * @var array
	 */
	protected $_Shops = array();

	/**
	 * Get site
	 * @return Site_Model
	 */
	public function getSite()
	{
		return $this->_oSite;
	}

	/**
	 * Select Structure_Models by parent_id
	 * @param int $structure_id structure ID
	 * @return array
	 */
	protected function _selectStructuresByParentId($structure_id)
	{
		$oSite = $this->getSite();

		$oStructures = $oSite->Structures;
		$oStructures
			->queryBuilder()
			->where('structures.parent_id', '=', $structure_id)
			->where('structures.active', '=', 1)
			->where('structures.indexing', '=', 1)
			->where('structures.siteuser_group_id', 'IN', $this->_aSiteuserGroups)
			->orderBy('sorting')
			->orderBy('name');

		$aStructure = $oStructures->findAll(FALSE);

		return $aStructure;
	}

	/**
	 * Add structure nodes by parent
	 * @param int $structure_id structure ID
	 * @return self
	 * @hostcms-event Core_Sitemap.onBeforeSelectInformationsystemGroups
	 * @hostcms-event Core_Sitemap.onBeforeSelectInformationsystemItems
	 * @hostcms-event Core_Sitemap.onBeforeSelectShopGroups
	 * @hostcms-event Core_Sitemap.onBeforeSelectShopItems
	 */
	protected function _structure($structure_id = 0)
	{
		$oSite = $this->getSite();

		$aStructure = $this->_selectStructuresByParentId($structure_id);

		$dateTime = Core_Date::timestamp2sql(time());

		$oSite_Alias = $oSite->getCurrentAlias();

		foreach ($aStructure as $oStructure)
		{
			$sProtocol = $oStructure->https
				? 'https://'
				: 'http://';

			$this->addNode($sProtocol . $oSite_Alias->name . $oStructure->getPath(), $oStructure->changefreq, $oStructure->priority);

			// Informationsystem
			if ($this->showInformationsystemGroups && isset($this->_Informationsystems[$oStructure->id]))
			{
				$oInformationsystem = $this->_Informationsystems[$oStructure->id];

				$oCore_QueryBuilder_Select = Core_QueryBuilder::select(array('MAX(id)', 'max_id'));
				$oCore_QueryBuilder_Select
					->from('informationsystem_groups')
					->where('informationsystem_groups.informationsystem_id', '=', $oInformationsystem->id)
					->where('informationsystem_groups.deleted', '=', 0);
				$aRow = $oCore_QueryBuilder_Select->execute()->asAssoc()->current();
				$maxId = $aRow['max_id'];

				$iFrom = 0;

				$path = $sProtocol . $oSite_Alias->name . $oInformationsystem->Structure->getPath();

				do {
					$oInformationsystem_Groups = $oInformationsystem->Informationsystem_Groups;
					$oInformationsystem_Groups->queryBuilder()
						->select('informationsystem_groups.id',
							'informationsystem_groups.informationsystem_id',
							'informationsystem_groups.parent_id',
							'informationsystem_groups.path'
							)
						->where('informationsystem_groups.id', 'BETWEEN', array($iFrom + 1, $iFrom + $this->limit))
						->where('informationsystem_groups.siteuser_group_id', 'IN', $this->_aSiteuserGroups)
						->where('informationsystem_groups.active', '=', 1)
						->where('informationsystem_groups.indexing', '=', 1);

					Core_Event::notify('Core_Sitemap.onBeforeSelectInformationsystemGroups', $this, array($oInformationsystem_Groups));

					$aInformationsystem_Groups = $oInformationsystem_Groups->findAll(FALSE);

					foreach ($aInformationsystem_Groups as $oInformationsystem_Group)
					{
						$this->addNode($path . $oInformationsystem_Group->getPath(), $oStructure->changefreq, $oStructure->priority);
					}
					$iFrom += $this->limit;
				}
				while ($iFrom < $maxId);

				// Informationsystem's items
				if ($this->showInformationsystemItems)
				{
					$oCore_QueryBuilder_Select = Core_QueryBuilder::select(array('MAX(id)', 'max_id'));
					$oCore_QueryBuilder_Select
						->from('informationsystem_items')
						->where('informationsystem_items.informationsystem_id', '=', $oInformationsystem->id)
						->where('informationsystem_items.deleted', '=', 0);
					$aRow = $oCore_QueryBuilder_Select->execute()->asAssoc()->current();
					$maxId = $aRow['max_id'];

					$iFrom = 0;

					do {
						$oInformationsystem_Items = $oInformationsystem->Informationsystem_Items;
						$oInformationsystem_Items->queryBuilder()
							->select('informationsystem_items.id',
								'informationsystem_items.informationsystem_id',
								'informationsystem_items.informationsystem_group_id',
								'informationsystem_items.shortcut_id',
								'informationsystem_items.path'
								)
							->where('informationsystem_items.id', 'BETWEEN', array($iFrom + 1, $iFrom + $this->limit))
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
							->where('informationsystem_items.siteuser_group_id', 'IN', $this->_aSiteuserGroups)
							->where('informationsystem_items.active', '=', 1)
							->where('informationsystem_items.shortcut_id', '=', 0)
							->where('informationsystem_items.indexing', '=', 1);

						Core_Event::notify('Core_Sitemap.onBeforeSelectInformationsystemItems', $this, array($oInformationsystem_Items));

						$aInformationsystem_Items = $oInformationsystem_Items->findAll(FALSE);
						foreach ($aInformationsystem_Items as $oInformationsystem_Item)
						{
							$this->addNode($path . $oInformationsystem_Item->getPath(), $oStructure->changefreq, $oStructure->priority);
						}

						$iFrom += $this->limit;
					}
					while ($iFrom < $maxId);
				}
			}

			// Shop
			if ($this->showShopGroups && isset($this->_Shops[$oStructure->id]))
			{
				$oShop = $this->_Shops[$oStructure->id];

				$oCore_QueryBuilder_Select = Core_QueryBuilder::select(array('MAX(id)', 'max_id'));
				$oCore_QueryBuilder_Select
					->from('shop_groups')
					->where('shop_groups.shop_id', '=', $oShop->id)
					->where('shop_groups.deleted', '=', 0);
				$aRow = $oCore_QueryBuilder_Select->execute()->asAssoc()->current();
				$maxId = $aRow['max_id'];

				$iFrom = 0;

				$path = $sProtocol . $oSite_Alias->name . $oShop->Structure->getPath();

				do {
					$oShop_Groups = $oShop->Shop_Groups;
					$oShop_Groups->queryBuilder()
						->select('shop_groups.id',
							'shop_groups.shop_id',
							'shop_groups.parent_id',
							'shop_groups.path'
							)
						->where('shop_groups.id', 'BETWEEN', array($iFrom + 1, $iFrom + $this->limit))
						->where('shop_groups.siteuser_group_id', 'IN', $this->_aSiteuserGroups)
						->where('shop_groups.active', '=', 1)
						->where('shop_groups.indexing', '=', 1);

					Core_Event::notify('Core_Sitemap.onBeforeSelectShopGroups', $this, array($oShop_Groups));

					$aShop_Groups = $oShop_Groups->findAll(FALSE);

					foreach ($aShop_Groups as $oShop_Group)
					{
						$this->addNode($path . $oShop_Group->getPath(), $oStructure->changefreq, $oStructure->priority);
					}

					$iFrom += $this->limit;
				}
				while ($iFrom < $maxId);

				// Shop's items
				if ($this->showShopItems)
				{
					$oCore_QueryBuilder_Select = Core_QueryBuilder::select(array('MAX(id)', 'max_id'));
					$oCore_QueryBuilder_Select
						->from('shop_items')
						->where('shop_items.shop_id', '=', $oShop->id)
						->where('shop_items.deleted', '=', 0);
					$aRow = $oCore_QueryBuilder_Select->execute()->asAssoc()->current();
					$maxId = $aRow['max_id'];

					$iFrom = 0;

					do {
						$oShop_Items = $oShop->Shop_Items;
						$oShop_Items->queryBuilder()
							->select('shop_items.id',
								'shop_items.shop_id',
								'shop_items.shop_group_id',
								'shop_items.shortcut_id',
								'shop_items.modification_id',
								'shop_items.path'
								)
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
							->where('shop_items.siteuser_group_id', 'IN', $this->_aSiteuserGroups)
							->where('shop_items.active', '=', 1)
							->where('shop_items.shortcut_id', '=', 0)
							->where('shop_items.indexing', '=', 1);

						// Modifications
						!$this->showModifications
							&& $oShop_Items->queryBuilder()->where('shop_items.modification_id', '=', 0);

						Core_Event::notify('Core_Sitemap.onBeforeSelectShopItems', $this, array($oShop_Items));
						
						$aShop_Items = $oShop_Items->findAll(FALSE);
						foreach ($aShop_Items as $oShop_Item)
						{
							$this->addNode($path . $oShop_Item->getPath(), $oStructure->changefreq, $oStructure->priority);
						}

						$iFrom += $this->limit;
					}
					while ($iFrom < $maxId);
				}
			}

			// Structure
			$this->_structure($oStructure->id);
		}

		return $this;
	}

	/**
	 * Is it necessary to rebuild sitemap?
	 */
	protected $_bRebuild = TRUE;

	/**
	 * Add Informationsystem
	 * @param int $structure_id
	 * @param Informationsystem_Model $oInformationsystem
	 */
	public function addInformationsystem($structure_id, Informationsystem_Model $oInformationsystem)
	{
		$this->_Informationsystems[$structure_id] = $oInformationsystem;
		return $this;
	}

	/**
	 * Add Shop
	 * @param int $structure_id
	 * @param Shop_Model $oShop
	 */
	public function addShop($structure_id, Shop_Model $oShop)
	{
		$this->_Shops[$structure_id] = $oShop;
		return $this;
	}

	/**
	 * Fill nodes of structure
	 * @return self
	 * @hostcms-event Core_Sitemap.onBeforeFillNodeInformationsystems
	 * @hostcms-event Core_Sitemap.onAfterFillNodeInformationsystems
	 * @hostcms-event Core_Sitemap.onBeforeFillNodeShops
	 * @hostcms-event Core_Sitemap.onAfterFillNodeShops
	 */
	public function fillNodes()
	{
		$sIndexFilePath = $this->_getIndexFilePath();

		$this->_bRebuild = !is_file($sIndexFilePath) || time() > filemtime($sIndexFilePath) + $this->rebuildTime;

		if ($this->_bRebuild)
		{
			$this->createIndex && $this->createSitemapDir();

			$this->_Informationsystems = $this->_Shops = array();

			$oSite = $this->getSite();

			if ($this->showInformationsystemGroups || $this->showInformationsystemItems)
			{
				Core_Event::notify('Core_Sitemap.onBeforeFillNodeInformationsystems', $this);

				$aInformationsystems = $oSite->Informationsystems->findAll();
				foreach ($aInformationsystems as $oInformationsystem)
				{
					$this->addInformationsystem($oInformationsystem->structure_id, $oInformationsystem);
				}

				Core_Event::notify('Core_Sitemap.onAfterFillNodeInformationsystems', $this);
			}

			if ($this->showShopGroups || $this->showShopItems)
			{
				Core_Event::notify('Core_Sitemap.onBeforeFillNodeShops', $this);

				$aShops = $oSite->Shops->findAll();
				foreach ($aShops as $oShop)
				{
					$this->addShop($oShop->structure_id, $oShop);
				}

				Core_Event::notify('Core_Sitemap.onAfterFillNodeShops', $this);
			}

			$this->_structure(0);
		}

		return $this;
	}

	/**
	 * List of sitemap files
	 * @var array
	 */
	protected $_aIndexedFiles = array();

	/**
	 * Current output file
	 * @var Core_Out_File
	 */
	protected $_currentOut = NULL;

	/**
	 * Get current output file
	 * @return Core_Out_File
	 */
	protected function _getOut()
	{
		if ($this->createIndex)
		{
			if (is_null($this->_currentOut) || $this->_inFile >= $this->perFile)
			{
				$this->_getNewOutFile();
			}
		}
		elseif (is_null($this->_currentOut))
		{
			$this->_currentOut = new Core_Out_Std();
			$this->_open();
		}

		return $this->_currentOut;
	}

	/**
	 * Count URL in current file
	 * @var int
	 */
	protected $_inFile = 0;

	/**
	 * Sitemap files count
	 * @var int
	 */
	protected $_countFile = 1;

	/**
	 * Open current output file
	 * @return self
	 */
	protected function _open()
	{
		$this->_currentOut->open();
		$this->_currentOut->write('<?xml version="1.0" encoding="UTF-8"?>' . "\n")
			->write('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
		return $this;
	}

	/**
	 * Close current output file
	 * @return self
	 */
	protected function _close()
	{
		if ($this->_currentOut)
		{
			$this->_currentOut->write("</urlset>\n");
			$this->_currentOut->close();
		}
		return $this;
	}

	/**
	 * Get new file for sitemap
	 */
	protected function _getNewOutFile()
	{
		if (!is_null($this->_currentOut))
		{
			$this->_close();

			$this->_countFile++;
			$this->_inFile = 0;
		}

		$this->_aIndexedFiles[] = $filename = "sitemap-{$this->_oSite->id}-{$this->_countFile}.xml";

		$this->_currentOut = new Core_Out_File();
		//$this->_currentOut->filePath(CMS_FOLDER . $filename);
		$this->_currentOut->filePath($this->getSitemapDir() . $filename);
		$this->_open();
	}

	/**
	 * Add node to sitemap
	 * @param string $loc location
	 * @param int $changefreq change frequency
	 * @param float $priority priority
	 * @return self
	 */
	public function addNode($loc, $changefreq, $priority)
	{
		switch ($changefreq)
		{
			case 0 : $sChangefreq = 'always'; break;
			case 1 : $sChangefreq = 'hourly'; break;
			default:
			case 2 : $sChangefreq = 'daily'; break;
			case 3 : $sChangefreq = 'weekly'; break;
			case 4 : $sChangefreq = 'monthly'; break;
			case 5 : $sChangefreq = 'yearly'; break;
			case 6 : $sChangefreq = 'never'; break;
		}

		$this->_getOut()->write(
			"<url>\n" .
			"<loc>{$loc}</loc>\n" .
			"<changefreq>" . Core_Str::xml($sChangefreq) . "</changefreq>\n" .
			"<priority>" . Core_Str::xml($priority) . "</priority>\n" .
			"</url>\n"
		);

		$this->_inFile++;

		return $this;
	}

	/**
	 * Get index file path
	 * @return string
	 */
	protected function _getIndexFilePath()
	{
		return CMS_FOLDER . "sitemap-{$this->_oSite->id}.xml";
	}

	public function createSitemapDir()
	{
		clearstatcache();

		$sSitemapDir = $this->getSitemapDir();
		!is_dir($sSitemapDir) && Core_File::mkdir($sSitemapDir);

		return $this;
	}

	public function getSitemapDir()
	{
		return CMS_FOLDER . 'hostcmsfiles' . DIRECTORY_SEPARATOR . 'sitemap' . DIRECTORY_SEPARATOR;
	}

	public function getSitemapHref()
	{
		return '/hostcmsfiles/sitemap/';
	}

	/**
	 * Executes the business logic.
	 * @return self
	 * @hostcms-event Core_Sitemap.onBeforeExecute
	 */
	public function execute()
	{
		Core_Event::notify('Core_Sitemap.onBeforeExecute', $this);

		$this->_close();

		$sIndexFilePath = $this->_getIndexFilePath();

		if ($this->createIndex)
		{
			$this->createSitemapDir();

			if ($this->_bRebuild)
			{
				$sIndex = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

				$oSite_Alias = $this->_oSite->getCurrentAlias();

				$sProtocol = Core::httpsUses()
					? 'https://'
					: 'http://';

				$sIndex .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
				foreach ($this->_aIndexedFiles as $filename)
				{
					$sIndex .= "<sitemap>\n";
					$sIndex .= "<loc>{$sProtocol}{$oSite_Alias->name}{$this->getSitemapHref()}{$filename}</loc>\n";
					$sIndex .= "<lastmod>" . date('Y-m-d') . "</lastmod>\n";
					$sIndex .= "</sitemap>\n";
				}

				$sIndex .= '</sitemapindex>';

				echo $sIndex;

				Core_File::write($sIndexFilePath, $sIndex);
			}
		}

		if (!$this->_bRebuild)
		{
			echo Core_File::read($sIndexFilePath);
		}

		return $this;
	}
}