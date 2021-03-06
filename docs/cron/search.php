<?php

/**
 * Пример вызова:
 * /usr/bin/php /var/www/site.ru/httpdocs/cron/search.php
 * Пример вызова с передачей php.ini
 * /usr/bin/php --php-ini /etc/php.ini /var/www/site.ru/httpdocs/cron/search.php
 * Реальный путь на сервере к корневой директории сайта уточните в службе поддержки хостинга.
 *
 * @package HostCMS 6\cron
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2014 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */

@set_time_limit(90000);

require_once(dirname(__FILE__) . '/../' . 'bootstrap.php');

$Search_Controller = Search_Controller::instance();

$topic = 0;

$step = 100;

$bIndexingCompleted = FALSE;

$Search_Controller->truncate();

$_SESSION['previous_step'] = 0;
$_SESSION['last_limit'] = 0;

$result = array();

$count = 0;

// Цикл по модулям
$oModules = Core_Entity::factory('Module');
$oModules->queryBuilder()
	->where('modules.active', '=', 1)
	->where('modules.indexing', '=', 1);

$aModules = $oModules->findAll();

foreach($aModules as $oModule)
{
	$limit = 0;

	echo "\nModule ", $oModule->path;
	if (!is_null($oModule->Core_Module))
	{
		if (method_exists($oModule->Core_Module, 'indexing'))
		{
			do {
				echo "\n  ", $limit, ' -> ', $limit + $step;

				$result = $oModule->Core_Module->indexing($limit, $step);
				$result && $count = count($result);

				$count && $Search_Controller->indexingSearchPages($result);

				$limit += $step;
			} while ($result && $count);
		}
	}
}

echo "OK\n";