<?php 
defined('EMONCMS_EXEC') or die('Restricted access');
global $path, $session, $user; 
?>
<style>
    a.anchor {
        display: block;
        position: relative;
        top: -50px;
        visibility: hidden;
    }
    .table td:nth-of-type(1) { width:25%; }
</style>

<h2><?php echo tr('Device API'); ?></h2>
<h3><?php echo tr('Apikey authentication'); ?></h3>
<p><?php echo tr('If you want to call any of the following actions when your not logged in you have this options to authenticate with the API key:'); ?></p>
<ul><li><?php echo tr('Append on the URL of your request: &apikey=APIKEY'); ?></li>
<li><?php echo tr('Use POST parameter: "apikey=APIKEY"'); ?></li>
<li><?php echo tr('Add the HTTP header: "Authorization: Bearer APIKEY"'); ?></li></ul>
<p><b><?php echo tr('Read only:'); ?></b><br>
<input type="text" style="width:255px" readonly="readonly" value="<?php echo $user->get_apikey_read($session['userid']); ?>" />
</p>
<p><b><?php echo tr('Read & Write:'); ?></b><br>
<input type="text" style="width:255px" readonly="readonly" value="<?php echo $user->get_apikey_write($session['userid']); ?>" />
</p>

<h3><?php echo tr('Devicekey authentication'); ?></h3>
<p><?php echo tr('Using a device key will only allow sending data for the Node of that device, giving a greater level of security.'); ?></p>
<p><?php echo tr('The input module can use a devicekey instead of an apikey. If you want to authenticate as a device, just replace apikey=APIKEY with devicekey=DEVICEKEY:'); ?></p>
<ul><li><?php echo tr('Append on the input URL of your request: &devicekey=DEVICEKEY'); ?></li>
<li><?php echo tr('Use POST parameter while calling input: "devicekey=DEVICEKEY"'); ?></li></ul>
<p><?php echo tr('Ensure that the sent input Node matches the Node that is configured for the device on the device menu.'); ?></p>

<h3><?php echo tr('Available HTML URLs'); ?></h3>
<table class="table">
    <tr><td><?php echo tr('The device list view'); ?></td><td><a href="<?php echo $path; ?>device/view"><?php echo $path; ?>device/view</a></td></tr>
    <tr><td><?php echo tr('This page'); ?></td><td><a href="<?php echo $path; ?>device/api"><?php echo $path; ?>device/api</a></td></tr>
</table>

<h3><?php echo tr('Available JSON commands'); ?></h3>
<p><?php echo tr('To use the json api the request url needs to include <b>.json</b>'); ?></p>

<p><b><?php echo tr('Device actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo tr('List devices'); ?></td><td><a href="<?php echo $path; ?>device/list.json"><?php echo $path; ?>device/list.json</a></td></tr>
    <tr><td><?php echo tr('Get device details'); ?></td><td><a href="<?php echo $path; ?>device/get.json?id=1"><?php echo $path; ?>device/get.json?id=1</a></td></tr>
    <tr><td><?php echo tr('Add a device'); ?></td><td><a href="<?php echo $path; ?>device/create.json?nodeid=Room&name=Test&description=House&type=test&dkey=DEVICEKEY"><?php echo $path; ?>device/set.json?nodeid=Room&name=Test&description=House&type=test&dkey=DEVICEKEY</a></td></tr>
    <tr><td><?php echo tr('Delete device'); ?></td><td><a href="<?php echo $path; ?>device/delete.json?id=1"><?php echo $path; ?>device/delete.json?id=1</a></td></tr>
    <tr><td><?php echo tr('Update device'); ?></td><td><a href="<?php echo $path; ?>device/set.json?id=1&nodeid=Room&name=Test&description=House&type=test&dkey=DEVICEKEY"><?php echo $path; ?>device/set.json?id=1&nodeid=Room&name=Test&description=House&type=test&dkey=DEVICEKEY</a></td></tr>
    <tr><td><?php echo tr('Generate a random device key'); ?></td><td><a href="<?php echo $path; ?>device/generatekey.json"><?php echo $path; ?>device/generatekey.json</a></td></tr>
    <tr><td><?php echo tr('Set a new random device key'); ?></td><td><a href="<?php echo $path; ?>device/setNewDeviceKey.json?id=1"><?php echo $path; ?>device/setNewDeviceKey.json?id=1</a></td></tr>
    <tr><td><?php echo tr('Initialize device'); ?></td><td><a href="<?php echo $path; ?>device/init.json?id=1"><?php echo $path; ?>device/init.json?id=1</a></td></tr>
</table>

<p><b><?php echo tr('Device MQTT authentication'); ?></b></p>
<table class="table">
    <tr><td><?php echo tr('Request authentication'); ?></td><td><a href="<?php echo $path; ?>device/auth/request.json"><?php echo $path; ?>device/auth/request.json</a></td></tr>
    <tr><td><?php echo tr('Check authentication request'); ?></td><td><a href="<?php echo $path; ?>device/auth/check.json"><?php echo $path; ?>device/auth/check.json</a></td></tr>
    <tr><td><?php echo tr('Allow authentication request'); ?></td><td><a href="<?php echo $path; ?>device/auth/allow.json?ip=127.0.0.1"><?php echo $path; ?>device/auth/allow.json?ip=127.0.0.1</a></td></tr>
</table>

<p><b><?php echo tr('Template actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo tr('List template metadata'); ?></td><td><a href="<?php echo $path; ?>device/template/listshort.json"><?php echo $path; ?>device/template/listshort.json</a></td></tr>
    <tr><td><?php echo tr('List templates'); ?></td><td><a href="<?php echo $path; ?>device/template/list.json"><?php echo $path; ?>device/template/list.json</a></td></tr>
    <tr><td><?php echo tr('Reload templates'); ?></td><td><a href="<?php echo $path; ?>device/template/reload.json"><?php echo $path; ?>device/template/reload.json</a></td></tr>
    <tr><td><?php echo tr('Get template details'); ?></td><td><a href="<?php echo $path; ?>device/template/get.json?type=example"><?php echo $path; ?>device/template/get.json?type=example</a></td></tr>
    <tr><td><?php echo tr('Prepare device initialization'); ?></td><td><a href="<?php echo $path; ?>device/template/prepare.json?id=1"><?php echo $path; ?>device/template/prepare.json?id=1</a></td></tr>
</table>

<a class="anchor" id="expression"></a> 
<h3><?php echo tr('Devices templates documentation'); ?></h3>
<p><?php echo tr('Template files are located at <b>\'\\Modules\\device\\data\\*.json\'</b>'); ?></p>
<p><?php echo tr('Each file defines a device type and provides the default inputs and feeds configurations for that device.'); ?></p>
<p><?php echo tr('A device should only need to be initialized once on instalation. Initiating a device twice will duplicate its default inputs and feeds.'); ?></p>
