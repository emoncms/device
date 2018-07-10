<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/device/Lib/titatoggle-dist-min.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/device/Views/device_dialog.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device_dialog.js"></script>

<div id="device-config-modal" class="modal hide keyboard modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-config-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-config-modal-label"><?php echo _('Configure Device'); ?></h3>
    </div>
    <div id="device-config-body" class="modal-body">
        <div id="device-sidebar" class="modal-sidebar">
            <div style="padding-left:10px;">
                <div id="device-sidebar-close" style="float:right; cursor:pointer; padding:10px;"><i class="icon-remove"></i></div>
                <h3><?php echo _('Devices'); ?></h3>
            </div>
            <div style="overflow-x: hidden; width:100%">
                <div id="template-list" class="accordion"></div>
            </div>
        </div>
        
        <div id="device-content" class="modal-content" style="max-width:1280px">
            
            <h3><?php echo _('Configuration'); ?></h3>
            
            <div id="navigation" style="padding-bottom:5px;">
                <button class="btn" id="sidebar-open"><i class="icon-list"></i></button>
            </div>
            
            <span id="template-info" style="display:none;">
                <span id="template-description"></span>
                <span id="template-tooltip" data-toggle="tooltip" data-placement="bottom">
                    <i class="icon-info-sign" style="cursor:pointer; padding-left:6px;"></i>
                </span>
            </span>
            
            <div class="divider"></div>
            
            <table class="device-input">
                <tr>
                    <th><?php echo _('Node'); ?></th>
                    <th><?php echo _('Name'); ?></th>
                    <th><?php echo _('Location'); ?></th>
                </tr>
                <tr>
                    <td><input id="device-config-node" class="input-small" type="text" required></td>
                    <td><input id="device-config-name" class="input-medium" type="text" required></td>
                    <td><input id="device-config-description" class="input-large" type="text"></td>
                </tr>
            </table>
            
            <label><b><?php echo _('Device Key'); ?></b></label>
            <div class="input-append">
                <input id="device-config-devicekey" class="input-large" type="text" style="width:260px">
                <button id="device-config-devicekey-new" class="btn"><?php echo _('New'); ?></button>
            </div>
            
            <div class="modal-options">
                <div id="template-options-header" class="option-header" data-toggle="collapse" data-target="#template-options">
                    <h5><span class="icon-chevron-right icon-collapse"></span><?php echo _('Options'); ?></h5>
                </div>
                <div id="template-options" class="collapse">
                    <table id="template-options-table" class="table table-options"></table>
                    <div id="template-options-none" class="alert" style="display:none"><?php echo _('You have no options configured'); ?></div>
                    
                    <div id="template-options-footer" style="margin-bottom: 8px;">
                        <h5><?php echo _('Add option:'); ?></h5>
                        <span>
                            <select id="template-options-select" class="input-large" disabled></select>
                            <button id="template-options-add" class="btn btn-info" style="border-radius: 4px" disabled><?php echo _('Add'); ?></button>
                        </span>
                    </div>
                </div>
                <div id="template-options-overlay" class="modal-overlay"></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="device-delete" class="btn btn-danger" style="cursor:pointer"><i class="icon-trash icon-white"></i> <?php echo _('Delete'); ?></button>
        <button id="device-init" class="btn btn-primary"><i class="icon-refresh icon-white"></i> <?php echo _('Initialize'); ?></button>
        <button id="device-save" class="btn btn-primary"><?php echo _('Save'); ?></button>
    </div>
</div>

<div id="device-init-modal" class="modal hide modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-init-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-init-modal-label"><?php echo _('Initialize device'); ?></h3>
    </div>
    <div id="device-init-body" class="modal-body">
        <div class="content">
            <p style="margin-top: 10px;"><?php echo _('Initializing a device will automaticaly configure inputs and associated feeds as described.'); ?><br>
                <b><?php echo _('Warning: '); ?></b><?php echo _('Process lists with dependencies to deselected feeds or inputs will be skipped as a whole'); ?>
            </p>
        
            <div id="device-init-feeds" style="display:none; margin-top:10px;">
                <label><b><?php echo _('Feeds'); ?></b></label>
                <table class="table table-hover table-feeds">
                    <tr>
                        <th></th>
                        <th></th>
                        <th><?php echo _('Tag'); ?></th>
                        <th><?php echo _('Name'); ?></th>
                        <th><?php echo _('Process list'); ?></th>
                    </tr>
                    <tbody id="device-init-feeds-table"></tbody>
                </table>
            </div>
            
            <div id="device-init-inputs" style="display:none">
                <label><b><?php echo _('Inputs'); ?></b></label>
                <table class="table table-hover table-inputs">
                    <tr>
                        <th></th>
                        <th></th>
                        <th><?php echo _('Node'); ?></th>
                        <th><?php echo _('Key'); ?></th>
                        <th><?php echo _('Name'); ?></th>
                        <th><?php echo _('Process list'); ?></th>
                    </tr>
                    <tbody id="device-init-inputs-table"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button id="device-init-cancel" class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="device-init-confirm" class="btn btn-primary"><?php echo _('Initialize'); ?></button>
    </div>
</div>

<div id="device-delete-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="device-delete-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-delete-modal-label"><?php echo _('Delete device'); ?></h3>
    </div>
    <div class="modal-body">
        <p><?php echo _('Deleting a device is permanent.'); ?>
           <br><br>
           <?php echo _('If this device is active and is using a device key, it will no longer be able to post data.'); ?>
           <br><br>
           <?php echo _('Inputs and Feeds that this device uses are not deleted and all historic data is kept. To remove them, delete them manualy afterwards.'); ?>
           <br><br>
           <?php echo _('Are you sure you want to delete?'); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="device-delete-confirm" class="btn btn-primary"><?php echo _('Delete permanently'); ?></button>
    </div>
</div>

<script>
    $(window).resize(function() {
        device_dialog.adjustConfigModal()
        device_dialog.adjustInitModal()
    });
</script>
