<?php

defined('HOSTCMS') || exit('HostCMS: access denied.');

/**
 *
 * Контроллер загрузки значений списка товаров для select доп. св-в
 *
 * @package HostCMS
 * @subpackage Shop
 * @version 6.x
 * @author Hostmake LLC
 * @copyright © 2005-2016 ООО "Хостмэйк" (Hostmake LLC), http://www.hostcms.ru
 */
class Shop_Controller_Load_Select_Options extends Admin_Form_Action_Controller_Type_Load_Select_Options
{
	/**
	 * Add value
	 * @return self
	 */
	public function addValues()
	{
		foreach ($this->_objects as $Object)
		{
			$oTmp = new stdClass();
			$oTmp->value = $Object->id;
			$oTmp->name = !$Object->shortcut_id
				? $Object->name
				: $Object->Shop_Item->name;

			$this->_values[] = $oTmp;
		}

		return $this;
	}

	/**
	 * Get count of objects
	 * @return self
	 */
	protected function _getCount()
	{
		return $this->_model->getCount();
	}

	/**
	 * Find objects by $this->_model
	 * @return self
	 */
	protected function _findObjects()
	{
		$oShop = $this->_model->Shop;

		$offset = 0;
		$limit = 1000;

		switch ($oShop->items_sorting_direction)
		{
			case 1:
				$items_sorting_direction = 'DESC';
			break;
			case 0:
			default:
				$items_sorting_direction = 'ASC';
		}

		$this->_model
			->queryBuilder()
			->clearOrderBy()
			->clearSelect()
			->select('id', 'shortcut_id', 'name');

		// Определяем поле сортировки информационных элементов
		switch ($oShop->items_sorting_field)
		{
			case 1:
				$this->_model
					->queryBuilder()
					->orderBy('shop_items.name', $items_sorting_direction)
					->orderBy('shop_items.sorting', $items_sorting_direction);
				break;
			case 2:
				$this->_model
					->queryBuilder()
					->orderBy('shop_items.sorting', $items_sorting_direction)
					->orderBy('shop_items.name', $items_sorting_direction);
				break;
			case 0:
			default:
				$this->_model
					->queryBuilder()
					->orderBy('shop_items.datetime', $items_sorting_direction)
					->orderBy('shop_items.sorting', $items_sorting_direction);
		}

		$this->_objects = array();

		do {
			$this->_model
				->queryBuilder()
				->offset($offset)
				->limit($limit);

			$aTmpObjects = $this->_model->findAll(FALSE);

			count($aTmpObjects)
				&& $this->_objects = array_merge($this->_objects, $aTmpObjects);

			$offset += $limit;
		}
		while (count($aTmpObjects));

		return $this;
	}
}