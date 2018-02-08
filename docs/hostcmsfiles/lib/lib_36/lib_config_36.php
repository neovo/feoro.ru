<?php
@ini_set('display_errors', 1);
error_reporting(E_ALL);
@set_time_limit(90000);

// Временная директория
$currentMonth = date('n');
$sTemporaryDirectory = TMP_DIR . '1c_exchange_files/';
$sMonthTemporaryDirectory = $sTemporaryDirectory . 'month-' . $currentMonth . '/';
$sCmsFolderTemporaryDirectory = CMS_FOLDER . $sMonthTemporaryDirectory;

// Магазин для выгрузки
$oShop = Core_Entity::factory('Shop')->find(Core_Array::get(Core_Page::instance()->libParams, 'shopId'));

// Размер блока выгружаемых данных (100000000 = 100 мБ)
$iFileLimit = 100000000;

// Логировать обмен
$bDebug = TRUE;

// bugfix
usleep(10);

$BOM = "\xEF\xBB\xBF";

// Решение проблемы авторизации при PHP в режиме CGI
if (isset($_REQUEST['authorization'])
|| (isset($_SERVER['argv'][0])
		&& empty($_SERVER['PHP_AUTH_USER'])
		&& empty($_SERVER['PHP_AUTH_PW'])))
{
	$authorization_base64 = isset($_REQUEST['authorization'])
		? $_REQUEST['authorization']
		: mb_substr($_SERVER['argv'][0], 14);

	$authorization = base64_decode(mb_substr($authorization_base64, 6));
	$authorization_explode = explode(':', $authorization);

	if (count($authorization_explode) == 2)
	{
		list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = $authorization_explode;
	}

	unset($authorization);
}

if (!isset($_SERVER['PHP_AUTH_USER']))
{
	header('WWW-Authenticate: Basic realm="HostCMS"');
	header('HTTP/1.0 401 Unauthorized');
	exit;
}
elseif (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']))
{
	$answr = Core_Auth::login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

	Core_Auth::setCurrentSite();

	$oUser = Core_Entity::factory('User')->getByLogin(
		$_SERVER['PHP_AUTH_USER']
	);

	if ($answr !== TRUE || !is_null($oUser) && $oUser->read_only)
	{
		$bDebug && Core_Log::instance()->clear()
			->status(Core_Log::$MESSAGE)
			->write('1С, ошибка авторизации');

		// авторизация не пройдена
		exit('Authentication failed!');
	}
}
else
{
	exit();
}

if (!is_null($sType = Core_Array::getGet('type'))
	&& ($sType == 'catalog' || $sType == 'sale')
	&& Core_Array::getGet('mode') == 'checkauth')
{
	clearstatcache();

	// Удаление директорий обмена за предыдущие месяцы
	for ($i = 1; $i <= 12; $i++)
	{
		if ($currentMonth != $i)
		{
			$sTmpDir = CMS_FOLDER . $sTemporaryDirectory . 'month-' . $i;

			// Удаляем файлы предыдущего месяца
			if (is_dir($sTmpDir)
				&& Core_File::deleteDir($sTmpDir) === FALSE)
			{
				$bDebug && Core_Log::instance()->clear()
					->status(Core_Log::$MESSAGE)
					->write('1С, удаление файлов предыдущего месяца ' . $i);

				echo "{$BOM}failure\nCan't delete temporary folder {$sTmpDir}";
				die();
			}
		}
	}

	// Удаление XML файлов
	if (is_dir($sCmsFolderTemporaryDirectory))
	{
		try
		{
			clearstatcache();

			if ($dh = @opendir($sCmsFolderTemporaryDirectory))
			{
				while (($file = readdir($dh)) !== FALSE)
				{
					if ($file != '.' && $file != '..')
					{
						$pathName = $sCmsFolderTemporaryDirectory . DIRECTORY_SEPARATOR . $file;

						if (Core_File::getExtension($pathName) == 'xml'
							&& is_file($pathName))
						{
							Core_File::delete($pathName);
						}
					}
				}

				closedir($dh);
				clearstatcache();
			}
		}
		catch(Exception $exc)
		{
			echo sprintf("{$BOM}failure\n%s", $exc/*->getMessage()*/);
		}
	}

	// Генерируем Guid сеанса обмена
	$sGUID = Core_Guid::get();
	setcookie("1c_exchange", $sGUID);
	echo sprintf("{$BOM}success\n1c_exchange\n%s", $sGUID);
}
elseif (!is_null($sType = Core_Array::getGet('type'))
	&& ($sType == 'catalog' || $sType == 'sale')
	&& Core_Array::getGet('mode') == 'init')
{
	$bDebug && Core_Log::instance()->clear()
		->status(Core_Log::$MESSAGE)
		->write('1С, mode=init');

	echo sprintf("{$BOM}zip=no\nfile_limit=%s", $iFileLimit);
}
elseif (Core_Array::getGet('type') == 'catalog'
	&& Core_Array::getGet('mode') == 'file'
	&& ($sFileName = Core_Array::get($_SERVER, 'REQUEST_URI')) != '')
{
	parse_str($sFileName, $_myGet);
	$sFileName = $_myGet['filename'];

	$sFullFileName = $sCmsFolderTemporaryDirectory . $sFileName;
	Core_File::mkdir(dirname($sFullFileName), CHMOD, TRUE);

	$bDebug && Core_Log::instance()->clear()
		->status(Core_Log::$MESSAGE)
		->write('1С, type=catalog, mode=file, destination=' . $sFullFileName);

	if (file_put_contents($sFullFileName, file_get_contents("php://input"), FILE_APPEND) !== FALSE
		&& @chmod($sFullFileName, CHMOD_FILE))
	{
		echo "{$BOM}success";
	}
	else
	{
		echo "{$BOM}failure\nCan't save incoming data to file: {$sFullFileName}";
	}
}
elseif (Core_Array::getGet('type') == 'catalog'
	&& Core_Array::getGet('mode') == 'import'
	&& !is_null($sFileName = Core_Array::getGet('filename')))
{
	$bDebug && Core_Log::instance()->clear()
		->status(Core_Log::$MESSAGE)
		->write('1С, type=catalog, mode=import');

	try
	{
		$oShop_Item_Import_Cml_Controller = new Shop_Item_Import_Cml_Controller($sCmsFolderTemporaryDirectory . $sFileName);
		$oShop_Item_Import_Cml_Controller->iShopId = $oShop->id;
		$oShop_Item_Import_Cml_Controller->itemDescription = 'text';
		$oShop_Item_Import_Cml_Controller->iShopGroupId = 0;
		$oShop_Item_Import_Cml_Controller->sPicturesPath = $sMonthTemporaryDirectory;
		$oShop_Item_Import_Cml_Controller->importAction = 1;
		$oShop_Item_Import_Cml_Controller->sShopDefaultPriceName = defined('SHOP_DEFAULT_CML_CURRENCY_NAME')
			? SHOP_DEFAULT_CML_CURRENCY_NAME
			: 'Розничная';
		//$oShop_Item_Import_Cml_Controller->updateFields = array('marking', 'name', 'shop_group_id', 'text', 'description', 'images', 'taxes', 'shop_producer_id');
		$oShop_Item_Import_Cml_Controller->debug = $bDebug;
		$oShop_Item_Import_Cml_Controller->import();
		echo "{$BOM}success";
	}
	catch(Exception $exc)
	{
		echo sprintf("{$BOM}failure\n%s", $exc/*->getMessage()*/);
	}
}
elseif (Core_Array::getGet('type') == 'sale'
	&& Core_Array::getGet('mode') == 'query')
{
	$bDebug && Core_Log::instance()->clear()
		->status(Core_Log::$MESSAGE)
		->write('1С, type=sale, mode=query');

	$oXml = new Core_SimpleXMLElement(sprintf(
		"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<КоммерческаяИнформация ВерсияСхемы=\"2.08\" ДатаФормирования=\"%s\"></КоммерческаяИнформация>",
		date("Y-m-d")));

	$aShopOrders = $oShop->Shop_Orders->getAllByUnloaded(0);

	foreach($aShopOrders as $oShopOrder)
	{
		$oShopOrder->addCml($oXml);
		$oShopOrder->unloaded = 1;
		$oShopOrder->save();
	}

	header('Content-type: text/xml; charset=UTF-8');
	echo $BOM, $oXml->asXML();
}
elseif (Core_Array::getGet('type') == 'sale'
	&& Core_Array::getGet('mode') == 'success')
{
	$bDebug && Core_Log::instance()->clear()
		->status(Core_Log::$MESSAGE)
		->write('1С, type=sale, mode=success');

	/*$aShopOrders = $oShop->Shop_Orders->getAllByUnloaded(0);

	foreach($aShopOrders as $oShopOrder)
	{
		$oShopOrder->unloaded = 1;
		$oShopOrder->save();
	}*/

	echo "{$BOM}success\n";
}
elseif (Core_Array::getGet('type') == 'sale'
	&& Core_Array::getGet('mode') == 'file')
{
	$bDebug && Core_Log::instance()->clear()
		->status(Core_Log::$MESSAGE)
		->write('1С, type=sale, mode=file');

	echo "{$BOM}success\n";
}

die();