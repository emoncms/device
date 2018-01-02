<?php global $path, $session, $user; ?>
<style>
  a.anchor{display: block; position: relative; top: -50px; visibility: hidden;}
</style>

<h2><?php echo _('Device Thing API'); ?></h2>
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
