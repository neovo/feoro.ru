<?php
$id = Core_Array::getGet('id');
if (Core::moduleIsActive('advertisement') && $id)
{
	$oAdvertisement_Controller = Advertisement_Controller::instance()
		// Время хранения информации о показе
		->keep_days(3);

	$location = $oAdvertisement_Controller->getLocation($id);

	$advertisement_id = intval(Core_Array::getGet('banner_id'));

	if (!$location && $advertisement_id)
	{
		$oAdvertisement = Core_Entity::factory('Advertisement')->find($advertisement_id);

		// Баннер найден
		!is_null($oAdvertisement) && $location = $oAdvertisement->href;
	}

	if ($location)
	{
		header('HTTP/1.0 301 Redirect');
		?><script type="text/javascript">location.href='<?php echo $location?>'</script><?php
		exit();
	}
}

// 404 Not found
$oCore_Page = Core_Page::instance();

$oCore_Response = $oCore_Page->deleteChild()->response->status(404);

// Если определена константа с ID страницы для 404 ошибки и она не равна нулю
$oSite = Core_Entity::factory('Site', CURRENT_SITE);
if ($oSite->error404)
{
	$oStructure = Core_Entity::factory('Structure')->find($oSite->error404);

	$oCore_Page = Core_Page::instance();

	// страница с 404 ошибкой не найдена
	if (is_null($oStructure->id))
	{
		throw new Core_Exception('Group not found');
	}

	if ($oStructure->type == 0)
	{
		$oDocument_Versions = $oStructure->Document->Document_Versions->getCurrent();

		if (!is_null($oDocument_Versions))
		{
			$oCore_Page->template($oDocument_Versions->Template);
		}
	}
	// Если динамическая страница или типовая дин. страница
	elseif ($oStructure->type == 1 || $oStructure->type == 2)
	{
		$oCore_Page->template($oStructure->Template);
	}

	$oCore_Page->addChild($oStructure->getRelatedObjectByType());
	$oStructure->setCorePageSeo($oCore_Page);
	
	// Если уже идет генерация страницы, то добавленный потомок не будет вызван
	$oCore_Page->buildingPage && $oCore_Page->execute();
}
else
{
	if (Core::$url['path'] != '/')
	{
		// Редирект на главную страницу
		$oCore_Response->header('Location', '/');
	}
}