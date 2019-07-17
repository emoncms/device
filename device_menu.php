<?php

    $menu['setup'][] = array(
        'text' => _("Devices"),
        'path' => 'device/view',
        'icon' => 'device'
    );

    $menu['sidebar']['emoncms'][] = array(
        'text' => _("Things"),
        'path' => 'device/thing/view',
        'icon' => 'thing',
        'order' => 10
    );
