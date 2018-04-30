<?php

    $domain = "messages";
    bindtextdomain($domain, "Modules/device/locale");
    bind_textdomain_codeset($domain, 'UTF-8');

    $menu_dropdown_config[] = array(
            'name'=> dgettext($domain, "Devices"),
            'icon'=>'icon-home',
            'path'=>"device/view" ,
            'session'=>"write",
            'order' => 45
    );
    
    $menu_dropdown[] = array(
            'name'=> dgettext($domain, "Things"),
            'icon'=>'icon-tasks',
            'path'=>"device/thing/view" ,
            'session'=>"write",
            'order' => 10
    );
