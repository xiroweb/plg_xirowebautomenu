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
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Table\Table;


class plgContentXirowebautomenu extends CMSPlugin
{

	private $menuItemModel;

	protected $autoloadLanguage = true;


    	/**
	 * Constructor.
	 *
	 * @param 	$subject
	 * @param	array $config
	 */
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

		if (!$this->checkMainMenu()) {
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
					'show_category_title'     => '',
					'num_leading_articles'    => '',
					'num_intro_articles'      => '',
					'num_columns'             => '',
					'num_links'               => '',
					'multi_column_order'      => 1,
					'orderby_sec'             => 'rdate',
					'order_date'              => 'published',
					'show_pagination'         => 0,
					'show_pagination_results' => 0,
					'show_category'           => 0,
					'info_bloc_position'      => 0,
					'show_publish_date'       => 0,
					'show_hits'               => 0,
					'show_feed_link'          => 0,
					'menu_text'               => 0,
					'show_page_heading'       => 0,
					'secure'                  => 0,
				),
				'language'					=> $data['language'],
				'published'					=> $data['published'],
				'access'					=> $data['access'],
			
			);

			if ($item->parent_id > 1) {
				$menuItem['parent_id']= $this->getMenuId($item->parent_id);
			}

		try
		{
			$menuIdsLevel1 = $this->addMenuItem($menuItem);
		}
		catch (Exception $e)
		{
			// $response            = array();
			// $response['success'] = false;
			// $response['message'] = JText::sprintf('PLG_SAMPLEDATA_BLOG_STEP_FAILED', 2, $e->getMessage());

			return false;
		}


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
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->select('DISTINCT(a.id) AS id, a.link as link')
			->from('#__menu AS a');

		$query->where('a.menutype = ' . $db->quote('mainmenu'));

		$query->where($db->quoteName('a.client_id') . ' = ' . (int) 0);

		$query->where('a.published != -2')
			->order('a.lft ASC');

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
		$id_menu = array();

		foreach ($menus as $key => $menuitem) {
			$args = array();
			parse_str(parse_url($menuitem['link'], PHP_URL_QUERY), $args);	
			if (isset($args['id'])) {
					if($args['id'] == $cat_id) {
						$id_menu[] = $menuitem['id'];
					}
			}
		} 

		if (count($id_menu)) {
			return $id_menu[0];
		} else {
			return 1;
		}

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
