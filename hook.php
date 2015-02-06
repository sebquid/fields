<?php

function plugin_fields_install() {
   global $CFG_GLPI;

   set_time_limit(900);
   ini_set('memory_limit','2048M');

   $plugin_fields = new Plugin;
   $plugin_fields->getFromDBbyDir('fields');
   $version = $plugin_fields->fields['version'];

   $classesToInstall = array(
      'PluginFieldsDropdown',
      'PluginFieldsField',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsValue',
      'PluginFieldsProfile', 
      'PluginFieldsMigration'   
   );

   $migration = new Migration($version);
   echo "<center>";
   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>".__("MySQL tables installation", "fields")."<th></tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td align='center'>";
   foreach ($classesToInstall as $class) {
      if ($plug=isPluginItemType($class)) {
         $dir= GLPI_ROOT . "/plugins/fields/inc/";
         $item=strtolower($plug['class']);
         if (file_exists("$dir$item.class.php")) {
            include_once ("$dir$item.class.php");
            if (!call_user_func(array($class,'install'), $migration, $version)) return false;
         }
      }
   }

   echo "</td>";
   echo "</tr>";
   echo "</table></center>";

   return true;
}


function plugin_fields_uninstall() {
   $_SESSION['uninstall_fields'] = true;

   $classesToUninstall = array(
      'PluginFieldsDropdown',
      'PluginFieldsContainer',
      'PluginFieldsContainer_Field',
      'PluginFieldsField',
      'PluginFieldsValue',
      'PluginFieldsProfile', 
      'PluginFieldsMigration' 
   );

   echo "<center>";
   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>".__("MySQL tables uninstallation", "fields")."<th></tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td align='center'>";

   foreach ($classesToUninstall as $class) {
      if ($plug=isPluginItemType($class)) {
         $dir=GLPI_ROOT . "/plugins/fields/inc/";
         $item=strtolower($plug['class']);
         if (file_exists("$dir$item.class.php")) {
            include_once ("$dir$item.class.php");
            if(!call_user_func(array($class,'uninstall'))) return false;
         }
      }
   }

   echo "</td>";
   echo "</tr>";
   echo "</table></center>";

   unset($_SESSION['uninstall_fields']);

   return true;
}

function regenerateFiles() {
	$container = new PluginFieldsContainer;
	$found_container = $container->find();
	foreach ($found_container as $current_container) {
		$containers_id = $current_container['id'];
		$container->getFromDB($containers_id);
		$container->post_addItem();
	}
}


function plugin_fields_getAddSearchOptions($itemtype) {
   if (isset($_SESSION['glpiactiveentities'])) {

      $itemtypes = PluginFieldsContainer::getEntries('all');

      if ($itemtypes !== false && in_array($itemtype, $itemtypes)) {
         return PluginFieldsContainer::getAddSearchOptions($itemtype);
      }
   }

   return null;  
}

// Define Dropdown tables to be manage in GLPI :
function plugin_fields_getDropdown() {
   $dropdowns = array();

   $field_obj = new PluginFieldsField;
   $fields = $field_obj->find("`type` = 'dropdown'");
   foreach ($fields as $field) {
      $dropdowns["PluginFields".ucfirst($field['name'])."Dropdown"] = $field['label'];
   }

   return $dropdowns;
}


/**** MASSIVE ACTIONS ****/


// Display specific massive actions for plugin fields
function plugin_fields_MassiveActionsFieldsDisplay($options=array()) {
   $itemtypes = PluginFieldsContainer::getEntries('all');

   if (in_array($options['itemtype'], $itemtypes)) {
      PluginFieldsField::showSingle($options['itemtype'], $options['options'], true);
      return true;
   }

   // Need to return false on non display item
   return false;
}


/**** RULES ENGINE ****/

/**
 *
 * Actions for rules
 * @since 0.84
 * @param $params input data
 * @return an array of actions
 */
function plugin_fields_getRuleActions($params) {
   $actions = array();

   switch ($params['rule_itemtype']) {
      case "PluginFusioninventoryTaskpostactionRule":
         $options = PluginFieldsContainer::getAddSearchOptions("Computer");
         foreach ($options as $num => $option) {
            $actions[$option['linkfield']]['name'] = $option['name'];
            $actions[$option['linkfield']]['type'] = $option['pfields_type'];
            if ($option['pfields_type'] == 'dropdown') {
               $actions[$option['linkfield']]['table'] = $option['table'];
            }
         }

         break;
   }

   return $actions;
}


function plugin_fields_rule_matched($params) {
   global $DB;

   $container = new PluginFieldsContainer;

   switch ($params['sub_type']) {
      case "PluginFusioninventoryTaskpostactionRule":
         $agent = new PluginFusioninventoryAgent;

         if (isset($params['input']['plugin_fusioninventory_agents_id'])) {
            foreach ($params['output'] as $field => $value) {

               // check if current field is in a tab container
               $query = "SELECT c.id
                         FROM glpi_plugin_fields_fields f
                         LEFT JOIN glpi_plugin_fields_containers c
                            ON c.id = f.plugin_fields_containers_id
                         WHERE f.name = '$field'";
               $res = $DB->query($query);
               if ($DB->numrows($res) > 0) {
                  $data = $DB->fetch_assoc($res);

                  //retrieve computer
                  $agents_id = $params['input']['plugin_fusioninventory_agents_id'];
                  $agent->getFromDB($agents_id);

                  // update current field
                  $container->updateFieldsValues(array('plugin_fields_containers_id' => $data['id'],
                                                       $field     => $value,
                                                       'items_id' => $agent->fields['computers_id']));
               }
            }
         }
      break;
   }
}