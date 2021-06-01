<?php

$schema['device'] = array(
    'id' => array('type' => 'int(11)', 'Null'=>false, 'Key'=>'PRI', 'Extra'=>'auto_increment'),
    'userid' => array('type' => 'int(11)'),
    'nodeid' => array('type' => 'text'),
    'name' => array('type' => 'text'),
    'description' => array('type' => 'text'),
    'type' => array('type' => 'varchar(32)'),
    'devicekey' => array('type' => 'varchar(64)'),
    'time' => array('type' => 'int(10)')
);

$schema['device_config'] = array(
    'deviceid' => array('type' => 'int(11)', 'Null'=>'NO', 'Key'=>'PRI'),
    'optionid' => array('type' => 'varchar(32)', 'Null'=>'NO', 'Key'=>'PRI'),
    'value' => array('type' => 'text')
);
