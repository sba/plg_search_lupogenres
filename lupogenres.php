<?php
/**
 * @package     LUPO
 * @copyright   Copyright (C) databauer / Stefan Bauer
 * @author      Stefan Bauer
 * @link        https://www.ludothekprogramm.ch
 * @license     License GNU General Public License version 2 or later
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

class plgSearchLupogenres extends JPlugin {
	/**
	 * Constructor
	 *
	 * @access      protected
	 *
	 * @param   object  $subject  The object to observe
	 * @param   array   $config   An array that holds the plugin configuration
	 *
	 * @since       1.5
	 */
	public function __construct(&$subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	/**
	 * @return array An array of search areas
	 */
	function onContentSearchAreas() {
		static $areas = array(
			'lupo' => 'PLG_SEARCH_LUPO_GENRES'
		);

		return $areas;
	}

	/**
	 * Search method
	 *
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 *
	 * @param   string Target search string
	 * @param   string mathcing option, exact|any|all
	 * @param   string ordering option, newest|oldest|popular|alpha|category
	 */
	function onContentSearch($text, $phrase = '', $ordering = '', $areas = null) {
		if (!class_exists('LupoModelLupo')) {
			JLoader::import('lupo', JPATH_SITE . '/components/com_lupo/models');
		}
		$model = new LupoModelLupo();


		$db     = JFactory::getDbo();
		$app    = JFactory::getApplication();
		$user   = JFactory::getUser();
		$groups = implode(',', $user->getAuthorisedViewLevels());

		$request_type = JRequest::getVar('type');

		if (is_array($areas)) {
			if (!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
				return array();
			}
		}

		$limit         = $this->params->def('search_limit', 50);
		$params_itemid = $this->params->def('Itemid', '');

		$text = trim($text);
		if ($text == '') {
			return array();
		}

		$section = JText::_('PLG_SEARCH_LUPOGENRES_GENRES');


		//search for exact search-phrase
		$words  = array($text);
		$wheres = array();
		foreach ($words as $word) {
			$word      = $db->quote('%' . $db->escape($word, true) . '%', false);
			$wheres2   = array();
			$wheres2[] = 'LOWER(a.genre) LIKE LOWER(' . $word . ')';
			$wheres[]  = implode(' OR ', $wheres2);
		}
		$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';

		$query = $db->getQuery(true);
		$query->select('a.id
						, genre as title
						, alias
						, "" AS created
						, a.id AS slug
						, 0 AS catslug
						, CONCAT((SELECT
									  COUNT(#__lupo_game_genre.id) AS nbr_genre
									FROM
									  #__lupo_game_genre
									  LEFT JOIN #__lupo_genres ON (#__lupo_genres.id = #__lupo_game_genre.genreid)
									WHERE #__lupo_game_genre.genreid = a.id) ," Spiele des Genres ", genre, " gefunden") as text
						, "' . $section . '" AS section
						, "2" AS browsernav');
		//TODO: translate language-strings in query
		$query->from('#__lupo_genres AS a');
		$query->where($where);
		$query->order('genre');
		$db->setQuery($query, 0, $limit);

		$rows = $db->loadObjectList();

		if ($rows) {
			foreach ($rows as $key => $row) {

				//get Itemid for each search-result. Seach first for lupo-category menuitem, if not set take plugin-Itemid if set
				$query = $db->getQuery(true);
				$query->select('m.id');
				$query->from('#__menu AS m');
				$query->where('m.link = "index.php?option=com_lupo&view=genre&id=' . $row->slug . '"');
				$db->setQuery($query);
				$menu   = $db->loadAssoc();
				$itemid = "";
				if (isset($menu['id'])) {
					$itemid = '&Itemid=' . $menu['id'];
				} else {
					if ($params_itemid != '') {
						$itemid = '&Itemid=' . $params_itemid;
					}
				}
				/*                if ($request_type == 'json'){
									$rows[$key]->text = $row->cat_agecat;
								}*/
				$rows[$key]->href = 'index.php?option=com_lupo&view=genre&id=' . $row->alias . $itemid;

				$rows[$key]->image = false;
			}
		}

		return $rows;
	}
}
