<?php
global $session, $settings;

if ($session["write"]) {
    if (isset($settings["device"]) && isset($settings["device"]["hide_menu"]) && $settings["device"]["hide_menu"]==true) {
        // Device menu is hidden
    } else {
        // Visible as default
        $menu["setup"]["l2"]['device'] = array(
            "name"=>tr("Devices"),
            "href"=>"device/view", 
            "order"=>6, 
            "icon"=>"device"
        );
    }
}
