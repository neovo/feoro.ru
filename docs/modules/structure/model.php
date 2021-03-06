<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Structure_Model
 *
 * @package HostCMS
 * @subpackage Structure
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Structure_Model extends Core_Entity
{
	/**
	 * Model name
	 * @var mixed
	 */
	protected $_modelName = 'structure';

	/**
	 * Backend property
	 * @var string
	 */
	public $menu_name = NULL;

	/**
	 * List of preloaded values
	 * @var array
	 */
	protected $_preloadValues = array(
		'parent_id' => 0,
		'document_id' => 0,
		'lib_id' => 0,
		'type' => 0,
		'sorting' => 0,
		'https' => 0,
		'active' => 1,
		'indexing' => 1,
		'changefreq' => 2,
		'priority' => 0.5,
		'siteuser_group_id' => 0,
		'template_id' => 0,
		// Warning: Удалить после объединения
		'data_template_id' => 0,
		'show' => 1,
		'url' => ''
	);

	/**
	 * Belongs to relations
	 * @var array
	 */
	protected $_belongsTo = array(
		'structure' => array('foreign_key' => 'parent_id'),
		'structure_menu' => array(),
		'template' => array(),
		'document' => array(),
		'lib' => array(),
		'site' => array(),
		'user' => array(),
		'siteuser' => array(),
		'siteuser_group' => array(),

		// Warning: Удалить
		'data_template' => array()
	);

	/**
	 * One-to-many or many-to-many relations
	 * @var array
	 */
	protected $_hasMany = array(
		'structure' => array('foreign_key' => 'parent_id')
	);

	/**
	 * One-to-one relations
	 * @var array
	 */
	protected $_hasOne = array(
		'forum' => array()
	);

	/**
	 * Forbidden tags. If list of tags is empty, all tags will show.
	 * @var array
	 */
	protected $_forbiddenTags = array(
		'options'
	);

	/**
	 * Constructor.
	 * @param int $id entity ID
	 */
	public function __construct($id = NULL)
	{
		parent::__construct($id);

		if (is_null($id))
		{
			$oUserCurrent = Core_Entity::factory('User', 0)->getCurrent();
			$this->_preloadValues['user_id'] = is_null($oUserCurrent) ? 0 : $oUserCurrent->id;
			$this->_preloadValues['site_id'] = defined('CURRENT_SITE') ? CURRENT_SITE : 0;
		}
	}

	/**
	 * Values of all properties of structure node
	 * @var array
	 */
	protected $_propertyValues = NULL;

	/**
	 * Values of all properties of element
	 * @param boolean $bCache cache mode
	 * @return array Property_Value
	 */
	public function getPropertyValues($bCache = TRUE)
	{
		if ($bCache && !is_null($this->_propertyValues))
		{
			return $this->_propertyValues;
		}

		// Need cache
		$aProperties = Core_Entity::factory('Structure_Property_List', $this->site_id)
			->Properties
			->findAll();

		$aReturn = array();

		foreach ($aProperties as $oProperty)
		{
			$aProperty_Values = $oProperty->getValues($this->id, $bCache);

			foreach ($aProperty_Values as $oProperty_Value)
			{
				if ($oProperty->type == 2)
				{
					$oProperty_Value
						->setHref('/' . $this->getDirHref())
						->setDir($this->getDirPath());
				}

				$aReturn[] = $oProperty_Value;
			}
		}

		if ($bCache)
		{
			$this->_propertyValues = $aReturn;
		}

		return $aReturn;
	}

	/**
	 * Create directory for item
	 * @return self
	 */
	public function createDir()
	{
		if (!is_dir($this->getDirPath()))
		{
			try
			{
				Core_File::mkdir($this->getDirPath(), CHMOD, TRUE);
			} catch (Exception $e) {}
		}

		return $this;
	}

	/**
	 * Get structure's file path
	 * @return string
	 */
	public function getStructureFilePath()
	{
		return CMS_FOLDER . "hostcmsfiles/structure/Structure" . intval($this->id) . ".php";
	}

	/**
	 * Get structure content
	 * @return string|NULL
	 */
	public function getStructureFile()
	{
		$path = $this->getStructureFilePath();

		return is_file($path)
			? Core_File::read($path)
			: NULL;
	}

	/**
	 * Specify structure content
	 * @param string $content content
	 */
	public function saveStructureFile($content)
	{
		$this->save();
		Core_File::write($this->getStructureFilePath(), $content);
	}

	/**
	 * Get structure's config file path
	 * @return string
	 */
	public function getStructureConfigFilePath()
	{
		return CMS_FOLDER . "hostcmsfiles/structure/StructureConfig" . intval($this->id) . ".php";
	}

	/**
	 * Get structure config
	 * @return string
	 */
	public function getStructureConfigFile()
	{
		$path = $this->getStructureConfigFilePath();

		return is_file($path)
			? Core_File::read($path)
			: NULL;
	}

	/**
	 * Specify structure config
	 * @param string $content config
	 */
	public function saveStructureConfigFile($content)
	{
		$this->save();
		Core_File::write($this->getStructureConfigFilePath(), $content);
	}

	/**
	 * Save object.
	 *
	 * @return Core_Entity
	 */
	public function save()
	{
		if (!$this->deleted && is_null($this->path))
		{
			$this->path = Core_Str::transliteration($this->name);
		}
		elseif (in_array('path', $this->_changedColumns))
		{
			$this->checkDuplicatePath();
		}

		parent::save();

		if (!$this->deleted && $this->path == '')
		{
			try {
				$path = Core_Str::transliteration(
					Core::$mainConfig['translate']
						? Core_Str::translate($this->name)
						: $this->name
				);
			} catch (Exception $e) {
				$path = NULL;
			}

			$this->path = strlen($path) ? $path : $this->id;
			$this->save();
		}

		return $this;
	}

	/**
	 * Delete object from database
	 * @param mixed $primaryKey primary key for deleting object
	 * @return Core_Entity
	 */
	public function delete($primaryKey = NULL)
	{
		if (is_null($primaryKey))
		{
			$primaryKey = $this->getPrimaryKey();
		}

		$this->id = $primaryKey;

		// Config file
		try
		{
			is_file($this->getStructureConfigFilePath()) && Core_File::delete($this->getStructureConfigFilePath());
		}
		catch (Exception $e) {}

		// File
		try
		{
			is_file($this->getStructureFilePath()) && Core_File::delete($this->getStructureFilePath());
		}
		catch (Exception $e) {}

		$aStructures = $this->Structures->findAll();
		foreach($aStructures as $oStructure)
		{
			$oStructure->delete();
		}

		// Delete proprties values
		// List of all properties
		$aProperties = Core_Entity::factory('Structure_Property_List', $this->site_id)->Properties->findAll();
		foreach ($aProperties as $oProperty)
		{
			// Values of property
			$aProperty_Values = $oProperty->getValues($this->id);

			foreach ($aProperty_Values as $oProperty_Value)
			{
				$oProperty_Value->delete();
			}
		}

		// Delete structure directory for additional properties
		$sDirPath = $this->getDirPath();
		try
		{
			is_dir($sDirPath) && Core_File::deleteDir($sDirPath);
		}
		catch (Exception $e) {}

		// Lib .dat file
		if (!is_null($this->lib_id))
		{
			$sLibDatFile = $this->Lib->getLibDatFilePath($this->id);
			try
			{
				is_file($sLibDatFile) && Core_File::delete($sLibDatFile);
			}
			catch (Exception $e) {}
		}

		return parent::delete($primaryKey);
	}

	/**
	 * Backend callback method
	 * @param Admin_Form_Field $oAdmin_Form_Field
	 * @param Admin_Form_Controller $oAdmin_Form_Controller
	 * @return string
	 */
	public function nameBadge($oAdmin_Form_Field, $oAdmin_Form_Controller)
	{
		$count = $this->getChildCount();
		$count && Core::factory('Core_Html_Entity_Span')
			->class('badge badge-hostcms badge-square')
			->value($count)
			->execute();
	}

	/**
	 * Get count of substructures all levels
	 * @return int
	 */
	public function getChildCount()
	{
		$count = 0;

		$aStructures = $this->Structures->findAll(FALSE);

		foreach ($aStructures as $oStructure)
		{
			$count++;
			$count += $oStructure->getChildCount();
		}

		return $count;
	}

	/**
	 * Backend callback method
	 * @return string
	 */
	public function path()
	{
		$sPath = $this->getPath();

		$oSite_Alias = Core_Entity::factory('Site', $this->site_id)->getCurrentAlias();
		if ($oSite_Alias)
		{
			$oCore_Html_Entity_Div = Core::factory('Core_Html_Entity_Div');

			if (!$this->active)
			{
				$oCore_Html_Entity_Div->style("text-decoration: line-through");
			}

			$oCore_Html_Entity_Div->add(
				Core::factory('Core_Html_Entity_A')
					->href("http://" . htmlspecialchars($oSite_Alias->name . $sPath))
					->target("_blank")
					->value(htmlspecialchars(urldecode($sPath)))
			);

			$oCore_Html_Entity_Div
				->class('hostcms-linkbox')
				->execute();
		}
		else
		{
			echo htmlspecialchars(urldecode($sPath));
		}
	}

	/**
	 * Get parent comment
	 * @return Structure_Model|NULL
	 */
	public function getParent()
	{
		if ($this->parent_id)
		{
			return Core_Entity::factory('Structure', $this->parent_id);
		}

		return NULL;
	}

	/**
	 * Get all nodes by site ID
	 * @param int $site_id site ID
	 * @return array
	 */
	public function getBySiteId($site_id)
	{
		$this->queryBuilder()
			//->clear()
			->where('site_id', '=', $site_id);

		return $this->findAll();
	}

	/**
	 * Get all nodes by menu ID
	 * @param int $structure_menu_id menu ID
	 * @return array
	 */
	public function getByStructureMenuId($structure_menu_id)
	{
		$this->queryBuilder()
			//->clear()
			->where('structure_menu_id', '=', $structure_menu_id);

		return $this->findAll();
	}

	/**
	 * Get active structure node by path and parent_id
	 * @param string $path
	 * @param int $parent_id
	 * @return
	 */
	public function getByPathAndParentId($path, $parent_id)
	{
		$this
			->queryBuilder()
			//->clear()
			->where('active', '=', 1)
			->where('path', '=', $path)
			->where('parent_id', '=', $parent_id)
			->limit(1);

		$aStructure = $this->findAll();
		return count($aStructure) == 1 ? $aStructure[0] : NULL;
	}

	/**
	 * Get path for files
	 * @return string
	 */
	public function getPath()
	{
		if ($this->path == '/')
		{
			return $this->path;
		}

		$path = rawurlencode($this->path) . '/';

		$path = $this->parent_id == 0
			? '/' . $path
			: $this->Structure->getPath() . $path;

		return $path;
	}

	/**
	 * Get object directory href
	 * @return string
	 */
	public function getDirHref()
	{
		return $this->Site->uploaddir . 'structure_' . intval($this->Site->id) . '/' . Core_File::getNestingDirPath($this->id, $this->Site->nesting_level) . '/structure_' . $this->id . '/';
	}

	/**
	 * Get object directory path
	 * @return string
	 */
	public function getDirPath()
	{
		return CMS_FOLDER . $this->getDirHref();
	}

	/**
	 * Change status of activity for structure node
	 * @return self
	 */
	public function changeStatus()
	{
		$this->active = 1 - $this->active;
		$this->save();
		return $this;
	}

	/**
	 * Switch indexing mode
	 * @return self
	 */
	public function changeIndexing()
	{
		$this->indexing = 1 - $this->indexing;
		$this->save();
		return $this;
	}

	/**
	 * Copy object
	 * @return Core_Entity
	 */
	public function copy()
	{
		$newObject = parent::copy();
		$newObject->path = '';
		$newObject->save();

		$aPropertyValues = $this->getPropertyValues(FALSE);

		// Create destination dir

		count($aPropertyValues) && $newObject->createDir();

		foreach($aPropertyValues as $oPropertyValue)
		{
			$oNewPropertyValue = clone $oPropertyValue;
			$oNewPropertyValue->entity_id = $newObject->id;
			$oNewPropertyValue->save();

			if ($oNewPropertyValue->Property->type == 2)
			{
				// Копируем файлы
				$oPropertyValue->setDir($this->getDirPath());
				$oNewPropertyValue->setDir($newObject->getDirPath());

				if (is_file($oPropertyValue->getLargeFilePath()))
				{
					try
					{
						Core_File::copy($oPropertyValue->getLargeFilePath(), $oNewPropertyValue->getLargeFilePath());
					} catch (Exception $e) {}
				}

				if (is_file($oPropertyValue->getSmallFilePath()))
				{
					try
					{
						Core_File::copy($oPropertyValue->getSmallFilePath(), $oNewPropertyValue->getSmallFilePath());
					} catch (Exception $e) {}
				}

			}
		}

		// Config file
		try
		{
			is_file($this->getStructureConfigFilePath())
				&& Core_File::copy($this->getStructureConfigFilePath(), $newObject->getStructureConfigFilePath());
		}
		catch (Exception $e) {}

		// File
		try
		{
			is_file($this->getStructureFilePath())
				&& Core_File::copy($this->getStructureFilePath(), $newObject->getStructureFilePath());
		}
		catch (Exception $e) {}

		// dat
		if ($this->lib_id)
		{
			$sLibDatFile = $this->Lib->getLibDatFilePath($this->id);
			try
			{
				is_file($sLibDatFile) && Core_File::copy($sLibDatFile, $newObject->Lib->getLibDatFilePath($newObject->id));
			}
			catch (Exception $e) {}
		}

		return $newObject;
	}

	/**
	 * Executes the business logic.
	 * @hostcms-event structure.onBeforeExecute
	 * @hostcms-event structure.onAfterExecute
	 */
	public function execute()
	{
		Core_Event::notify($this->_modelName . '.onBeforeExecute', $this);

		include $this->getStructureFilePath();

		Core_Event::notify($this->_modelName . '.onAfterExecute', $this);

		return $this;
	}

	/**
	 * Show properties in XML
	 * @var boolean
	 */
	protected $_showXmlProperties = FALSE;

	/**
	 * Show properties in XML
	 * @param boolean $showXmlProperties
	 * @return self
	 */
	public function showXmlProperties($showXmlProperties = TRUE)
	{
		$this->_showXmlProperties = $showXmlProperties;
		return $this;
	}

	/**
	 * Get XML for entity and children entities
	 * @return string
	 * @hostcms-event structure.onBeforeRedeclaredGetXml
	 */
	public function getXml()
	{
		Core_Event::notify($this->_modelName . '.onBeforeRedeclaredGetXml', $this);

		$this->clearXmlTags()
			->addXmlTag('link', $this->getPath())
			->addXmlTag('dir', '/' . $this->getDirHref());

		if ($this->_showXmlProperties)
		{
			$this->addEntities($this->getPropertyValues());
		}

		return parent::getXml();
	}

	/**
	 * Get the ID of the user group
	 * @return int
	 */
	public function getSiteuserGroupId()
	{
		// как у родителя
		if ($this->siteuser_group_id == -1)
		{
			$result = $this->parent_id
				? $this->Structure->getSiteuserGroupId()
				: 0;
		}
		else
		{
			$result = $this->siteuser_group_id;
		}

		return intval($result);
	}

	/**
	 * Search indexation
	 * @return Search_Page
	 * @hostcms-event structure.onBeforeIndexing
	 * @hostcms-event structure.onAfterIndexing
	 */
	public function indexing()
	{
		$oSearch_Page = new stdClass();

		Core_Event::notify($this->_modelName . '.onBeforeIndexing', $this, array($oSearch_Page));

		$oSearch_Page->text = htmlspecialchars($this->name) . ' ' .
			$this->id . ' ' .
			htmlspecialchars($this->seo_title) . ' ' .
			htmlspecialchars($this->seo_description) . ' ' .
			htmlspecialchars($this->seo_keywords) . ' ' .
			htmlspecialchars($this->path) . ' ';

		$oSearch_Page->title = strlen($this->seo_title) > 0
			? $this->seo_title
			: $this->name;

		// Для динамических страниц дата ставится текущая
		$date = date('Y-m-d H:i:s');

		// Страница статичная
		if ($this->type == 0)
		{
			$oDocument_Version = $this->Document->Document_Versions->getCurrent();

			if ($oDocument_Version)
			{
				$date = $oDocument_Version->datetime;
				$oSearch_Page->text .= $oDocument_Version->loadFile() . ' ';
			}
		}

		if (Core::moduleIsActive('informationsystem'))
		{
			$oInformationsystem = Core_Entity::factory('Informationsystem')->getByStructure_id($this->id, FALSE);
			if ($oInformationsystem)
			{
				$oSearch_Page->text .= htmlspecialchars($oInformationsystem->name) . ' ' . $oInformationsystem->description . ' ';
			}
		}

		if (Core::moduleIsActive('shop'))
		{
			$oShop = Core_Entity::factory('Shop')->getByStructure_id($this->id, FALSE);
			if ($oShop)
			{
				$oSearch_Page->text .= htmlspecialchars($oShop->name) . ' ' .
					$oShop->description . ' ';
			}
		}

		$aPropertyValues = $this->getPropertyValues(FALSE);
		foreach ($aPropertyValues as $oPropertyValue)
		{
			// List
			if ($oPropertyValue->Property->type == 3 && Core::moduleIsActive('list'))
			{
				if ($oPropertyValue->value != 0)
				{
					$oList_Item = $oPropertyValue->List_Item;
					$oList_Item->id && $oSearch_Page->text .= htmlspecialchars($oList_Item->value) . ' ';
				}
			}
			// Informationsystem
			elseif ($oPropertyValue->Property->type == 5 && Core::moduleIsActive('informationsystem'))
			{
				if ($oPropertyValue->value != 0)
				{
					$oInformationsystem_Item = $oPropertyValue->Informationsystem_Item;
					if ($oInformationsystem_Item->id)
					{
						$oSearch_Page->text .= htmlspecialchars($oInformationsystem_Item->name) . ' ';
					}
				}
			}
			// Other type
			elseif ($oPropertyValue->Property->type != 2)
			{
				$oSearch_Page->text .= htmlspecialchars($oPropertyValue->value) . ' ';
			}
		}

		$oSiteAlias = $this->Site->getCurrentAlias();
		if ($oSiteAlias)
		{
			$oSearch_Page->url = 'http://' . $oSiteAlias->name . $this->getPath();
		}
		else
		{
			return NULL;
		}

		$oSearch_Page->size = mb_strlen($oSearch_Page->text);
		$oSearch_Page->site_id = $this->site_id;
		$oSearch_Page->datetime = $date;
		$oSearch_Page->module = 0;
		$oSearch_Page->module_id = $this->site_id;
		$oSearch_Page->inner = 0;
		$oSearch_Page->module_value_type = 0; // search_page_module_value_type
		$oSearch_Page->module_value_id = $this->id; // search_page_module_value_id

		$oSearch_Page->siteuser_groups = array($this->getSiteuserGroupId());

		Core_Event::notify($this->_modelName . '.onAfterIndexing', $this, array($oSearch_Page));

		return $oSearch_Page;
	}

	/**
	 * Set SEO info to page from structure node
	 * @param Core_Page $oCore_Page page
	 * @return self
	 * @hostcms-event structure.onAfterSetCorePageSeo
	 */
	public function setCorePageSeo(Core_Page $oCore_Page)
	{
		$sTitle = trim($this->seo_title) != ''
			? $this->seo_title
			: $this->name;

		$sDescription = trim($this->seo_description) != ''
			? $this->seo_description
			: $this->name;

		$sKeywords = trim($this->seo_keywords) != ''
			? $this->seo_keywords
			: $this->name;

		$oCore_Page
			->title($sTitle)
			->description($sDescription)
			->keywords($sKeywords);

		Core_Event::notify($this->_modelName . '.onAfterSetCorePageSeo', $this, array($oCore_Page));

		return $this;
	}

	/**
	 * Get related object by type
	 * @hostcms-event structure.onBeforeGetRelatedObjectByType
	 * @hostcms-event structure.onAfterGetRelatedObjectByType
	 * @return object
	 */
	public function getRelatedObjectByType()
	{
		Core_Event::notify($this->_modelName . '.onBeforeGetRelatedObjectByType', $this);

		// Статичная страница
		if ($this->type == 0)
		{
			$return = $this->Document->Document_Versions->getCurrent();
		}
		elseif ($this->type == 1)
		{
			$return = $this;
		}
		else
		{
			// Типовая динамическая страница
			$return = $this->Lib;
		}

		Core_Event::notify($this->_modelName . '.onAfterGetRelatedObjectByType', $this, array(& $return));

		return $return;
	}

	/**
	 * Mark entity as deleted
	 * @return Core_Entity
	 */
	public function markDeleted()
	{
		$this->clearCache();

		return parent::markDeleted();
	}

	/**
	 * Clear tagged cache
	 * @return self
	 */
	public function clearCache()
	{
		if (Core::moduleIsActive('cache'))
		{
			Core_Cache::instance(Core::$mainConfig['defaultCache'])
				->deleteByTag('structure_' . $this->id)
				->deleteByTag('structure_' . $this->parent_id);
		}

		return $this;
	}

	/**
	 * Check and correct duplicate path
	 * @return self
	 */
	public function checkDuplicatePath()
	{
		$oSameStructures = Core_Entity::factory('Structure');
		$oSameStructures->queryBuilder()
			->where('site_id', '=', $this->site_id)
			->where('parent_id', '=', $this->parent_id)
			->where('path', '=', $this->path)
			->where('id', '!=', $this->id)
			->limit(1);

		$aSameStructures = $oSameStructures->findAll(FALSE);

		if (count($aSameStructures))
		{
			$this->path = Core_Guid::get();
		}

		return $this;
	}
}