<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Fields plugin for GLPI
 Copyright (C) 2016 by the fields Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of fields.

 fields is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 fields is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with fields. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

define ('PLUGIN_FIELDS_VERSION', '1.3.1');

if (!defined("PLUGINFIELDS_DIR")) {
   define("PLUGINFIELDS_DIR", GLPI_ROOT . "/plugins/fields");
}

if (!defined("PLUGINFIELDS_DOC_DIR") ) {
   define("PLUGINFIELDS_DOC_DIR", GLPI_PLUGIN_DOC_DIR . "/fields");
   if(!file_exists(PLUGINFIELDS_DOC_DIR)) {
      mkdir(PLUGINFIELDS_DOC_DIR);
   }
}

if (!defined("PLUGINFIELDS_CLASS_PATH")) {
   define("PLUGINFIELDS_CLASS_PATH", PLUGINFIELDS_DOC_DIR . "/inc");
   if(!file_exists(PLUGINFIELDS_CLASS_PATH)) {
      mkdir(PLUGINFIELDS_CLASS_PATH);
   }
}

if (!defined("PLUGINFIELDS_FRONT_PATH")) {
   define("PLUGINFIELDS_FRONT_PATH", PLUGINFIELDS_DOC_DIR."/front");
   if(!file_exists(PLUGINFIELDS_FRONT_PATH)) {
      mkdir(PLUGINFIELDS_FRONT_PATH);
   }
}

// Init the hooks of the plugins -Needed
function plugin_init_fields() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['fields'] = true;

   include_once(PLUGINFIELDS_DIR . "/inc/autoload.php");

   $options = array(
      PLUGINFIELDS_CLASS_PATH
   );
   $pluginfields_autoloader = new PluginFieldsAutoloader($options);
   $pluginfields_autoloader->register();

   $plugin = new Plugin();
   if ($plugin->isInstalled('fields')
       && $plugin->isActivated('fields')
       && Session::getLoginUserID() ) {

      // Init hook about itemtype(s) for plugin fields
      $PLUGIN_HOOKS['plugin_fields'] = array();

      // When a Category is changed during ticket creation
      if (isset($_POST) && !empty($_POST) && isset($_POST['_plugin_fields_type'])) {
         if ($_SERVER['REQUEST_URI'] == Ticket::getFormURL()) {
            //$_SESSION['plugin_fields']['Ticket'] = $_POST;
            foreach ($_POST as $key => $value) {
               if (! is_array($value)) {
                  $_SESSION['plugin']['fields']['values_sent'][$key] = stripcslashes($value);
               }
            }
         }
      }

      // complete rule engine
      $PLUGIN_HOOKS['use_rules']['fields']    = array('PluginFusioninventoryTaskpostactionRule');
      $PLUGIN_HOOKS['rule_matched']['fields'] = 'plugin_fields_rule_matched';

      if (isset($_SESSION['glpiactiveentities'])) {

         $PLUGIN_HOOKS['config_page']['fields'] = 'front/container.php';

         // add entry to configuration menu
         $PLUGIN_HOOKS["menu_toadd"]['fields'] = array('config'  => 'PluginFieldsMenu');

         // add tabs to itemtypes
         Plugin::registerClass('PluginFieldsContainer',
                               array('addtabon' => array_unique(PluginFieldsContainer::getEntries())));

         //include js and css
         $PLUGIN_HOOKS['add_css']['fields'][]           = 'fields.css';
         $PLUGIN_HOOKS['add_javascript']['fields'][]    = 'fields.js.php';

         // Add/delete profiles to automaticaly to container
         $PLUGIN_HOOKS['item_add']['fields']['Profile']       = array("PluginFieldsProfile",
                                                                       "addNewProfile");
         $PLUGIN_HOOKS['pre_item_purge']['fields']['Profile'] = array("PluginFieldsProfile",
                                                                       "deleteProfile");

         //load drag and drop javascript library on Package Interface
         $PLUGIN_HOOKS['add_javascript']['fields'][] = "scripts/redips-drag-min.js";
         $PLUGIN_HOOKS['add_javascript']['fields'][] = "scripts/drag-field-row.js";
      }

      // Add Fields to Datainjection
      if ($plugin->isActivated('datainjection')) {
         $PLUGIN_HOOKS['plugin_datainjection_populate']['fields'] = "plugin_datainjection_populate_fields";
      }

      //Retrieve dom container
      $itemtypes = PluginFieldsContainer::getUsedItemtypes();
      if ($itemtypes !== false) {
         foreach ($itemtypes as $itemtype) {
            $PLUGIN_HOOKS['pre_item_update']['fields'][$itemtype] = array("PluginFieldsContainer",
                                                                          "preItemUpdate");
            $PLUGIN_HOOKS['pre_item_purge'] ['fields'][$itemtype] = array("PluginFieldsContainer",
                                                                          "preItemPurge");
            $PLUGIN_HOOKS['item_add']['fields'][$itemtype]        = array("PluginFieldsContainer",
                                                                          "preItemUpdate");
         }
      }
   }
}


// Get the name and the version of the plugin - Needed
function plugin_version_fields() {
   return array ('name'           => __("Additionnal fields", "fields"),
                 'version'        => PLUGIN_FIELDS_VERSION,
                 'author'         => 'Teclib\', Olivier Moron',
                 'homepage'       => 'teclib.com',
                 'license'        => 'GPLv2+',
                 'minGlpiVersion' => '0.85');
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_fields_check_prerequisites() {
   if (version_compare(GLPI_VERSION,'0.85','lt')) {
      echo "This plugin requires GLPI 0.85";
      return false;
   }

   if (version_compare(PHP_VERSION, '5.3.0', 'lt')) {
      echo "PHP 5.3.0 or higher is required";
      return false;
   }

   // Check class and front files for existing containers and dropdown fields
   plugin_fields_checkFiles();

   return true;
}

function plugin_fields_checkFiles() {
   $plugin = new Plugin();

   if (isset($_SESSION['glpiactiveentities'])
      && $plugin->isInstalled('fields')
      && $plugin->isActivated('fields')) {

      Plugin::registerClass('PluginFieldsContainer');
      Plugin::registerClass('PluginFieldsDropdown');
      Plugin::registerClass('PluginFieldsField');

      if (TableExists("glpi_plugin_fields_containers")) {
         $container_obj = new PluginFieldsContainer();
         $containers    = $container_obj->find();

         foreach ($containers as $container) {
            $itemtypes = (count($container['itemtypes']) > 0) ? json_decode($container['itemtypes'], TRUE) : array();
            foreach ($itemtypes as $itemtype) {
               $classname = "PluginFields".ucfirst($itemtype.
                                        preg_replace('/s$/', '', $container['name']));
               if(!class_exists($classname)) {
                  PluginFieldsContainer::generateTemplate($container);
               }
            }
         }
      }

      if (TableExists("glpi_plugin_fields_fields")) {
         $fields_obj = new PluginFieldsField();
         $fields     = $fields_obj->find("`type` = 'dropdown'");
         foreach ($fields as $field) {
            PluginFieldsDropdown::create($field);
         }
      }
   }
}

// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_fields_check_config($verbose = false) {
   if (true) { // Your configuration check
      return true;
   }
   if ($verbose) {
      echo __("Installed / not configured");
   }
   return false;
}
