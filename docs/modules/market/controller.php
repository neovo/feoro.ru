<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Market.
 *
 * @package HostCMS
 * @subpackage Market
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Market_Controller extends Core_Servant_Properties
{
	/**
	 * Allowed object properties
	 * @var array
	 */
	protected $_allowedProperties = array(
		'login',
		'contract',
		'pin',
		'cms_folder',
		'php_version',
		'mysql_version',
		'update_id',
		'domain',
		'update_server',
		'keys',
		'category_id',
		'items',
		'total',
		'page',
		'limit',
		'error',
		'controller',
		'admin_view',
		'options',
		'tmpDir',
		'order'
	);

	/**
	 * The singleton instances.
	 * @var mixed
	 */
	static public $instance = NULL;

	/**
	 * Categories
	 */
	protected $_categories = NULL;

	/**
	 * Register an existing instance as a singleton.
	 * @return object
	 */
	static public function instance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_categories = $this->items = array();
		$this->page = 1;
		$this->limit = 9;
		$this->error = 0;
		$this->options = array();
	}

	/**
	 * Get directory path
	 * @return string
	 */
	public function getPath()
	{
		return CMS_FOLDER . 'hostcmsfiles/tmp/install';
	}

	/**
	 * Set market options
	 * @return self
	 */
	public function setMarketOptions()
	{
		$oHOSTCMS_UPDATE_NUMBER = Core_Entity::factory('Constant')->getByName('HOSTCMS_UPDATE_NUMBER');
		$update_id = !is_null($oHOSTCMS_UPDATE_NUMBER)
			? $oHOSTCMS_UPDATE_NUMBER->value
			: 0;

		$oSite = Core_Entity::factory('Site', CURRENT_SITE);

		$aSite_Alias_Names = array();

		$aSite_Aliases = $oSite->Site_Aliases->findAll();
		foreach ($aSite_Aliases as $oSite_Alias)
		{
			$aSite_Alias_Names[] = $oSite_Alias->name;
		}

		$oSite_Alias = $oSite->getCurrentAlias();
		$domain = !is_null($oSite_Alias)
			? $oSite_Alias->name
			: 'undefined';

		$this->login(defined('HOSTCMS_USER_LOGIN') ? HOSTCMS_USER_LOGIN : '')
			->contract(defined('HOSTCMS_CONTRACT_NUMBER') ? HOSTCMS_CONTRACT_NUMBER : '')
			->pin(defined('HOSTCMS_PIN_CODE') ? HOSTCMS_PIN_CODE : '')
			->cms_folder(CMS_FOLDER)
			->php_version(phpversion())
			->mysql_version(Core_DataBase::instance()->getVersion())
			->update_id($update_id)
			->domain($domain)
			->update_server(HOSTCMS_UPDATE_SERVER)
			->keys($aSite_Alias_Names);

		return $this;
	}

	protected function _parseGroup($oXmlGroup, $parentId = 0)
	{
		foreach ($oXmlGroup as $value)
		{
			//if (intval($value->count))
			//{
				$oObject = new StdClass();
				$oObject->id = intval($value->attributes()->id);
				$oObject->name = strval($value->name);
				$oObject->description = strval($value->description);
				$oObject->count = intval($value->count);

				if ($oObject->id)
				{
					$this->_categories[$parentId][] = $oObject;
				}

				if (isset($value->shop_group) && count($value->shop_group))
				{
					$this->_parseGroup($value->shop_group, $oObject->id);
				}
			//}
		}

		return $this->_categories;
	}

	/**
	 * Загрузка магазина
	 *
	 * @return Market_Controller
	 */
	public function getMarket()
	{
		$md5_contract = md5($this->contract);
		$md5_pin = md5($this->pin);

		$url = 'http://' . $this->update_server . "/hostcmsupdate/market/?action=load_market&domain=" . rawurlencode($this->domain) .
			'&protocol=' . (Core::httpsUses() ? 'https' : 'http') .
			"&login=" . rawurlencode($this->login) .
			"&contract=" . rawurlencode($md5_contract) .
			"&pin=" . rawurlencode($md5_pin) .
			"&cms_folder=" . rawurlencode($this->cms_folder) .
			"&php_version=" . rawurlencode($this->php_version) .
			"&mysql_version=" . rawurlencode($this->mysql_version) .
			"&update_id=" . $this->update_id .
			"&category_id=" . intval($this->category_id) .
			"&current=" . intval($this->page) .
			"&limit=" . intval($this->limit);

		!is_null($this->order) && $url .= "&order=" . rawurlencode($this->order);

		try
		{
			$Core_Http = Core_Http::instance()
				->url($url)
				->port(80)
				->timeout(5)
				->execute();

			$data = $Core_Http->getBody();

			$oXml = @simplexml_load_string($data);

			if (is_object($oXml) && !intval($oXml->error))
			{
				$this->_parseGroup($oXml->shop_group);

				//print_r($this->_categories);

				$aShop_Items = array();
				if (isset($oXml->shop_item) && count($oXml->shop_item))
				{
					foreach ($oXml->shop_item as $value)
					{
						$oObject = new StdClass();
						$oObject->id = intval($value->attributes()->id);

						$shop_group_id = intval($value->shop_group_id);

						$oObject->category_name = isset($aShop_Groups[$shop_group_id])
							? $aShop_Groups[$shop_group_id]->name
							: '';

						$oObject->name = strval($value->name);
						$oObject->description = strval($value->description);
						$oObject->image_large = 'http://' . $this->update_server . strval($value->dir) . strval($value->image_large);
						$oObject->image_small = 'http://' . $this->update_server . strval($value->dir) . strval($value->image_small);
						$oObject->url = 'http://' . $this->update_server . strval($value->url) . '?contract=' . $md5_contract . '&pin=' . $md5_pin;
						$oObject->siteuser_id = intval($value->siteuser_id);
						$oObject->price = strval($value->price);
						$oObject->currency = strval($value->currency);
						$oObject->paid = isset($value->paid)
							? intval($value->paid)
							: 0;

						$oAdminModule = Core_Entity::factory('Module')->getByPath(strval($value->path), FALSE);
						$oObject->installed = !is_null($oAdminModule)
							? 1
							: 0;

						$aShop_Items[] = $oObject;
					}

					$this->items = $aShop_Items;
				}
			}

			$this->category_id = isset($oXml->category_id)
				? intval($oXml->category_id)
				: 0;

			$this->total = isset($oXml->total)
				? intval($oXml->total)
				: 0;

			$this->page = isset($oXml->page)
				? intval($oXml->page)
				: 1;

			$this->error = isset($oXml->error)
				? intval($oXml->error)
				: 0;
		}
		catch (Exception $e)
		{
			$this->admin_view->addMessage(
				Core_Message::get(Core::_('Market.server_error_respond_0'), 'error')
			);
		}

		return $this;
	}

	/**
	 * Загрузка приложения
	 *
	 * @param int $module_id update ID
	 * @return string
	 */
	public function getModule($module_id)
	{
		$url = 'http://' . $this->update_server . "/hostcmsupdate/market/?action=get_module&domain=" . rawurlencode($this->domain) .
			'&protocol=' . (Core::httpsUses() ? 'https' : 'http') .
			'&login=' . rawurlencode($this->login) .
			'&contract=' . rawurlencode(md5($this->contract)) .
			'&pin=' . rawurlencode(md5($this->pin)) .
			'&cms_folder=' . rawurlencode($this->cms_folder) .
			'&php_version=' . rawurlencode($this->php_version) .
			'&mysql_version=' . rawurlencode($this->mysql_version) .
			'&update_id=' . $this->update_id .
			'&category_id=' . intval($this->category_id) .
			'&module_id=' . intval($module_id) .
			'&current=' . intval($this->page) .
			'&limit=' . intval($this->limit);

		try
		{
			$Core_Http = Core_Http::instance()
				->url($url)
				->port(80)
				->timeout(5)
				->execute();

			$data = $Core_Http->getBody();

			if (empty($data))
			{
				throw new Core_Exception(Core::_('Update.server_return_empty_answer'));
			}

			$oXml = @simplexml_load_string($data);

			if (is_object($oXml))
			{
				$error = intval($oXml->error);

				if (!$error)
				{
					if (isset($oXml->module) && count($oXml->module))
					{
						(!defined('DENY_INI_SET') || !DENY_INI_SET) && function_exists('set_time_limit') && ini_get('safe_mode') != 1 && @set_time_limit(3600);

						// Объект с данными о модуле
						$oModule = new StdClass();

						if (intval($oXml->module->attributes()->id))
						{
							$oModule->id = intval($oXml->module->attributes()->id);
							$oModule->shop_item_id = intval($oXml->module->shop_item_id);
							$oModule->name = strval($oXml->module->name);
							$oModule->description = strval($oXml->module->description);
							$oModule->number = strval($oXml->module->number);
							$oModule->path = strval($oXml->module->path);
							$oModule->php = strval($oXml->module->php);
							$oModule->sql = strval($oXml->module->sql);
							$oModule->file = strval($oXml->module->file);
							$oModule->author_email = strval($oXml->module->author_email);
						}

						if ($oModule->id)
						{
							// Загружаем и распаковываем версию модуля
							/**
							 * Структура архива
							 * files
							 * -- admin
							 * -- modules
							 * module.xml
							 * module.php
							 * module.sql
							 */

							// Временная директория для распаковки модуля
							// CMS_FOLDER . 'hostcmsfiles/tmp/install/{id}/'
							$this->tmpDir = $this->getPath() . '/' . $oModule->shop_item_id;

							// Удаляем директорию с данными предыдущей установки
							is_dir($this->tmpDir) && Core_File::deleteDir($this->tmpDir);

							// Создаем директорию снова
							Core_File::mkdir($this->tmpDir, CHMOD, TRUE);

							// по умолчанию ошибок обновления нет
							$bErrorInstall = FALSE;

							if ($oModule->file != '')
							{
								$Core_Http = $this->getModuleFile($oModule->file);

								$original_filename = 'tmpfile.tar.gz';

								// Сохраняем tar.gz
								$source_file = $this->tmpDir . DIRECTORY_SEPARATOR . $original_filename;
								Core_File::write($source_file, $Core_Http->getBody());

								// Распаковываем файлы
								$Core_Tar = new Core_Tar($source_file);
								if (!$Core_Tar->extractModify($this->tmpDir, $this->tmpDir))
								{
									$bErrorInstall = TRUE;

									// Возникла ошибка распаковки
									$this->admin_view->addMessage(
										Core_Message::get(Core::_('Update.update_files_error'), 'error')
									);
								}
							}

							// Создавать запись о модуле в таблице модулей
							$bCreateModule = FALSE;

							// Устанавливать сейчас
							$bInstall = FALSE;

							// Читаем modules.xml
							$sModuleXmlPath = $this->tmpDir . DIRECTORY_SEPARATOR . 'module.xml';
							if (is_file($sModuleXmlPath))
							{
								$sModuleXml = Core_File::read($sModuleXmlPath);
								$oModuleXml = simplexml_load_string($sModuleXml);

								$create_module = strval($oModuleXml->options->create_module);
								$bCreateModule = $create_module == 1 || $create_module == 'true';

								$aXmlFields = $oModuleXml->xpath("fields/field");

								if (count($aXmlFields))
								{
									$aFields = $this->getFields($aXmlFields);

									// Вывод списка опций
									if ($this->controller->getAction() != 'sendOptions')
									{
										ob_start();

										$sTitle = sprintf('Параметры установки "%s"', $oModule->name);
										$this->admin_view
											->pageTitle($sTitle);

										$this->controller->title($sTitle);

										$oMainTab = Admin_Form_Entity::factory('Tab')->name('main');

										foreach ($aFields as $aFieldsValue)
										{
											$sFieldCaption = htmlspecialchars($aFieldsValue['Caption']);
											$sFieldName = htmlspecialchars($aFieldsValue['Name']);
											$sFieldValue = htmlspecialchars($aFieldsValue['Value']);

											$sFieldType = strval($aFieldsValue['Type']);

											switch ($sFieldType)
											{
												case 'input':
													$oMainTab->add(
														Admin_Form_Entity::factory('Div')->class('row')
															->add(
																Admin_Form_Entity::factory('Input')
																	->caption($sFieldCaption)
																	->name($sFieldName)
																	->value($sFieldValue)
																	->divAttr(array('class' => 'form-group col-lg-6 col-md-12 col-sm-6 col-xs-6'))
																	->controller($this->controller)
															)
													);

												break;
												case 'select':
													$sFieldListValue = $aFieldsValue['ListValue'];

													$oMainTab->add(
														Admin_Form_Entity::factory('Div')->class('row')
															->add(
																Admin_Form_Entity::factory('Select')
																	->caption($sFieldCaption)
																	->name($sFieldName)
																	->options($sFieldListValue)
																	->divAttr(array('class' => 'form-group col-lg-6 col-md-12 col-sm-6 col-xs-6'))
																	->controller($this->controller)
															)
													);
												break;
												case 'siteList':
												case 'shopList':
												case 'informationsystemList':

													$aTmpOptions = array();
													if ($sFieldType == 'siteList')
													{
														$oUser = Core_Entity::factory('User')->getCurrent();
														$aObjects = $oUser->getSites();
													}
													elseif ($sFieldType == 'shopList')
													{
														$aObjects = Core_Entity::factory('Site', CURRENT_SITE)
															->Shops
															->findAll();
													}
													elseif ($sFieldType == 'informationsystemList')
													{
														$aObjects = Core_Entity::factory('Site', CURRENT_SITE)
															->Informationsystems
															->findAll();
													}

													foreach ($aObjects as $oObject)
													{
														$aTmpOptions[$oObject->id] = $oObject->name;
													}

													$oMainTab->add(
														Admin_Form_Entity::factory('Div')->class('row')
															->add(
																Admin_Form_Entity::factory('Select')
																	->caption($sFieldCaption)
																	->name($sFieldName)
																	->options($aTmpOptions)
																	->divAttr(array('class' => 'form-group col-lg-6 col-md-12 col-sm-6 col-xs-6'))
																	->controller($this->controller)
															)
													);
												break;
												case 'file':
													$sFieldExtension = htmlspecialchars($aFieldsValue['Extension']);
													$sFieldMaxWidth = intval($aFieldsValue['MaxWidth']);
													$sFieldMaxHeight = intval($aFieldsValue['MaxHeight']);

													$oMainTab->add(
														Admin_Form_Entity::factory('Div')->class('row')
															->add(
																Admin_Form_Entity::factory('File')
																	->caption($sFieldCaption)
																	->name($sFieldName)
																	->value($sFieldValue)
																	->largeImage(
																		array('show_params' => FALSE)
																	)->smallImage(
																		array('show' => FALSE)
																	)
																	->divAttr(array('class' => 'form-group col-lg-6 col-md-12 col-sm-6 col-xs-6'))
																	->controller($this->controller)
															)
															->add(
																Admin_Form_Entity::factory('Div')
																	->class('col-lg-6 col-md-12 col-sm-6 col-xs-6 margin-top-21')
																	->value(
																		(trim($sFieldExtension) != ''
																			? Core::_('market.allowed_extension', $sFieldExtension)
																			: ''
																		)
																)
															)
															->add(
																Admin_Form_Entity::factory('Div')
																	->class('col-lg-6 col-md-12 col-sm-6 col-xs-6')
																	->value(
																		$sFieldMaxWidth > 0 && $sFieldMaxHeight > 0
																			? "\n" . Core::_('market.max_file_size', $sFieldMaxWidth, $sFieldMaxHeight)
																			: ''
																		)
															)
													);

												break;
												case 'textarea':
													$oMainTab->add(
														Admin_Form_Entity::factory('Div')->class('row')
															->add(
																Admin_Form_Entity::factory('Textarea')
																	->caption($sFieldCaption)
																	->name($sFieldName)
																	->value($sFieldValue)
																	->divAttr(array('class' => 'form-group col-lg-6 col-md-12 col-sm-6 col-xs-6'))
																	->controller($this->controller)
															)
													);
												break;
											}
										}
										Admin_Form_Entity::factory('Form')
											->controller($this->controller)
											->action($this->controller->getPath())
											->add($oMainTab)
											->add(
												Admin_Form_Entity::factory('Input')
													->name('install')
													->value($module_id)
													->class('hidden')
											)
											->add(Admin_Form_Entity::factory('Button')
												->name('applyOptions')
												->type('submit')
												->value(Core::_('market.install'))
												->class('applyButton btn btn-blue')
												->onclick(
													$this->controller->getAdminSendForm('sendOptions')
												)
											)
											->execute();

										$this->admin_view
											->content(ob_get_clean())
											->show();
									}
									// Применение списка преданных опций
									else
									{
										$aOptions = array();
										foreach ($aFields as $aFieldsValue)
										{
											$sFieldCaption = $aFieldsValue['Caption'];
											$sFieldName = $aFieldsValue['Name'];
											$sFieldType = $aFieldsValue['Type'];

											// Файл
											if ($sFieldType == 'file')
											{
												if (isset($_FILES[$sFieldName]['tmp_name'])
												&& is_file($_FILES[$sFieldName]['tmp_name'])
												&& $_FILES[$sFieldName]['size'] > 0)
												{
													$sFieldPath = $aFieldsValue['Path'];
													$sFieldExtension = $aFieldsValue['Extension'];

													$sExt = Core_File::getExtension($_FILES[$sFieldName]['name']);
													$aAllowedExt = explode(',', $sFieldExtension);

													if (strlen(trim($sFieldExtension)) == 0 || in_array($sExt, $aAllowedExt))
													{
														if (!move_uploaded_file($_FILES[$sFieldName]['tmp_name'], CMS_FOLDER . $sFieldPath))
														{
															$this->admin_view->addMessage(
																Core_Message::get(Core::_('install.file_copy_error', $sFieldPath), 'error')
															);
														}
													}
													else
													{
														$this->admin_view->addMessage(
															Core_Message::get(Core::_('install.file_disabled_extension', $sFieldCaption), 'error')
														);
													}
												}
											}
											// Остальные типы полей
											else
											{
												$aOptions[$sFieldName] = Core_Array::getPost($sFieldName);
											}
										}

										$this->options = $aOptions;

										$bInstall = TRUE;
									}
								}
								else
								{
									// Устанавливаем сразу без опций модуля
									$bInstall = TRUE;
								}
							}
							else
							{
								$bInstall = TRUE;
							}

							if ($bInstall)
							{
								// Копируем файлы из ./files/ в папку системы
								$sFilesDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'files';
								if (is_dir($sFilesDir))
								{
									try
									{
										Core_File::copyDir($sFilesDir, CMS_FOLDER);
									}
									catch (Exception $e)
									{
										$this->admin_view->addMessage(
											Core_Message::get($e->getMessage(), 'error')
										);
									}
								}

								if (!$bErrorInstall)
								{
									try
									{
										// Размещаем SQL из описания обновления
										$sSql = strval($oModule->sql);
										$sSqlFilename = $this->tmpDir . '/' . $oModule->id . '.sql';
										Core_File::write($sSqlFilename, html_entity_decode($sSql, ENT_COMPAT, 'UTF-8'));
										$sSqlCode = html_entity_decode($sSql, ENT_COMPAT, 'UTF-8');
										Sql_Controller::instance()->execute($sSqlCode);

										// Размещаем PHP из описания обновления
										$sPhp = strval($oModule->php);
										$sPhpFilename = $this->tmpDir . '/' . $oModule->id . '.php';
										Core_File::write($sPhpFilename, html_entity_decode($sPhp, ENT_COMPAT, 'UTF-8'));
										include($sPhpFilename);

										// Стандартный файл module.sql из поставки модуля
										$sSqlModuleFilename = $this->tmpDir . DIRECTORY_SEPARATOR . 'module.sql';
										if (is_file($sSqlModuleFilename))
										{
											$sSqlCode = Core_File::read($sSqlModuleFilename);
											Sql_Controller::instance()->execute($sSqlCode);
										}

										// Стандартный файл module.php из поставки модуля
										$sPhpModuleFilename = $this->tmpDir . DIRECTORY_SEPARATOR . 'module.php';
										if (is_file($sPhpModuleFilename))
										{
											include($sPhpModuleFilename);
										}

										// Создаем модуль только при явном указании на это
										if ($bCreateModule)
										{
											$oAdminModule = Core_Entity::factory('Module');
											$oAdminModule
												->name($oModule->name)
												->description($oModule->description)
												->active(1)
												->indexing(1)
												->path($oModule->path)
												->save();

											// install() для модуля, если есть
											$oAdminModule->setupModule();
										}
									}
									catch (Exception $e)
									{
										$this->admin_view->addMessage(
											Core_Message::get($e->getMessage(), 'error')
										);
									}
								}

								$oAdminModule = Core_Entity::factory('Module')->getByPath($oModule->path, FALSE);
								if (!$oAdminModule)
								{
									// Удаляем папку с файлами в случае с успешной установкой
									is_dir($this->tmpDir) && Core_File::deleteDir($this->tmpDir);

									// Если не было ошибок
									if (!$bErrorInstall)
									{
										try
										{
											$this->admin_view->addMessage(
												Core_Message::get(Core::_('Market.install_success', $oModule->name))
											);
										} catch (Exception $e) {}
									}
								}
								else
								{
									$this->admin_view->addMessage(
										Core_Message::get(Core::_('Market.installed', $oModule->name))
									);
								}

								// Вывод списка
								$this
									->getMarket()
									->showItemsList();
							}
						}
					}
				}

				if ($error > 0)
				{
					$sModuleName = $error < 10 ? 'Update' : 'Market';

					$this->admin_view->addMessage(
						Core_Message::get(Core::_($sModuleName . '.server_error_respond_' . $error), 'error')
					);
				}
			}


		}
		catch (Exception $e)
		{
			$this->admin_view->addMessage(
				Core_Message::get(Core::_('Market.server_error_respond_0'), 'error')
			);
		}

		return NULL;
	}

	/**
	 * Get fields
	 * @param array $array
	 * @return array
	 */
	public function getFields(array $array)
	{
		$return = array();

		// цикл по дереву 'fields'
		foreach ($array as $aFieldsValue)
		{
			$tmp = array(
				'Caption' => strval($aFieldsValue->caption),
				'Type' => strval($aFieldsValue->attributes()->type),
				'Value' => strval($aFieldsValue->value),
				'Name' => strval($aFieldsValue->name),
				'Path' => strval($aFieldsValue->path),
				'Extension' => strval($aFieldsValue->extension),
				'MaxWidth' => strval($aFieldsValue->max_width),
				'MaxHeight' => strval($aFieldsValue->max_height),
				'ListValue' => array()
			);

			$aXmlValues = $aFieldsValue->xpath("value/list");

			// Значения для списка
			if (count($aXmlValues))
			{
				foreach ($aXmlValues as $oXmlValue)
				{
					$tmp['ListValue'][strval($oXmlValue->attributes()->value)] = strval($oXmlValue);
				}
			}

			$return[] = $tmp;
		}

		return $return;
	}

	/**
	 * Загрузка файла модуля
	 *
	 * @param string $path
	 * @return Core_Http
	 */
	public function getModuleFile($path)
	{
		$url = 'http://' . $this->update_server . $path . "&domain=".rawurlencode($this->domain) .
		'&protocol=' . (Core::httpsUses() ? 'https' : 'http') .
		"&login=" . rawurlencode($this->login) .
		"&contract=" . rawurlencode(md5($this->contract)) .
		"&pin=" . rawurlencode(md5($this->pin)) .
		"&cms_folder=" . rawurlencode($this->cms_folder) .
		"&php_version=" . rawurlencode($this->php_version) .
		"&mysql_version=" . rawurlencode($this->mysql_version) .
		"&update_id=" . $this->update_id;

		$Core_Http = Core_Http::instance()
			->url($url)
			->port(80)
			->timeout(5)
			->execute();

		return $Core_Http;
	}

	protected $_aTmpOptions = array();

	protected function _getCategoryOptions($parentId, $level = 0)
	{
		if (isset($this->_categories[$parentId]))
		{
			foreach ($this->_categories[$parentId] as $object)
			{
				$this->_aTmpOptions[$object->id] = str_repeat('—', $level) . " " . $object->name;

				if ($object->count)
				{
					$this->_aTmpOptions[$object->id] .= " (" . $object->count . ")";
				}

				$this->_getCategoryOptions($object->id, $level + 1);
			}
		}
	}

	public function showItemsList()
	{
		$this->admin_view
			->pageTitle(Core::_('Market.title'));

		$oMainTab = Admin_Form_Entity::factory('Tab')->name('main');

		ob_start();

		if ($this->error == 0)
		{
			$this->_aTmpOptions = array(Core::_('Market.select_section'));
			$this->_getCategoryOptions(0);
			/*foreach($this->_categories as $object)
			{
				$aTmp[$object->id] = $object->name;
			}*/

			$oMainTab->add(
				Admin_Form_Entity::factory('Div')->class('row')->add(
					Admin_Form_Entity::factory('Select')
						->name('category_id')
						->value($this->category_id)
						->onchange('changeCategory(this)')
						->options($this->_aTmpOptions)
						->divAttr(array('class' => 'form-group col-lg-6 col-md-6 col-sm-6'))
				)
			);

			$sHtml = $this->getMarketItemsHtml();

			$count_pages = ceil($this->total / $this->limit);

			$oMainTab->add(
				Admin_Form_Entity::factory('Div')->class('row')
					->add(
						Admin_Form_Entity::factory('Code')->html($sHtml)
					)
			);

			if ($this->category_id && $count_pages > 1)
			{
				ob_start();

				$this->controller
					->limit($this->limit)
					->setTotalCount($this->total)
					//->showBottomActions(FALSE)
					->pageNavigation();

				$sFooter = '<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">' . ob_get_clean() . '</div>';

				$oMainTab->add(
					Admin_Form_Entity::factory('Div')->class('row')
						->add(
							Admin_Form_Entity::factory('Code')->html($sFooter)
						)
				);
			}
		}
		else
		{
			// Показ ошибок
			$this->admin_view
				->addMessage(
					Core_Message::get(Core::_('Update.server_error_respond_' . $this->error), 'error')
				);
		}

		$sWindowId = $this->controller->getWindowId();

		$oAdmin_Form_Entity_Form = Admin_Form_Entity::factory('Form')
			->controller($this->controller)
			->action($this->controller->getPath())
			->add($oMainTab)
			->add(Admin_Form_Entity::factory('Code')
				->html('<script type="text/javascript">
				function changeCategory(object)
				{
					if (object && object.tagName == "SELECT")
					{
						category_id = parseInt(object.options[object.selectedIndex].value);
						$.adminLoad({path: "/admin/market/index.php", windowId:"' . $sWindowId . '", additionalParams: "category_id=" + category_id});
					}
					return false;
				}</script>')
			)
			->execute();

		$this->admin_view
			->content(ob_get_clean())
			->show();
	}

	public function getMarketItemsHtml()
	{
		$sHtml = '<div class="market col-lg-12 col-md-12 col-sm-12 col-xs-12">';
		foreach ($this->items as $object)
		{
			$sHtml .= $this->_getMarketItemHtml($object);
		}
		$sHtml .= '</div>';

		return $sHtml;
	}

	protected function _getMarketItemHtml($object)
	{
		$sWindowId = $this->controller
			? $this->controller->getWindowId()
			: 'id_content';

		$sHtml = '<div class="col-lg-4 col-sm-6 col-xs-12 market-item">
			<div class="databox databox-xlg databox-halved radius-bordered databox-shadowed databox-vertical">
				<div class="databox-top bg-white padding-10">
					<div class="row">
						<div class="col-lg-4 col-sm-4 col-xs-4">
							<a target="_blank" href="' . $object->url . '">
								<img src="' . $object->image_small . '" style="width:80px; height:80px;" class="market-item-image bordered-3 bordered-white" />
							</a>
						</div>
						<div class="col-lg-8 col-sm-8 col-xs-8 text-align-left padding-10">
							<span class="databox-header carbon no-margin"><a target="_blank" href="' . $object->url . '">' . htmlspecialchars($object->name) . '</a></span>
							<span class="databox-text lightcarbon no-margin"> ' . htmlspecialchars($object->category_name) . ' </span>
						</div>
					</div>
				</div>
				<div class="databox-bottom bg-white no-padding">
					<div class="databox-row row-4">
						<div class="databox-cell cell-12 text-align-left">
							<div class="databox-text darkgray"> ' . $object->description . '</div>
						</div>
					</div>
					<div class="databox-row row-6">
		';

		if ($object->installed)
		{
			$sHtml .= '<div class="databox-row row-6 padding-10">
					<span class="btn btn-labeled btn-default pull-right">
						<i class="btn-label fa fa-check"></i>
						' . Core::_('Market.installed') . '
					</span>
				</div>';
		}
		else
		{
			$sHtml .= '<div class="databox-row row-6 padding-10">

				<div class="databox-cell cell-6 no-padding">
					<div class="databox-text black market-item-price"> ' . (floatval($object->price)
						? number_format(round($object->price), 0, ',', ' ') . ' ' . (
							$object->currency == 'руб.'
								? '<i class="fa fa-rub"></i>'
								: $object->currency
						)
						: Core::_('Market.free')
					) . ' </div>
				</div>

				<div class="databox-cell cell-6 no-padding">
				';

			if ($object->paid && !$object->installed || $object->price == 0)
			{
				$sHtml .= '
					<a class="btn btn-labeled btn-darkorange pull-right" onclick="res =confirm(\'' . Core::_('Market.install_warning') . '\'); if (res){ $.adminLoad({path:\'/admin/market/index.php\',action:\'\',operation:\'\',additionalParams:\'install=' . $object->id . '&category_id=' . $this->category_id . '&current=' . $this->page . '\',windowId:\'' . $sWindowId . '\'}); } return false" href="/admin/market/index.php?hostcms[window]=' . $sWindowId . '&install=' . $object->id . '&category_id=' . $this->category_id . '&current=' . $this->page . '">
						<i class="btn-label fa fa-download"></i>
						' . Core::_('Market.install') . '
					</a>
				';
			}
			else
			{
				$sHtml .= '<a class="btn btn-labeled btn-palegreen pull-right" target="_blank" href="' . $object->url . '"><i class="btn-label fa fa-shopping-cart"></i>' . Core::_('Market.buy') . '</a>';
			}

			$sHtml .= '</div>
			</div>';
		}

		$sHtml .= '</div>
				</div>
			</div>
		</div>';

		return $sHtml;
	}
}