<?php

/*
 * rah_privileges - Configure admin-side privileges
 * https://github.com/gocom/rah_privileges
 *
 * Copyright (C) 2015 Jukka Svahn
 *
 * This file is part of rah_privileges.
 *
 * rah_privileges is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, version 2.
 *
 * rah_privileges is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with hpw_admincss. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * The plugin class.
 *
 * @internal
 */

class Rah_Privileges
{
    /**
     * Constructor.
     */

    public function __construct()
    {
        add_privs('prefs.rah_privs', '1');
        register_callback(array($this, 'install'), 'plugin_lifecycle.rah_privileges', 'installed');
        register_callback(array($this, 'uninstall'), 'plugin_lifecycle.rah_privileges', 'deleted');
        register_callback(array($this, 'savePrefs'), 'prefs', '', 1);
        register_callback(array($this, 'inject_css'), 'admin_side', 'head_end');
        $this->mergePrivileges();
    }

    /**
     * Installer.
     */

    public function install()
    {
        if (get_pref('rah_privileges_privs', false) === false) {
            if (defined('PREF_PLUGIN')) {
                set_pref('rah_privileges_privs', 0, 'rah_privs', PREF_PLUGIN, 'rah_privileges_input', 80);
            } else {
                set_pref('rah_privileges_privs', 0, 'rah_privs', PREF_ADVANCED, 'rah_privileges_input', 80);
            }
        }
    }

    /**
     * Uninstaller.
     */

    public function uninstall()
    {
        safe_delete('txp_prefs', "name like 'rah\_privileges\_%'");
    }

    /**
     * Merges privileges table with our overwrites.
     */

    public function mergePrivileges()
    {
        global $txp_permissions, $event;

        if (!get_pref('rah_privileges_privs')) {
            return;
        }

        $privs = json_decode(get_pref('rah_privileges_privs'), true);

        if (!is_array($privs)) {
            return;
        }

        if ($event === 'prefs') {
            unset(
                $privs['prefs'],
                $privs['prefs.rah_privs']
            );
        }

        foreach ($privs as $resource => $groups) {
            if (!$groups) {
                $txp_permissions[$resource] = null;
            } else {
                $txp_permissions[$resource] = implode(',', (array) $groups);
            }
        }
    }

    /**
     * Saves privileges configuration.
     */

    public function savePrefs()
    {
        global $prefs;

        if (!isset($_POST['rah_privileges_resource_0'])) {
            return;
        }

        $data = array();

        foreach ($_POST as $name => $value) {
            if (strpos($name, 'rah_privileges_resource_') === 0) {
                $index = substr($name, strlen('rah_privileges_resource_'));
                $groups = ps('rah_privileges_groups_'.$index);
                $resource = (string) ps($name);
                $data[$resource] = $groups;
                unset($_POST['rah_privileges_groups_'.$index], $_POST[$name]);
            }
        }

        $prefs['rah_privileges_privs'] = $_POST['rah_privileges_privs'] = json_encode($data);
        $this->mergePrivileges();
    }
  
    /**
     * Inject style rules into the head of the page.
     *
     * @return string      Style rules, or nothing if not the correct $event
     */

    public function inject_css()
    {
        global $event;

        if ($event === 'prefs') {
            $rah_plugin_styles = $this->get_style_rules();

            echo '<style>' . $rah_plugin_styles['rah_privileges'] . '</style>';
        }

        return;
    }

    /**
     * CSS definitions: hopefully kind to themers.
     *
     * @return string      Style rules
     */

    protected function get_style_rules()
    {
        $rah_plugin_styles = array(
            'rah_privileges' => '
#prefs-rah_privileges_privs { 
  display: block;
}
.rah_privileges-checkbox-item {
  display: inline-block;
  white-space: nowrap;
}
.rah_privileges-checkbox-item .checkbox + label {
    padding: 0 2em 0 0.5em;
}
            ',
        );

        return $rah_plugin_styles;
    }
}

/**
 * Renders input for setting privilege settings.
 *
 * @return string HTML widget
 */

function rah_privileges_input()
{
    global $txp_permissions, $plugin_areas;

    $permissions = $txp_permissions;
    $mergedpermissions = $out = $panels = array();
    $index = 0;
    $levels = get_groups();

    unset($levels[0]);

    $labels = array_keys($permissions);
    $panels = array_combine(array_values($labels), array_fill(0, count($labels), 1));
    $labels = array_combine($labels, $labels);

    if (get_pref('rah_privileges_privs')) {
        $mergedpermissions = json_decode(get_pref('rah_privileges_privs'), true);
    }

    foreach (areas() as $area => $events) {
        foreach ($events as $title => $event) {
            if (array_key_exists($event, $labels)) {
                $labels[$event] = $title;
                $panels[$event] = 0;
            }
        }
    }

    array_multisort($panels, SORT_ASC, SORT_NUMERIC, $labels, SORT_ASC, SORT_STRING, $permissions);

    foreach ($permissions as $resource => $groups) {
        $out[] = hInput('rah_privileges_resource_'.$index, $resource);

        if ($index !== 0) {
            $out[] = br.br;
        }

        $out[] = tag($labels[$resource], 'strong').br;

        if (isset($mergedpermissions[$resource])) {
            $groups = $mergedpermissions[$resource];
        } elseif ($groups !== null) {
            $groups = explode(',', (string) $groups);
        }

        foreach ($levels as $group => $label) {
            $checked = is_array($groups) && in_array($group, $groups);
            $name = 'rah_privileges_groups_'.$index.'[]';
            $id = 'rah_privileges_groups_'.$index.'_'.intval($group);

            $out[] = '<div class="rah_privileges-checkbox-item">';
            $out[] = checkbox(
                $name,
                $group,
                $checked,
                '',
                $id
            );

            $out[] = '<label for="'.$id.'">'.$label.'</label>';
            $out[] = '</div>';
        }

        $index++;
    }

    return implode('', $out);
}

new Rah_Privileges();
