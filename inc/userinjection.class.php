<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2011 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file: Remi Collet
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginDatainjectionUserInjection extends User
                                       implements PluginDatainjectionInjectionInterface {


   function __construct() {
      $this->table = getTableForItemType(get_parent_class($this));
   }


   function isPrimaryType() {
      return true;
   }


   function connectedTo() {
      return array();
   }


   function getOptions($primary_type = '') {
      global $LANG;

      $tab = Search::getOptions(get_parent_class($this));

      //Specific to location
      $tab[3]['linkfield'] = 'locations_id';
      $tab[1]['linkfield'] = 'name';

      //Manage password
      $tab[4]['table']         = $this->getTable();
      $tab[4]['field']         = 'password';
      $tab[4]['linkfield']     = 'password';
      $tab[4]['name']          = $LANG['login'][7];
      $tab[4]['displaytype']   = 'password';

      //To manage groups : relies on a CommonDBRelation object !
      $tab[100]['name']          = $LANG['common'][35];
      $tab[100]['field']         = 'name';
      $tab[100]['table']         = getTableForItemType('Group');
      $tab[100]['linkfield']     = getForeignKeyFieldForTable($tab[100]['table']);
      $tab[100]['displaytype']   = 'relation';
      $tab[100]['relationclass'] = 'Group_User';
      $tab[100]['relationfield'] = $tab[100]['linkfield'];

      //To manage groups : relies on a CommonDBRelation object !
      $tab[101]['name']          = $LANG['Menu'][35];
      $tab[101]['field']         = 'name';
      $tab[101]['table']         = getTableForItemType('Profile');
      $tab[101]['linkfield']     = getForeignKeyFieldForTable($tab[101]['table']);
      $tab[101]['displaytype']   = 'relation';
      $tab[101]['relationclass'] = 'Profile_User';
      $tab[101]['relationfield'] = $tab[101]['linkfield'];

      $blacklist = PluginDatainjectionCommonInjectionLib::getBlacklistedOptions();
      //Remove some options because some fields cannot be imported
      $notimportable = array(13, 14, 15, 20, 80);
      $ignore_fields = array_merge($blacklist, $notimportable);

      //Add linkfield for theses fields : no massive action is allowed in the core, but they can be
      //imported using the commonlib
      $add_linkfield = array('comment' => 'comment',
                             'notepad' => 'notepad');

      foreach ($tab as $id => $tmp) {
         if (!is_array($tmp) || in_array($id,$ignore_fields)) {
            unset($tab[$id]);

         } else {
            if (in_array($tmp['field'],$add_linkfield)) {
               $tab[$id]['linkfield'] = $add_linkfield[$tmp['field']];
            }

            if (!in_array($id,$ignore_fields)) {
               if (!isset($tmp['linkfield'])) {
                  $tab[$id]['injectable'] = PluginDatainjectionCommonInjectionLib::FIELD_VIRTUAL;
               } else {
                  $tab[$id]['injectable'] = PluginDatainjectionCommonInjectionLib::FIELD_INJECTABLE;
               }

               if (isset($tmp['linkfield']) && !isset($tmp['displaytype'])) {
                  $tab[$id]['displaytype'] = 'text';
               }

               if (isset($tmp['linkfield']) && !isset($tmp['checktype'])) {
                  $tab[$id]['checktype'] = 'text';
               }
            }
         }
      }

      //Add displaytype value
      $dropdown = array("dropdown"       => array(81, 82),
                        "multiline_text" => array(16),
                        "bool"           => array(8),
                        "password"       => array(4));

      foreach ($dropdown as $type => $tabsID) {
         foreach ($tabsID as $tabID) {
            $tab[$tabID]['displaytype'] = $type;
         }
      }

      return $tab;
   }

   /**
    * Standard method to add an object into glpi
    * WILL BE INTEGRATED INTO THE CORE IN 0.80
    *
    * @param values fields to add into glpi
    * @param options options used during creation
    *
    * @return an array of IDs of newly created objects : for example array(Computer=>1, Networkport=>10)
   **/
   function addOrUpdateObject($values=array(), $options=array()) {

      $lib = new PluginDatainjectionCommonInjectionLib($this, $values, $options);
      $lib->processAddOrUpdate();
      return $lib->getInjectionResults();
   }

   function processAfterInsertOrUpdate($values, $add = true) {
      global $DB;
      if (!$add && isset($values['User']['password']) && $values['User']['password'] != '') {
         //We use an SQL request because updating the password is unesasy 
         //(self reset password process in $user->prepareInputForUpdate())
         $password
                 = sha1(unclean_cross_side_scripting_deep(stripslashes($values['User']["password"])));
         $query = "UPDATE `glpi_users` SET `password`='$password' " .
                  "WHERE `id`='".$values['User']['id']."'";
         $DB->query($query);
      }
   }

   protected function addSpecificOptionalInfos($itemtype, $field, $value) {
      //If info is a password, then fill also password2, needed for prepareInputForAdd 
      if ($field == 'password') {
         $this->setValueForItemtype($itemtype, "password2", $value);
      }
   }

}

?>