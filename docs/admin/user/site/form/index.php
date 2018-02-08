<?php
/**
 * Administration center users.
 *
 * @package HostCMS
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
require_once('../../../../bootstrap.php');

Core_Auth::authorization($sModule = 'user');

// Код формы
$iAdmin_Form_Id = 138;
$sAdminFormAction = '/admin/user/site/form/index.php';

$oAdmin_Form = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id);

$user_group_id = Core_Array::getGet('user_group_id');
$site_id = Core_Array::getGet('site_id');

$oUser_Group = Core_Entity::factory('User_Group', $user_group_id);
$oSite = Core_Entity::factory('Site', $site_id);

// Проверка возможности доступа пользователя к сайту
$oUser = Core_Entity::factory('User')->getCurrent();

if ($oUser->superuser == 0
	&& !$oUser->checkSiteAccess($oSite))
{
	throw new Core_Exception("Access denied");
}

// Контроллер формы
$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path($sAdminFormAction)
	->title(Core::_('User_Group_Action_Access.ua_show_user_form_access_title', $oUser_Group->name, $oSite->name))
	->pageTitle(Core::_('User_Group_Action_Access.ua_show_user_form_access_title', $oUser_Group->name, $oSite->name));

// Элементы строки навигации
$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');

// Путь к контроллеру формы групп пользователей
$sUserGroupsPath = '/admin/user/index.php';

// Путь к контроллеру формы пользователей определенной группы
$sUsersPath = '/admin/user/user/index.php';
$sAdditionalUsersParams = 'user_group_id=' . $user_group_id;

$sChoosingSitePath = '/admin/user/site/index.php';
$sAdditionalChoosingSiteParams = 'user_group_id=' . $user_group_id;

$form_mode = Core_Array::getGet('mode');
if (!is_null($form_mode))
{
	$sAdditionalChoosingSiteParams .= '&mode=' . $form_mode;
}

// Элементы строки навигации
$oAdmin_Form_Entity_Breadcrumbs->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('User_Group.ua_link_users_type'))
		->href(
			$oAdmin_Form_Controller->getAdminLoadHref($sUserGroupsPath, NULL, NULL, '')
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminLoadAjax($sUserGroupsPath, NULL, NULL, '')
	)
)
->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('User.ua_show_users_title', $oUser_Group->name))
		->href(
			$oAdmin_Form_Controller->getAdminLoadHref($sUsersPath, NULL, NULL, $sAdditionalUsersParams)
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminLoadAjax($sUsersPath, NULL, NULL, $sAdditionalUsersParams)
	)
)
->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('User_Group.choosing_site'))
		->href(
			$oAdmin_Form_Controller->getAdminLoadHref($sChoosingSitePath, NULL, NULL, $sAdditionalChoosingSiteParams)
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminLoadAjax($sChoosingSitePath, NULL, NULL, $sAdditionalChoosingSiteParams)
	)
)
->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('User_Module.ua_show_user_access_title', $oUser_Group->name, $oSite->name))
		->href(
			$oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath())
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath())
	)
);

// Добавляем все хлебные крошки контроллеру
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Breadcrumbs);

// Действие "Применить"
$oAdminFormActionApply = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)
	->Admin_Form_Actions
	->getByName('apply');

if ($oAdminFormActionApply && $oAdmin_Form_Controller->getAction() == 'apply')
{
	$oControllerApply = Admin_Form_Action_Controller::factory(
		'Admin_Form_Action_Controller_Type_Apply', $oAdminFormActionApply
	);

	// Добавляем типовой контроллер редактирования контроллеру формы
	$oAdmin_Form_Controller->addAction($oControllerApply);
}

// Действие "Копировать"
$oAdminFormActionCopy = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)
	->Admin_Form_Actions
	->getByName('copy');

if ($oAdminFormActionCopy && $oAdmin_Form_Controller->getAction() == 'copy')
{
	$oControllerCopy = Admin_Form_Action_Controller::factory(
		'Admin_Form_Action_Controller_Type_Copy', $oAdminFormActionCopy
	);

	// Добавляем типовой контроллер редактирования контроллеру формы
	$oAdmin_Form_Controller->addAction($oControllerCopy);
}

// Источник данных
$oAdmin_Form_Dataset = new Admin_Form_Dataset_Entity(
	Core_Entity::factory('User_Site_Form')
);

$oAdmin_Language = Core_Entity::factory('Admin_Language')
	->getByShortname(Core_I18n::instance()->getLng());

// Ограничение источника 0 по родительской группе
$oAdmin_Form_Dataset->addCondition(
	array('select' => array('admin_forms.*', array('admin_word_values.name', 'name')))
)->addCondition(
	array('leftJoin' => array('admin_words', 'admin_forms.admin_word_id', '=', 'admin_words.id'))
)
->addCondition(
	array('leftJoin' => array('admin_word_values', 'admin_word_values.admin_word_id', '=', 'admin_words.id'))
)
->addCondition(array('open' => array()))
->addCondition(array('where' => array('admin_word_values.admin_language_id', '=', $oAdmin_Language->id)))
->addCondition(array('setOr' => array()))
->addCondition(array('where' => array('admin_forms.admin_word_id', '=', 0)))
->addCondition(array('close' => array()));

// Добавляем источник данных контроллеру формы
$oAdmin_Form_Controller->addDataset($oAdmin_Form_Dataset);

// Внешняя заменя для onclick и href
$oAdmin_Form_Controller->addExternalReplace('{user_group_id}', $user_group_id);
$oAdmin_Form_Controller->addExternalReplace('{site_id}', $site_id);

// Показ формы
$oAdmin_Form_Controller->execute();
