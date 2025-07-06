<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/device/Views/device_dialog.css?v=4" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device_dialog.js?v=4"></script>

<div id="device-config-modal" class="modal hide keyboard modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-config-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-config-modal-label"><?php echo tr('Configure Device'); ?></h3>
    </div>
    <div id="device-config-body" class="modal-body">
        <div id="device-sidebar" class="modal-sidebar">
            <h3 style="padding-left:10px;">
                <span><?php echo tr('Devices'); ?></span>            
                <span id="device-sidebar-close"><i class="icon-remove"></i></span>
            </h3>
            <div id="select-device-alert" class="hidden">
                <div class="alert" style="border: 0; line-height: 1.1; margin-bottom: 0; padding-left: .8em; border-radius: 0;">
                <?php echo tr('Please select the correct device template to setup your device:'); ?>
                </div>
            </div>
            <div style="overflow-x: hidden; width:100%">
                <div id="template-list" class="accordion"></div>
            </div>
        </div>
        
        <div id="device-content" class="modal-content">
            <h3>
                <span id="device-sidebar-open" class="btn btn-sidebar"><i class="icon-th-list"></i></span>
                <span><?php echo tr('Configuration'); ?></span>
            </h3>
            
            <span id="template-info" style="display:none;">
                <span id="template-description"></span>
                <span id="template-tooltip" data-toggle="tooltip" data-placement="bottom" data-container="#device-config-modal">
                    <i class="icon-info-sign" style="cursor:pointer; padding-left:6px;"></i>
                </span>
            </span>
            
            <div class="divider"></div>
            
            <label><b><?php echo tr('Node'); ?></b></label>
            <input id="device-config-node" class="input-medium" type="text" required>
            
            <label><b><?php echo tr('Name'); ?></b></label>
            <input id="device-config-name" class="input-large" type="text" required>
            
            <label><b><?php echo tr('Location'); ?></b></label>
            <input id="device-config-description" class="input-large" type="text">
            
            <label><b><?php echo tr('Device Key'); ?></b></label>
            <div class="input-append">
                <input id="device-config-devicekey" class="input-large key" type="text" style="width:245px;">
                <button id="device-config-devicekey-new" class="btn"><?php echo tr('New'); ?></button>
            </div>

            <div class="divider"></div>

            <div class="generate-template-section">
                <p><b><?php echo tr('Generate template from existing inputs, input processing and feeds'); ?></b></p>
                <button class="btn" id="generate-template">Generate template</button>
                <button class="btn btn-primary" id="prepare-custom-template"><?php echo tr('Apply custom template'); ?></button>
                
                <textarea id="custom-template-textarea" class="input-large hidden" placeholder="<?php echo tr('Generated template will be shown here'); ?>"></textarea>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn hidden-xs" data-dismiss="modal" aria-hidden="true"><?php echo tr('Cancel'); ?></button>
        <button class="btn visible-xs pull-left"  title="<?php echo tr('Cancel'); ?>" style="margin-left:0;font-weight:bold" data-dismiss="modal" aria-hidden="true" type="button">×</button>
        <button id="device-delete" class="btn btn-danger" style="cursor:pointer"><i class="icon-trash icon-white hidden-xs"></i> <?php echo tr('Delete'); ?></button>
        <button id="device-init" class="btn btn-primary"><i class="icon-refresh icon-white hidden-xs"></i> <?php echo tr('Initialize'); ?></button>
        <button id="device-save" class="btn btn-primary"><?php echo tr('Save'); ?></button>
    </div>
</div>

<div id="device-init-modal" class="modal hide modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-init-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-init-modal-label"><?php echo tr('Initialize device'); ?></h3>
    </div>
    <div id="device-init-body" class="modal-body">
        <div class="content">
            <p style="margin-top: 10px;"><?php echo tr('Initializing a device will automaticaly configure inputs and associated feeds as described.'); ?><br>
                <b><?php echo tr('Warning: '); ?></b><?php echo tr('Process lists with dependencies to deselected feeds or inputs will be skipped as a whole'); ?>
            </p>
        
            <div id="device-init-feeds" style="display:none; margin-top:10px;">
                <label><b><?php echo tr('Feeds'); ?></b></label>
                <table class="table table-hover table-feeds">
                    <tr>
                        <th></th>
                        <th></th>
                        <th><?php echo tr('Tag'); ?></th>
                        <th><?php echo tr('Name'); ?></th>
                        <th><?php echo tr('Process list'); ?></th>
                    </tr>
                    <tbody id="device-init-feeds-table"></tbody>
                </table>
            </div>
            
            <div id="device-init-inputs" style="display:none">
                <label><b><?php echo tr('Inputs'); ?></b></label>
                <table class="table table-hover table-inputs">
                    <tr>
                        <th></th>
                        <th></th>
                        <th><?php echo tr('Node'); ?></th>
                        <th><?php echo tr('Key'); ?></th>
                        <th><?php echo tr('Name'); ?></th>
                        <th><?php echo tr('Process list'); ?></th>
                    </tr>
                    <tbody id="device-init-inputs-table"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button id="device-init-cancel" class="btn" data-dismiss="modal" aria-hidden="true"><?php echo tr('Cancel'); ?></button>
        <button id="device-init-confirm" class="btn btn-primary"><?php echo tr('Initialize'); ?></button>
    </div>
</div>

<div id="device-delete-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="device-delete-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-delete-modal-label"><?php echo tr('Delete device'); ?></h3>
    </div>
    <div class="modal-body">
        <p><?php echo tr('Deleting a device is permanent.'); ?>
           <br><br>
           <?php echo tr('If this device is active and is using a device key, it will no longer be able to post data.'); ?>
           <br><br>
           <?php echo tr('Inputs and Feeds that this device uses are not deleted and all historic data is kept. To remove them, delete them manualy afterwards.'); ?>
           <br><br>
           <?php echo tr('Are you sure you want to delete?'); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo tr('Cancel'); ?></button>
        <button id="device-delete-confirm" class="btn btn-primary"><?php echo tr('Delete permanently'); ?></button>
    </div>
</div>

<script>
    $(window).resize(function() {
        device_dialog.adjustConfigModal()
        device_dialog.adjustInitModal()
    });
</script>
