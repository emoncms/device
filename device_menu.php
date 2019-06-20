<?php

$menu['setup'][] = array(
    'text' => _("Device Setup"),
    'path' => 'device/view',
    'icon' => 'device'
);

$menu['sidebar']['emoncms'][] = array(
    'text' => _("Things"),
    'path' => 'device/thing/view',
    'icon' => 'device',
    'order' => 0
);
