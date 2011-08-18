<?php
/**
 * @version		
 * @package		Civicrm
 * @subpackage	Joomla Plugin
 * @copyright	Copyright (C) 2005 - 2011 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

jimport('joomla.plugin.plugin');

require_once JPATH_SITE.'/components/com_content/router.php';

/**
 * CiviCRM Search plugin
 *
 * @package     Joomla.Plugin
 * @subpackage  Search.content
 * @since       1.6
 */
class plgSearchCiviSearch extends JPlugin
{
    /**
     * @return array An array of search areas
     */
    function onContentSearchAreas()
    {
        static $areas = array( 'events' => 'Events' );
        return $areas;
    }

    /**
     * Search method
     * The sql must return the following fields that are used in a common display
     * routine: href, title, section, created, text, browsernav
     * @param string Target search string
     * @param string mathcing option, exact|any|all
     * @param string ordering option, newest|oldest|popular|alpha|category
     * @param mixed An array if the search it to be restricted to areas, null if search all
     */
    function onContentSearch($text, $phrase='', $ordering='', $areas=null)
    {
        $db     = JFactory::getDbo();
        $app    = JFactory::getApplication();
        $user   = JFactory::getUser();
        $groups = implode(',', $user->getAuthorisedViewLevels());
        $tag = JFactory::getLanguage()->getTag();

        require_once JPATH_SITE.'/components/com_content/helpers/route.php';
        require_once JPATH_SITE.'/administrator/components/com_search/helpers/search.php';

        $searchText = $text;
        if (is_array($areas)) {
            if (!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
                return array();
            }
        }

        $sContent       = $this->params->get('search_content',      1);
        $sArchived      = $this->params->get('search_archived',     1);
        $limit          = $this->params->def('search_limit',        50);

        $state          = array();
        if ($sContent) {
            $state[]=1;
        }
        if ($sArchived) {
            $state[]=2;
        }


        $text = trim($text);
        if ($text == '') {
            return array();
        }

        switch ($ordering) {
            case 'alpha':
                $order = 'a.title ASC';
                break;
            case 'newest':
                $order = 'a.start_date DESC';
                break;
            case 'oldest':
                $order = 'a.start_date ASC';
                break;
            default:
                $order = 'a.id DESC';
        }

        $text   = $db->Quote('%'.$db->getEscaped($text, true).'%', false);
        $query  = $db->getQuery(true);

        $return = array();
        if (!empty($state)) {
            $query->select('a.title, a.description AS text, a.created_date AS created, "2" AS browsernav, a.id AS eventid');
            $query->from('civicrm_event AS a');
            $query->where('(a.title LIKE '. $text .' OR a.description LIKE '. $text .' OR a.summary LIKE '. $text .')  AND a.is_public = 1  AND a.is_template = 0 AND  a.is_active = 1 ');
            $query->group('a.id');
            $query->order($order);
            if ($app->isSite() && $app->getLanguageFilter()) {
                $query->where('a.language in (' . $db->Quote(JFactory::getLanguage()->getTag()) . ',' . $db->Quote('*') . ')');
            }

            $db->setQuery($query, 0, $limit);
            $rows = $db->loadObjectList();

            if ($rows) {
                $count = count($rows);
                for ($i = 0; $i < $count; $i++) {
                    $rows[$i]->href = ContentHelperRoute::getCategoryRoute($rows[$i]->slug);
                    $rows[$i]->href = 'index.php?option=com_civicrm&task=civicrm/event/info&reset=1&id='.$rows[$i]->eventid;
                    $rows[$i]->section  = JText::_('Event');
                }

                $return = array();
                foreach($rows AS $key => $category) {
                    if (searchHelper::checkNoHTML($category, $searchText, array('name', 'title', 'text'))) {
                        $return[] = $category;
                    }
                }
            }
        }
        return $return;
    }
}
