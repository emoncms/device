<?php global $path, $session, $user; ?>
<style>
    a.anchor {
        display: block;
        position: relative;
        top: -50px;
        visibility: hidden;
    }
    .table td:nth-of-type(1) { width:25%; }
</style>

<h2><?php echo _('Device API'); ?></h2>
<h3><?php echo _('Apikey authentication'); ?></h3>
<p><?php echo _('If you want to call any of the following actions when your not logged in you have this options to authenticate with the API key:'); ?></p>
<ul><li><?php echo _('Append on the URL of your request: &apikey=APIKEY'); ?></li>
<li><?php echo _('Use POST parameter: "apikey=APIKEY"'); ?></li>
<li><?php echo _('Add the HTTP header: "Authorization: Bearer APIKEY"'); ?></li></ul>
<p><b><?php echo _('Read only:'); ?></b><br>
<input type="text" style="width:255px" readonly="readonly" value="<?php echo $user->get_apikey_read($session['userid']); ?>" />
</p>
<p><b><?php echo _('Read & Write:'); ?></b><br>
<input type="text" style="width:255px" readonly="readonly" value="<?php echo $user->get_apikey_write($session['userid']); ?>" />
</p>

<h3><?php echo _('Devicekey authentication'); ?></h3>
<p><?php echo _('Using a device key will only allow sending data for the Node of that device, giving a greater level of security.'); ?></p>
<p><?php echo _('The input module can use a devicekey instead of an apikey. If you want to authenticate as a device, just replace apikey=APIKEY with devicekey=DEVICEKEY:'); ?></p>
<ul><li><?php echo _('Append on the input URL of your request: &devicekey=DEVICEKEY'); ?></li>
<li><?php echo _('Use POST parameter while calling input: "devicekey=DEVICEKEY"'); ?></li></ul>
<p><?php echo _('Ensure that the sent input Node matches the Node that is configured for the device on the device menu.'); ?></p>

<h3><?php echo _('Available HTML URLs'); ?></h3>
<table class="table">
    <tr><td><?php echo _('The device list view'); ?></td><td><a href="<?php echo $path; ?>device/view"><?php echo $path; ?>device/view</a></td></tr>
    <tr><td><?php echo _('This page'); ?></td><td><a href="<?php echo $path; ?>device/api"><?php echo $path; ?>device/api</a></td></tr>
</table>

<h3><?php echo _('Available JSON commands'); ?></h3>
<p><?php echo _('To use the json api the request url needs to include <b>.json</b>'); ?></p>

<p><b><?php echo _('Device actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('List devices'); ?></td><td><a href="<?php echo $path; ?>device/list.json"><?php echo $path; ?>device/list.json</a></td></tr>
    <tr><td><?php echo _('Get device details'); ?></td><td><a href="<?php echo $path; ?>device/get.json?id=1"><?php echo $path; ?>device/get.json?id=1</a></td></tr>
    <tr><td><?php echo _('Get device configs'); ?></td><td><a href="<?php echo $path; ?>device/configs.json?id=1"><?php echo $path; ?>device/configs.json?id=1</a></td></tr>
    <tr><td><?php echo _('Add a device'); ?></td><td><a href="<?php echo $path; ?>device/create.json?nodeid=Test&name=Test"><?php echo $path; ?>device/create.json?nodeid=Test&name=Test</a></td></tr>
    <tr><td><?php echo _('Delete device'); ?></td><td><a href="<?php echo $path; ?>device/delete.json?id=1"><?php echo $path; ?>device/delete.json?id=1</a></td></tr>
    <tr><td><?php echo _('Update device'); ?></td><td><a href="<?php echo $path; ?>device/set.json?id=1&fields={%22name%22:%22Test%22,%22description%22:%22Room%22,%22nodeid%22:%22House%22,%22type%22:%22test%22}"><?php echo $path; ?>device/set.json?id=1&fields={"name":"Test","description":"Room","nodeid":"House","type":"test"}</a></td></tr>
    <tr><td><?php echo _('Initialize device'); ?></td><td><a href="<?php echo $path; ?>device/init.json?id=1"><?php echo $path; ?>device/init.json?id=1</a></td></tr>
</table>

<p><b><?php echo _('Device MQTT authentication'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Request authentication'); ?></td><td><a href="<?php echo $path; ?>device/auth/request.json"><?php echo $path; ?>device/auth/request.json</a></td></tr>
    <tr><td><?php echo _('Check authentication request'); ?></td><td><a href="<?php echo $path; ?>device/auth/check.json"><?php echo $path; ?>device/auth/check.json</a></td></tr>
    <tr><td><?php echo _('Allow authentication request'); ?></td><td><a href="<?php echo $path; ?>device/auth/allow.json?ip=127.0.0.1"><?php echo $path; ?>device/auth/allow.json?ip=127.0.0.1</a></td></tr>
</table>

<p><b><?php echo _('Template actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('List template metadata'); ?></td><td><a href="<?php echo $path; ?>device/template/listshort.json"><?php echo $path; ?>device/template/listshort.json</a></td></tr>
    <tr><td><?php echo _('List templates'); ?></td><td><a href="<?php echo $path; ?>device/template/list.json"><?php echo $path; ?>device/template/list.json</a></td></tr>
    <tr><td><?php echo _('Reload templates'); ?></td><td><a href="<?php echo $path; ?>device/template/reload.json"><?php echo $path; ?>device/template/reload.json</a></td></tr>
    <tr><td><?php echo _('get template details'); ?></td><td><a href="<?php echo $path; ?>device/template/get.json?type=example"><?php echo $path; ?>device/template/get.json?type=example</a></td></tr>
    <tr><td><?php echo _('get template options'); ?></td><td><a href="<?php echo $path; ?>device/template/options.json?type=example"><?php echo $path; ?>device/template/options.json?type=example</a></td></tr>
    <tr><td><?php echo _('Prepare device initialization'); ?></td><td><a href="<?php echo $path; ?>device/template/prepare.json?id=1"><?php echo $path; ?>device/template/prepare.json?id=1</a></td></tr>
</table>

<p><b><?php echo _('Scan actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Start device scan'); ?></td><td><a href="<?php echo $path; ?>device/scan/start.json?type=example&options={}"><?php echo $path; ?>device/scan/start.json?type=example&options={}</a></td></tr>
    <tr><td><?php echo _('Get device scan progress'); ?></td><td><a href="<?php echo $path; ?>device/scan/progress.json?type=example"><?php echo $path; ?>device/scan/progress.json?type=example</a></td></tr>
    <tr><td><?php echo _('Cancel device scan'); ?></td><td><a href="<?php echo $path; ?>device/scan/camncel.json?type=example"><?php echo $path; ?>device/scan/camncel.json?type=example</a></td></tr>
</table>

<a class="anchor" id="expression"></a> 
<h3><?php echo _('Devices templates documentation'); ?></h3>
<p><?php echo _('Template files are located at <b>\'\\Modules\\device\\data\\*.json\'</b>'); ?></p>
<p><?php echo _('Each file defines a device type and provides the default inputs and feeds configurations for that device.'); ?></p>
<p><?php echo _('A device should only need to be initialized once on instalation. Initiating a device twice will duplicate its default inputs and feeds.'); ?></p>
