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
            <h4 style="padding-left:10px;">
                <span><?php echo _('Devices'); ?></span>
                <span id="device-sidebar-close" class="btn-sidebar-close"><i class="icon-remove"></i></span>
            </h4>
            <div id="select-device-alert" class="hidden" style="overflow: hidden">
                <div class="alert">
                    <?php echo _('Please select the correct device template to setup your device:'); ?>
                </div>
            </div>
            <div style="overflow-x: hidden; width:100%">
                <div id="template-list" class="accordion"></div>
            </div>
        </div>
        
        <div id="device-content" class="modal-content">
            <h3>
                <span id="device-sidebar-open" class="btn btn-sidebar-open"><i class="icon-th-list"></i></span>
                <span><?php echo _('Configuration'); ?></span>
            </h3>
            
            <span id="template-info" style="display:none;">
                <span id="template-description"></span>
                <span id="template-tooltip" data-toggle="tooltip" data-placement="bottom" data-container="#device-config-modal">
                    <span class="icon-info-sign" style="cursor:pointer; padding-left:6px;"></span>
                </span>
            </span>
            
            <div class="divider"></div>
            
            <label><b><?php echo _('Key'); ?></b></label>
            <input id="device-config-node" class="input-medium" type="text" pattern="[a-zA-Z0-9-_. ]+" required>
            <span id="device-config-name-icon" class="input-icon" data-show=false data-toggle="tooltip" data-placement="bottom" data-container="#device-config-modal">
                <span class="icon-plus-sign" style="cursor:pointer;"></span>
            </span>
            <div id="device-config-name-container" class="hide">
                <label><b><?php echo _('Name'); ?></b></label>
                <input id="device-config-name" class="input-large" type="text">
            </div>
            
            <label><b><?php echo _('Description'); ?></b></label>
            <input id="device-config-description" class="input-large" type="text">
            
            <label><b><?php echo _('Device Key'); ?></b></label>
            <div class="input-append">
                <input id="device-config-devicekey" class="input-large device-key" type="text" style="width:245px;">
                <button id="device-config-devicekey-new" class="btn"><?php echo _('New'); ?></button>
            </div>
            
            <div id="device-config-options" class="modal-options hide">
                <div id="device-config-options-header" class="option-header" data-toggle="collapse" data-target="#device-config-options-body">
                    <h5><span class="icon-chevron-right icon-collapse"></span><?php echo _('Options'); ?></h5>
                </div>
                <div id="device-config-options-body" class="collapse">
                    <table id="device-config-options-table" class="table table-options"></table>
                    <div id="device-config-options-none" class="alert" style="display:none"><?php echo _('You have no options configured'); ?></div>
                    
                    <div id="device-config-options-footer" style="margin-bottom: 8px" >
                        <h5><?php echo _('Add option:'); ?></h5>
                        <span>
                            <select id="device-config-options-select" style="margin: 0px" disabled></select>
                            <button id="device-config-options-add" class="btn btn-info" style="border-radius: 4px" disabled><?php echo _('Add'); ?></button>
                        </span>
                    </div>
                </div>
                <div id="device-config-options-overlay" class="modal-overlay"></div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn hidden-xs" style="display:none; float:left"><?php echo _('Back'); ?></button>
        <button class="btn hidden-xs" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button class="btn visible-xs pull-left" data-dismiss="modal" aria-hidden="true" title="<?php echo _('Cancel'); ?>" type="button" style="margin-left:0;font-weight:bold">×</button>
        <button id="device-delete" class="btn btn-danger" style="cursor:pointer"><i class="icon-trash icon-white hidden-xs"></i> <?php echo _('Delete'); ?></button>
        <button id="device-scan" class="btn btn-info" style="display:none"><i class="icon-search icon-white"></i> <?php echo _('Scan'); ?></button>
        <button id="device-init" class="btn btn-primary"><i class="icon-refresh icon-white hidden-xs"></i> <?php echo _('Initialize'); ?></button>
        <button id="device-save" class="btn btn-primary"><?php echo _('Save'); ?></button>
    </div>
</div>

<div id="device-init-modal" class="modal hide modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-init-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-init-modal-label"><?php echo _('Initialize device'); ?></h3>
    </div>
    <div id="device-init-body" class="modal-body">
        <div class="modal-content">
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

<div id="device-scan-modal" class="modal hide keyboard" tabindex="-1" role="dialog" aria-labelledby="device-scan-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-scan-label"><?php echo _('Scan Devices'); ?></h3>
    </div>
    <div id="device-scan-progress" class="scan-progress progress progress-default progress-striped active">
        <div id="device-scan-progress-bar" class="bar" style="width:100%;"></div>
    </div>
    <div id="device-scan-body" class="modal-body" style="margin-top: 20px">
            <p id="device-scan-description"></p>
            
            <div class="divider"></div>
            
            <ul id="device-scan-results" class="scan-result" style="display:none"></ul>
            <div id="device-scan-results-none" class="alert" style="display:none"><?php echo _('No devices found'); ?></div>
            
            <div id="device-scan-container"></div>
    </div>
    <div class="modal-footer">
        <button id="device-scan-cancel" class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
    </div>
    <div id="device-scan-loader" class="ajax-loader"></div>
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
        device_dialog.adjustConfigModal();
        device_dialog.adjustInitModal();
        device_dialog.adjustScanModal();
    });
</script>
