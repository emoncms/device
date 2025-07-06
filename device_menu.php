<?php
global $session;
if ($session["write"]) {
    $menu["setup"]["l2"]['device'] = array(
        "name"=>tr("Devices"),
        "href"=>"device/view", 
        "order"=>6, 
        "icon"=>"device"
    );
}
