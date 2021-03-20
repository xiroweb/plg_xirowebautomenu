<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Xiroweb
 *
 * @copyright   Copyright (C) 2020 XiroWeb. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;


class plgContentXirowebautomenu extends CMSPlugin
{

	private $menuItemModel;

	protected $autoloadLanguage = true;

	protected $app;

	function __construct(&$subject, $config = array()) {
		// call parent constructor
		parent::__construct($subject, $config);


	}

	public function onContentPrepareForm(Form $form, $data)
	{

		$context = $form->getName();

		if (!in_array($context, array('com_categories.categorycom_content')))
		{
			return true;
		}

		if (!$this->checkMainMenu()) {
			return true;
		}

		// When a category is edited, the context is com_categories.categorycom_content
		if (strpos($context, 'com_categories.category') === 0)
		{
			// off if edit
			if (is_array($data) && key_exists('id', $data))
			{
				return true;
			}

			if (is_object($data) && isset($data->id))
			{
				return true;
			}
		}

		Form::addFormPath(__DIR__ . '/form');
		$form->loadFile('automenu',true);

		return true;
	}

	/**
	 * The save event.
	 *
	 * @param   string   $context  The context
	 * @param   JTable   $item     The table
	 * @param   boolean  $isNew    Is new item
	 * @param   array    $data     The validated data
	 *
	 * @return  boolean
	 *
	 * @since   3.7.0
	 */
	public function onContentAfterSave($context, $item, $isNew, $data = array())
	{
		// Check if data is an array and the item has an id
		if (!is_array($data) || empty($item->id) || empty($data['automenu']))
		{
			return true;
		}

		if (!$this->checkMainMenu()) 
		{
			return true;
		}
	
		// Get MenuItemModel.
		JLoader::register('MenusHelper', JPATH_ADMINISTRATOR . '/components/com_menus/helpers/menus.php');
		BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/models/', 'MenusModel');
		Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/tables/');
		$this->menuItemModel = BaseDatabaseModel::getInstance('Item', 'MenusModel');

		// Insert menuitems level 1.
		$menuItem =
			array(
				'menutype'     => 'mainmenu',
				'title'        => $item->title,
				'alias'		   => $item->alias,
				'link'         => 'index.php?option=com_content&view=category&layout=blog&id=' . $item->id,
				'component_id' => 22,
				'params'       => array(
					'layout_type'             => 'blog',
					'show_category_heading_title_text'  => '',
					'show_category_title'  => '',
					'show_description'  => '',
					'show_description_image'  => '',
					'maxLevel'  => '',
					'show_empty_categories'  => '',
					'show_no_articles'  => '',
					'show_subcat_desc'  => '',
					'show_cat_num_articles'  => '',
					'show_cat_tags'  => '',
					'page_subheading'  => '',
					'num_leading_articles'  => '',
					'num_intro_articles'  => '',
					'num_columns'  => '',
					'num_links'  => '',
					'multi_column_order'  => '',
					'show_subcategory_content'  => '',
					'orderby_pri'  => '',
					'orderby_sec'  => '',
					'order_date'  => '',
					'show_pagination'  => '',
					'show_pagination_results'  => '',
					'show_featured'  => '',
					'article_layout'  => '_:default',
					'show_title'  => '',
					'link_titles'  => '',
					'show_intro'  => '',
					'info_block_position'  => '',
					'info_block_show_title'  => '',
					'show_category'  => '',
					'link_category'  => '',
					'link_parent_category' => '',
					'show_associations' => '',
					'show_author' => '',
					'link_author' => '',
					'show_create_date' => '',
					'show_modify_date' => '',
					'show_publish_date' => '',
					'show_item_navigation' => '',
					'show_vote' => '',
					'show_readmore' => '',
					'show_readmore_title' => '',
					'show_icons' => '',
					'show_print_icon' => '',
					'show_email_icon' => '',
					'show_hits' => '',
					'show_tags' => '',
					'show_noauth' => '',
					'show_feed_link' => '',
					'feed_summary' => '',
					'menu-anchor_title' => '',
					'menu-anchor_css' => '',
					'menu_image' => '',
					'menu_image_css' => '',
					'menu_text' => 1,
					'menu_show' => 1,
					'page_title' => '',
					'show_page_heading' => '',
					'page_heading' => '',
					'pageclass_sfx' => '',
					'menu-meta_description' => '',
					'menu-meta_keywords' => '',
					'robots' => '',
					'secure' => 0
				),
				'language'					=> $data['language'],
				'published'					=> $data['published'],
				'access'					=> $data['access'],
			
			);

			if ($item->parent_id > 1) 
			{
				$parent_ids = $this->getMenuId($item->parent_id);

				if (count($parent_ids)) {
					$menuItem['parent_id'] = $parent_ids[0];
				}
			}

		try
		{
			$this->addMenuItem($menuItem);
		}
		catch (Exception $e)
		{
			$this->app->enqueueMessage(JText::_('PLG_CONTENT_XIROWEBAUTOMENU_MENU_ITEM_CREATE_ERROR'), 'warning');
			return true;
		}

		$this->app->enqueueMessage(JText::_('PLG_CONTENT_XIROWEBAUTOMENU_MENU_ITEM_CREATE_SUCCESS'), 'success');

		return true;
	}

	public function onCategoryChangeState($extension, $pks, $value)
	{
		if (!in_array($extension, array('com_content')))
		{
			return true;
		}
	
		$menu_pks = $this->getMenuId($pks);
		

		if (empty($menu_pks)) 
		{
			return true;
		}

		// Get MenuItemModel.
		JLoader::register('MenusHelper', JPATH_ADMINISTRATOR . '/components/com_menus/helpers/menus.php');
		BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/models/', 'MenusModel');
		Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/tables/');
		$this->menuItemModel = BaseDatabaseModel::getInstance('Item', 'MenusModel');

		try
		{
			$this->menuItemModel->publish($menu_pks, $value);
		}
		catch (Exception $e)
		{

			return true;
		}

		$this->app->enqueueMessage(JText::_('PLG_CONTENT_XIROWEBAUTOMENU_MENU_ITEM_PUBLISH_'.$value), 'notice');

		return true;
	}

	public function onContentAfterDelete($context, $article)
	{

		if (!in_array($context, array('com_categories.category')))
		{
			return true;
		}

		if (!in_array($article->extension, array('com_content'))) {
			return true;
		}

		$pk_menu = $this->getMenuId($article->id);

		if (empty($pk_menu)) {
			return true; 
		}
		
		JLoader::register('MenusHelper', JPATH_ADMINISTRATOR . '/components/com_menus/helpers/menus.php');
		BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/models/', 'MenusModel');
		Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/tables/');
		$this->menuItemModel = BaseDatabaseModel::getInstance('Item', 'MenusModel');

		try
		{
			// maybe user active menu item after trash category item
			$pk_menu_publish = $pk_menu;
			$this->menuItemModel->publish($pk_menu, '-2');

			$this->menuItemModel->delete($pk_menu);
		}
		catch (Exception $e)
		{
			return true;
		}
	

		$this->app->enqueueMessage(JText::_('PLG_CONTENT_XIROWEBAUTOMENU_MENU_ITEM_DELETE_SUCCESS'), 'success');

		return true;
    }

	/**
	 * Adds menuitems.
	 */
	

	private function addMenuItem(array $menuItem)
	{
		$user    = Factory::getUser();

		$this->menuItemModel->setState('item.id', 0);

			// Set values which are always the same.
			$menuItem['id']              = 0;
			$menuItem['created_user_id'] = $user->id;
			$menuItem['note']            = '';
			$menuItem['img']             = '';
			$menuItem['associations']    = array();
			$menuItem['client_id']       = 0;
			$menuItem['home']            = 0;

			// Set browserNav to default if not set
			if (!isset($menuItem['browserNav']))
			{
				$menuItem['browserNav'] = 0;
			}


			// Set type to 'component' if not set
			if (!isset($menuItem['type']))
			{
				$menuItem['type'] = 'component';
			}

			// Set template_style_id to global if not set
			if (!isset($menuItem['template_style_id']))
			{
				$menuItem['template_style_id'] = 0;
			}

			// Set parent_id to root (1) if not set
			if (!isset($menuItem['parent_id']))
			{
				$menuItem['parent_id'] = 1;
			}

			if (!$this->menuItemModel->save($menuItem))
			{
				// Try two times with another alias (-1 and -2).
				$menuItem['alias'] .= '-1';

				if (!$this->menuItemModel->save($menuItem))
				{
					$menuItem['alias'] = substr_replace($menuItem['alias'], '2', -1);

					if (!$this->menuItemModel->save($menuItem))
					{
						throw new Exception($menuItem['title'] . ' => ' . $menuItem['alias'] . ' : ' . $this->menuItemModel->getError());
						return false;
					}
				}
			}

		

		return true;
	}

	protected function getMenuId($cat_id)
	{
		if (!is_array($cat_id)){
			$cat_id = (array) $cat_id;
		}

		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('DISTINCT(a.id) AS id, a.link as link')
			->from('#__menu AS a');

		$query->where('a.menutype = ' . $db->quote('mainmenu'));

		$query->where($db->quoteName('a.client_id') . ' = ' . (int) 0);

		// $query->where('a.published != -2');
		$query->order('a.lft ASC');

		// Get the options.
		$db->setQuery($query);

		try
		{
			$menus = $db->loadAssocList();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());
		}

		$ids_menu = array();

		foreach ($menus as $key => $menuitem)
		{
			$args = array();
			parse_str(parse_url($menuitem['link'], PHP_URL_QUERY), $args);	
			if (isset($args['id'])) {
					if(in_array($args['id'], $cat_id)) {
						$ids_menu[] = $menuitem['id'];
					}
			}
		} 

		return $ids_menu;

	}


	protected function checkMainMenu()
	{

		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from('#__menu_types AS a');

		$query->where('a.menutype = ' . $db->quote('mainmenu'));

		$query->where($db->quoteName('a.client_id') . ' = ' . (int) 0);

		$db->setQuery($query);

		$result = $db->loadResult();

		return $result;
	}
        
}
