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

<h2><?php echo _('Device Thing API'); ?></h2>
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

<h3><?php echo _('Available HTML URLs'); ?></h3>
<table class="table">
    <tr><td><?php echo _('The device list view'); ?></td><td><a href="<?php echo $path; ?>device/thing/view"><?php echo $path; ?>device/thing/view</a></td></tr>
    <tr><td><?php echo _('This page'); ?></td><td><a href="<?php echo $path; ?>device/thing/api"><?php echo $path; ?>device/thing/api</a></td></tr>
</table>

<h3><?php echo _('Available JSON commands'); ?></h3>
<p><?php echo _('To use the json api the request url needs to include <b>.json</b>'); ?></p>

<table class="table">
    <tr><td><?php echo _('List things'); ?></td><td><a href="<?php echo $path; ?>device/thing/list.json"><?php echo $path; ?>device/thing/list.json</a></td></tr>
    <tr><td><?php echo _('Get thing'); ?></td><td><a href="<?php echo $path; ?>device/thing/get.json?id=1"><?php echo $path; ?>device/thing/get.json?id=1</a></td></tr>
    <tr><td><?php echo _('Get item'); ?></td><td><a href="<?php echo $path; ?>device/item/get.json?id=1&itemid=1"><?php echo $path; ?>device/item/get.json?id=1&itemid=1</a></td></tr>
    <tr><td><?php echo _('Set item on'); ?></td><td><a href="<?php echo $path; ?>device/item/on.json?id=1&itemid=1"><?php echo $path; ?>device/item/on.json?id=1&itemid=1</a></td></tr>
    <tr><td><?php echo _('Set item off'); ?></td><td><a href="<?php echo $path; ?>device/item/off.json?id=1&itemid=1"><?php echo $path; ?>device/item/off.json?id=1&itemid=1</a></td></tr>
    <tr><td><?php echo _('Toggle item value'); ?></td><td><a href="<?php echo $path; ?>device/item/toggle.json?id=1&itemid=1"><?php echo $path; ?>device/item/toggle.json?id=1&itemid=1</a></td></tr>
    <tr><td><?php echo _('Increase item value'); ?></td><td><a href="<?php echo $path; ?>device/item/increase.json?id=1&itemid=1"><?php echo $path; ?>device/item/increase.json?id=1&itemid=1</a></td></tr>
    <tr><td><?php echo _('Decrease item value'); ?></td><td><a href="<?php echo $path; ?>device/item/decrease.json?id=1&itemid=1"><?php echo $path; ?>device/item/decrease.json?id=1&itemid=1</a></td></tr>
    <tr><td><?php echo _('Set percent of item value'); ?></td><td><a href="<?php echo $path; ?>device/item/percent.json?id=1&itemid=1&value=0"><?php echo $path; ?>device/item/percent.json?id=1&itemid=1&value=0</a></td></tr>
    <tr><td><?php echo _('Set item value'); ?></td><td><a href="<?php echo $path; ?>device/item/set.json?id=1&itemid=1&value=0"><?php echo $path; ?>device/item/set.json?id=1&itemid=1&value=0</a></td></tr>
</table>
