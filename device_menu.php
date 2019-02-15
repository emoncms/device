<?php

$menu['setup'][] = array(
    'text' => _("Device Setup"),
    'path' => 'device/view',
    'icon' => 'device',
    'sort' => 4
);

/*
    // no reason to have list of devices in menu. requires list view to edit/add/delete
    //
    $domain = "messages";
    bindtextdomain($domain, "Modules/device/locale");
    bind_textdomain_codeset($domain, 'UTF-8');


    global $mysqli, $redis, $route, $session;

    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli, $redis);

    $menu_dropdown_config[] = array(
            'name'=> dgettext($domain, "Device Setup"),
            'icon'=>'icon-home',
            'path'=>"device/view" ,
            'session'=>"write",
            'order' => 45
    );

    foreach($device->get_list($session['userid']) as $key=>$item) {
        $sidebar['device'][] = array(
            'text' => sprintf('%s: %s',$item['nodeid'],$item['name']),
            'path' => 'device/view',
            'sort' => $key
        );
    }
*/