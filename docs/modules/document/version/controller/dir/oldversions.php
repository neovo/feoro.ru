<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Documents.
 * Контроллер удаления нетекущих версий документа
 *
 * @package HostCMS
 * @subpackage Document
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Document_Version_Controller_Dir_Oldversions extends Admin_Form_Action_Controller
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'document_dir_id',
	);

	/**
	 * Constructor.
	 * @param Admin_Form_Action_Model $oAdmin_Form_Action action
	 */
	public function __construct(Admin_Form_Action_Model $oAdmin_Form_Action)
	{
		parent::__construct($oAdmin_Form_Action);
	}

	/**
	 * Executes the business logic.
	 * @param mixed $operation Operation name
	 * @return self
	 */
	public function execute($operation = NULL)
	{
		if (is_null($this->document_dir_id))
		{
			throw new Core_Exception('document_dir_id is NULL.');
		}

		// Документы в непосредственной директории
		$this->_deleteDocumentVersions($this->document_dir_id);

		$aDocument_Dirs = $this->_getChildDirs($this->document_dir_id);
		foreach ($aDocument_Dirs as $oDocument_Dir)
		{
			$this->_deleteDocumentVersions($oDocument_Dir->id);
		}
		
		return $this;
	}

	protected function _getChildDirs($document_dir_id)
	{
		$oDocument_Dirs = Core_Entity::factory('Site', CURRENT_SITE)->Document_Dirs;
		$oDocument_Dirs->queryBuilder()
			->where('parent_id', '=', $document_dir_id);

		$aDocument_Dirs = $oDocument_Dirs->findAll(FALSE);
		
		$result = array();
		foreach ($aDocument_Dirs as $oDocument_Dir)
		{
			$result = array_merge($result, $this->_getChildDirs($oDocument_Dir->id));
		}
		
		return array_merge($aDocument_Dirs, $result);
	}

	/**
	 * Delete old version from documents
	 */
	protected function _deleteDocumentVersions($document_dir_id)
	{
		$oDocuments = Core_Entity::factory('Document');
		$oDocuments->queryBuilder()
			->where('document_dir_id', '=', $document_dir_id);
		
		$aDocuments = $oDocuments->findAll(FALSE);

		foreach ($aDocuments as $oDocument)
		{
			$oDocument->deleteOldVersions();
		}
		
		return $this;
	}
}