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

if(version_compare(JVERSION,'1.6.0','ge')) {
    
    jimport('joomla.plugin.plugin');
    require_once JPATH_SITE.'/components/com_content/router.php';

} else {
    $mainframe->registerEvent( 'onSearch', 'plgSearchCiviSearch' );
    $mainframe->registerEvent( 'onSearchAreas', 'plgSearchCiviSearchAreas' );

    JPlugin::loadLanguage( 'plg_search_civisearch' );
}
/**
 * CiviCRM Search plugin
 *
 * @package     Joomla.Plugin
 * @subpackage  Search.content
 * @since       1.5
 */

function &plgSearchCiviSearchAreas()
{
    static $areas = array(
                          'events' => 'Events'
                         );
    return $areas;
}


function plgSearchCiviSearch( $text, $phrase='', $ordering='', $areas=null )
{
	$return = plgSearchCiviSearch::onContentSearch($text, $phrase='', $ordering='', $areas=null);
	return $return;
}


class plgSearchCiviSearch extends JPlugin
{
    /**
     * @return array An array of search areas
     */
    function onContentSearchAreas()
    {
        $areas = plgSearchCiviSearchAreas();
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
        if(version_compare(JVERSION,'1.6.0','ge')) {

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
    
            $sEvent = $this->params->get('search_event');
            $limit = $this->params->def('search_limit',        50);
        } else {
            $db     =& JFactory::getDBO();
            $user   =& JFactory::getUser();
            $searchText = $text;

            require_once(JPATH_SITE.DS.'components'.DS.'com_content'.DS.'helpers'.DS.'route.php');

            if (is_array( $areas )) {
                if (!array_intersect( $areas, array_keys( plgSearchCategoryAreas() ) )) {
                    return array();
                }
            }
        
            // load plugin params info
                    $plugin =& JPluginHelper::getPlugin('search', 'civisearch');
            $pluginParams = new JParameter( $plugin->params );
        
            $limit = $pluginParams->def( 'search_limit', 50 );
            
        }

        $text = trim( $text );
        if ( $text == '' ) {
            return array();
        }

        switch ( $ordering ) {
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

    
    
        $text   = $db->Quote( '%'.$db->getEscaped( $text, true ).'%', false );
        $select = 'a.title, a.description AS text, a.created_date AS created, a.summary AS summary, a.id AS eventid';
        $from = 'civicrm_event AS a';
        $where = '(a.title LIKE '. $text .' OR a.description LIKE '. $text .' OR a.summary LIKE '. $text .')  AND a.is_public = 1  AND a.is_template = 0 AND  a.is_active = 1';
        $group = 'a.id';
        $return = array();
        
        if(version_compare(JVERSION,'1.6.0','ge')) {
            $query  = $db->getQuery(true);
            $query->select($select);
            $query->from($from);
            $query->where($where);
            $query->group($group);
            $query->order($order);
            if ($app->isSite() && $app->getLanguageFilter()) {
                $query->where('a.language in (' . $db->Quote(JFactory::getLanguage()->getTag()) . ',' . $db->Quote('*') . ')');
            }
        } else {
            $query  = 'SELECT '.$select
                    . ' FROM '.$from
                    . ' WHERE '.$where
                    . ' GROUP BY '.$group
                    . ' ORDER BY '. $order
                    ;
        }
    
        $db->setQuery($query, 0, $limit);
        $rows = $db->loadObjectList();

        if ($rows) {
            $count = count($rows);
            for ($i = 0; $i < $count; $i++) {
                $rows[$i]->href = 'index.php?option=com_civicrm&task=civicrm/event/info&reset=1&id='.$rows[$i]->eventid;
                $rows[$i]->section  = JText::_('Event');
            }

            $return = array();
            foreach($rows AS $key => $event) {
                if (searchHelper::checkNoHTML($event, $searchText, array('summary', 'title', 'text'))) {
                    $return[] = $event;
                }
            }
        }
        
        return $return;
    } 
    
}
