<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Document_Model
 *
 * @package HostCMS
 * @subpackage Document
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Document_Model extends Core_Entity
{
	/**
	 * Backend property
	 * @var mixed
	 */
	public $img = 1;

	/**
	 * One-to-many or many-to-many relations
	 * @var array
	 */
	protected $_hasMany = array(
		'document_version' => array()
	);

	/**
	 * Belongs to relations
	 * @var array
	 */
	protected $_belongsTo = array(
		'document_dir' => array(),
		'document_status' => array(),
		'user' => array(),
		'site' => array()
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
	 * Delete old version of document
	 */
	public function deleteOldVersions()
	{
		$oDocument_Versions = $this->Document_Versions->findAll();

		foreach ($oDocument_Versions as $oDocument_Version)
		{
			if ($oDocument_Version->current == 0)
			{
				$oDocument_Version->markDeleted();
			}
		}
	}

	/**
	 * Get document by site id
	 * @param int $site_id site id
	 * @return array
	 */
	public function getBySiteId($site_id)
	{
		$this->queryBuilder()
			//->clear()
			->where('site_id', '=', $site_id)
			->orderBy('name');

		return $this->findAll();
	}

	/**
	 * Delete object from database
	 * @param mixed $primaryKey primary key for deleting object
	 * @return Document_Model
	 */
	public function delete($primaryKey = NULL)
	{
		if (is_null($primaryKey))
		{
			$primaryKey = $this->getPrimaryKey();
		}

		$this->id = $primaryKey;

		$aDocument_Versions = $this->Document_Versions->findAll();
		foreach($aDocument_Versions as $oDocument_Version)
		{
			$oDocument_Version->delete();
		}

		return parent::delete($primaryKey);
	}

	/**
	 * Copy object
	 * @return Core_Entity
	 */
	public function copy()
	{
		$newObject = parent::copy();

		$oCurrent_Version = $this->Document_Versions->getCurrent(FALSE);
		if ($oCurrent_Version)
		{
			$oNewCurrent_Version = $oCurrent_Version->copy();
			$newObject->add($oNewCurrent_Version);
		}

		return $newObject;
	}

	/**
	 * Backend callback method
	 * @return string
	 */
	public function adminTemplate()
	{
		$oDocument_Version = $this->Document_Versions->getCurrent(FALSE);
		if (!is_null($oDocument_Version))
		{
			return $oDocument_Version->Template->name;
		}
	}

	/**
	 * Edit-in-Place callback
	 * @param string $text Text of document verison
	 * @return self
	 */
	public function editInPlaceVersion($text)
	{
		$oNewDocument_Version = Core_Entity::factory('Document_Version');

		$oDocument_Version_Current = $this->Document_Versions->getCurrent(FALSE);

		!is_null($oDocument_Version_Current) && $oNewDocument_Version->description = $oDocument_Version_Current->description;
		!is_null($oDocument_Version_Current) && $oNewDocument_Version->template_id = $oDocument_Version_Current->template_id;
		$oNewDocument_Version->saveFile($text);
		$this->add($oNewDocument_Version);
		$oNewDocument_Version->setCurrent();

		return $this;
	}
	
	/**
	 * Add message into search index
	 * @return self
	 */
	public function index()
	{
		if (Core::moduleIsActive('search'))
		{
			Search_Controller::indexingSearchPages(array($this->indexing()));
		}

		return $this;
	}

	/**
	 * Remove message from search index
	 * @return self
	 */
	public function unindex()
	{
		if (Core::moduleIsActive('search'))
		{
			Search_Controller::deleteSearchPage(6, 0, $this->id);
		}

		return $this;
	}
	
	/**
	 * Search indexation
	 * @return Search_Page
	 * @hostcms-event document.onBeforeIndexing
	 * @hostcms-event document.onAfterIndexing
	 */
	public function indexing()
	{
		$oSearch_Page = new stdClass();

		Core_Event::notify($this->_modelName . '.onBeforeIndexing', $this, array($oSearch_Page));

		$oSearch_Page->text = htmlspecialchars($this->name) . ' ';

		$oDocument_Version_Current = $this->Document_Versions->getCurrent(FALSE);
		
		if ($oDocument_Version_Current)
		{
			$oSearch_Page->text .= $oDocument_Version_Current->loadFile();
		
			$oSearch_Page->title = $this->name;

			$oSearch_Page->size = mb_strlen($oSearch_Page->text);
			$oSearch_Page->site_id = $this->site_id;
			$oSearch_Page->datetime = $oDocument_Version_Current->datetime;
			$oSearch_Page->module = 6;
			$oSearch_Page->module_id = $this->id;
			$oSearch_Page->inner = 1;
			$oSearch_Page->module_value_type = 0; // search_page_module_value_type
			$oSearch_Page->module_value_id = $this->id; // search_page_module_value_id
			$oSearch_Page->url = 'document-' . $this->id; // Уникальный номер

			$oSearch_Page->siteuser_groups = array(0);
		}

		Core_Event::notify($this->_modelName . '.onAfterIndexing', $this, array($oSearch_Page));

		return $oSearch_Page;
	}
}