<?php
global $session;
if ($session["write"]) {
    $menu["setup"]["l2"]['device'] = array(
        "name"=>_("Devices"),
        "href"=>"device/view", 
        "order"=>1, 
        "icon"=>"device"
    );
}