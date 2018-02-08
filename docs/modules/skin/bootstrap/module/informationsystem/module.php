<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 * Information System. Backend's Index Pages and Widget.
 *
 * @package HostCMS
 * @subpackage Skin
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Skin_Bootstrap_Module_Informationsystem_Module extends Informationsystem_Module
{
	/**
	 * Name of the skin
	 * @var string
	 */
	protected $_skinName = 'bootstrap';

	/**
	 * Name of the module
	 * @var string
	 */
	protected $_moduleName = 'informationsystem';

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->_adminPages = array(
			1 => array('title' => Core::_('Informationsystem.widget_title'))
		);
	}

	/**
	 * Show admin widget
	 * @param int $type
	 * @param boolean $ajax
	 * @return self
	 */
	public function adminPage($type = 0, $ajax = FALSE)
	{
		$type = intval($type);

		$oModule = Core_Entity::factory('Module')->getByPath($this->_moduleName);
		$this->_path = "/admin/index.php?ajaxWidgetLoad&moduleId={$oModule->id}&type={$type}";

		if ($ajax)
		{
			$this->_content();
		}
		else
		{
			?><div class="col-xs-12 col-sm-6 col-md-6 col-lg-6" id="informationsystemCommentsAdminPage" data-hostcmsurl="<?php echo htmlspecialchars($this->_path)?>">
				<script type="text/javascript">
				$.widgetLoad({ path: '<?php echo $this->_path?>', context: $('#informationsystemCommentsAdminPage') });
				</script>
			</div><?php
		}

		return TRUE;
	}

	protected function _content()
	{
		$oComments = Core_Entity::factory('Comment');
		$oComments->queryBuilder()
			->straightJoin()
			->join('comment_informationsystem_items', 'comments.id', '=', 'comment_informationsystem_items.comment_id')
			->join('informationsystem_items', 'comment_informationsystem_items.informationsystem_item_id', '=', 'informationsystem_items.id')
			->join('informationsystems', 'informationsystem_items.informationsystem_id', '=', 'informationsystems.id')
			->where('informationsystem_items.deleted', '=', 0)
			->where('informationsystems.deleted', '=', 0)
			->where('site_id', '=', CURRENT_SITE)
			->orderBy('comments.datetime', 'DESC')
			->limit(5);

		// Права доступа пользователя к комментариям
		$oUser = Core_Entity::factory('User')->getCurrent();
		if ($oUser->superuser == 0 && $oUser->only_access_my_own == 1)
		{
			$oComments->queryBuilder()->where('comments.user_id', '=', $oUser->id);
		}

		$aComments = $oComments->findAll(FALSE);

		if (count($aComments) > 0)
		{
			?><div class="widget">
				<div class="widget-header bordered-bottom bordered-themesecondary">
					<i class="widget-icon fa fa-comments themesecondary"></i>
					<span class="widget-caption themesecondary"><?php echo Core::_('Informationsystem.widget_title')?></span>
					<div class="widget-buttons">
						<a data-toggle="maximize">
							<i class="fa fa-expand gray"></i>
						</a>
						<a onclick="$(this).find('i').addClass('fa-spin'); $.widgetLoad({ path: '<?php echo $this->_path?>', context: $('#informationsystemCommentsAdminPage'), 'button': $(this).find('i') });">
							<i class="fa fa-refresh gray"></i>
						</a>
					</div>
				</div>
				<div class="widget-body">
					<div class="widget-main no-padding">
						<div class="task-container">
							<ul class="tasks-list">
							<?php
							$masColorNames = array('yellow', 'orange', 'palegreen');
							$color = 0;

							$iComments_Admin_Form_Id = 52;
							$oComments_Admin_Form = Core_Entity::factory('Admin_Form', $iComments_Admin_Form_Id);
							$oComments_Admin_Form_Controller = Admin_Form_Controller::create($oComments_Admin_Form)
								->window('id_content');
							$sInformationsystemCommentsHref = '/admin/informationsystem/item/comment/index.php';

							foreach($aComments as $oComment)
							{
								$sEditHref = $oComments_Admin_Form_Controller->getAdminActionLoadHref($sInformationsystemCommentsHref, 'edit', NULL, 0, $oComment->id);
								$sEditOnClick = $oComments_Admin_Form_Controller->getAdminActionLoadAjax($sInformationsystemCommentsHref, 'edit', NULL, 0, $oComment->id);

								$sChangeActiveHref = $oComments_Admin_Form_Controller->getAdminActionLoadHref($sInformationsystemCommentsHref, 'changeActive', NULL, 0, $oComment->id);

								$sMarkDeletedHref = $oComments_Admin_Form_Controller->getAdminActionLoadHref($sInformationsystemCommentsHref, 'markDeleted', NULL, 0, $oComment->id);

								?>
								<li class="task-item">
									<div class="row">
										<div class="col-lg-9 col-md-9 col-sm-9 col-xs-9">
											<div class="task-state">
												<span class="label label-<?php echo $masColorNames[$color == 3 ? $color = 0 : $color]; ++$color;?>">
												<?php echo $oComment->subject != ''
													? trim(htmlspecialchars(Core_Str::cut(strip_tags(html_entity_decode($oComment->subject, ENT_COMPAT, 'UTF-8')), 150)))
													: Core::_('Admin_Form.noSubject')?>
												</span>
											</div>
										</div>
										<div class="col-lg-3 col-md-3 col-sm-3 col-xs-3">
											<div class="task-time"><?php echo Core_Date::sql2date($oComment->datetime)?></div>
										</div>
									</div>
									<div class="row">
										<div class="col-lg-12">
											<div class="task-body"><?php echo trim(htmlspecialchars(Core_Str::cut(strip_tags(html_entity_decode($oComment->text, ENT_COMPAT, 'UTF-8')), 150)))?></div>
										</div>
									</div>
									<div class="row">
										<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
											<div class="task-creator pull-left">
												<div class="btn-group pull-right">
													<a class="btn btn-default btn-xs darkgray" title="<?php echo Core::_('Comment.change_active')?>" href="<?php echo $sChangeActiveHref?>" onclick="$.widgetRequest({path: '<?php echo $sChangeActiveHref?>', context: $('#informationsystemCommentsAdminPage')}); return false"><i class="fa <?php echo $oComment->active ? "fa-dot-circle-o" : "fa-circle-o"?>"></i> </a>
													<a href="<?php echo $sEditHref?>" onclick="<?php echo $sEditOnClick?>" class="btn btn-default btn-xs darkgray" title="<?php echo Core::_('Comment.edit')?>"><i class="fa fa-pencil"></i> </a>
													<a class="btn btn-default btn-xs darkgray" title="<?php echo Core::_('Comment.delete')?>" href="<?php echo $sMarkDeletedHref?>" onclick="res = confirm('<?php echo Core::_('Admin_Form.confirm_dialog', htmlspecialchars(Core::_('Admin_Form.msg_information_alt_delete')))?>'); if (res) { $.widgetRequest({path: '<?php echo $sMarkDeletedHref?>', context: $('#informationsystemCommentsAdminPage')}); } return false"><i class="fa fa-times"></i></a>
													<?php
													if ($oComment->active)
													{
														$oStructure = $oComment->Informationsystem_Item->Informationsystem->Structure;

														$oCurrentAlias = Core_Entity::factory('Site', CURRENT_SITE)->getCurrentAlias();

														if ($oCurrentAlias)
														{
															?><a class="btn btn-default btn-xs darkgray" title="<?php echo Core::_('Comment.view_comment')?>" href="<?php echo ($oStructure->https ? 'https://' : 'http://' ) . $oCurrentAlias->name . $oStructure->getPath() . $oComment->Informationsystem_Item->getPath() . '#comment' . $oComment->id?>" target="_blank"><i class="fa fa-external-link"></i> </a><?php
														}
													}
													?>
												</div>
											</div>
										</div>
										<div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
											<div class="task-assignedto pull-right"><?php
											if ($oComment->author != '')
											{
												?><i class="fa fa-user icon-separator"></i><?php echo htmlspecialchars($oComment->author);
											}
											?></div>
										</div>
									</div>
								</li>
							<?php
							}
							?>
							</ul>
							<div>
								<a class="btn btn-info" onclick="$.adminLoad({path: '/admin/informationsystem/item/comment/index.php'}); return false" href="/admin/informationsystem/item/comment/index.php">
									<i class="fa fa-comments"></i><?php echo Core::_('Informationsystem.widget_other_comments')?></a>
							</div>
						</div>
					</div>
				</div>
			</div><?php
		}

		return $this;
	}
}