<?php
    global $path;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device_dialog.js?v=3"></script>
<link href="<?php echo $path; ?>Modules/app/css/titatoggle-dist-min.css?v=<?php echo $v; ?>" rel="stylesheet">

<style>
    input[type="checkbox"] { margin:0px; }

    .checkbox-slider--b {
        width: 20px;
        border-radius: 25px;
        background-color: gainsboro;
        height: 20px;
    }
    *::before, *::after {
        box-sizing: border-box;
    }

    .group-body tr:hover > td {
        background-color: #44b3e2;
    }

    .device-selected {
        background-color: #209ed3;
        color: #fff;
    }

    .modal-adjust {
        width: 60%; left:20%; /* (100%-width)/2 */
        margin-left: auto; margin-right: auto;
        overflow-y: hidden;
    }

    .modal-adjust .modal-body {
        max-height: none;
        overflow-y: auto;
    }

    #sidebar-wrapper {
        position: absolute;
        margin: -15px;
        width: 250px;
        height: 100%;
        background: #eee;
        overflow-y: auto;
        z-index: 1000;
    }

    #content-wrapper {
        margin-top: -15px;
        margin-left: 250px;
    }

    #content-wrapper .divider {
        *width: 100%;
        height: 1px;
        margin: 9px 1px;
        *margin: -5px 0 5px;
        overflow: hidden;
        background-color: #e5e5e5;
        border-bottom: 1px solid #ffffff;
    }

    #template-info .tooltip-inner {
        max-width: 500px;
    }

    #device-init-modal {
        width: 60%; left:20%; /* (100%-width)/2 */
        margin-left: auto; margin-right: auto;
    }

    #device-init-modal table td { text-align: left; }

    #device-init-feeds table td:nth-of-type(1) { width:14px; text-align: center; }
    #device-init-feeds table td:nth-of-type(2) { width:5%; }
    #device-init-feeds table td:nth-of-type(3) { width:15%; }
    #device-init-feeds table td:nth-of-type(4) { width:25%; }

    #device-init-inputs table td:nth-of-type(1) { width:14px; text-align: center; }
    #device-init-inputs table td:nth-of-type(2) { width:5%; }
    #device-init-inputs table td:nth-of-type(3) { width:5%; }
    #device-init-inputs table td:nth-of-type(4) { width:10%; }
    #device-init-inputs table td:nth-of-type(5) { width:25%; }
</style>

<div id="device-config-modal" class="modal hide keyboard modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-config-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-config-modal-label"><?php echo _('Configure Device'); ?></h3>
    </div>
    <div id="device-config-body" class="modal-body">
        <div id="sidebar-wrapper">
            <div style="padding-left:10px;">
                <div id="sidebar-close" style="float:right; cursor:pointer; padding:10px;"><i class="icon-remove"></i></div>
                <h3><?php echo _('Devices'); ?></h3>
            </div>
            <div style="overflow-x: hidden; background-color:#f3f3f3; width:100%">
                <table id="template-table" class="table"></table>
            </div>
        </div>
        
        <div id="content-wrapper" style="max-width:1280px">
            
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
            
            <label><b><?php echo _('Node'); ?></b></label>
            <input id="device-config-node" class="input-medium" type="text">
            
            <label><b><?php echo _('Name'); ?></b></label>
            <input id="device-config-name" class="input-large" type="text">
            
            <label><b><?php echo _('Location'); ?></b></label>
            <input id="device-config-description" class="input-large" type="text">
            
            <label><b><?php echo _('Device Key'); ?></b></label>
            <div class="input-append">
                <input id="device-config-devicekey" class="input-large" type="text" style="width:260px">
                <button id="device-config-devicekey-new" class="btn">New</button>
            </div>
            
            <div id="template-options" style="display:none">
                <div id="template-options-divider" class="divider"></div>
                
                <div id="template-options-ctrl" style="display:none">
                    <label><b><?php echo _('Controller'); ?></b>
                        <span id="template-options-ctrl-tooltip" data-toggle="tooltip" data-placement="right">
                            <i class="icon-info-sign" style="cursor:pointer; padding-left:6px;"></i>
                        </span>
                    </label>
                    <select id="template-options-ctrl-select" class="input-large"></select>
                </div>
                
                <div id="template-options-table" style="display:none">
                	<table class="table table-hover table-config">
                		<tbody>
                			<tr>
            					<th id="options-header" colspan="5">
                					<i class="toggle-header icon-minus-sign" style="cursor:pointer"></i>
                					<a class="toggle-header" style="cursor:pointer">Options</a>
                				</th>
                			</tr>
                			<tr id="option-header" style="display: table-row;" colspan="5">
                				<th>Option</th>
                				<th>Value</th>
                				<th colspan="3"></th>
                			</tr>
                		</tbody>
                		<tbody id="options-table" style="display: table-row-group;">
                		</tbody>
                		<tbody>
                			<tr colspan="5"><th>Add option:</th></tr>
                			<tr>
                				<td><select id="template-add-options" class="input-large"></select></td>
                				<td><button id="add-option-button" class="btn btn-info" style="border-radius: 4px;">Add</button></td>
            				</tr>
                		</tbody>
                	</table>
                </div>
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

<div id="device-init-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="device-init-modal-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-init-modal-label"><?php echo _('Initialize device'); ?></h3>
    </div>
    <div class="modal-body">
        <p><?php echo _('Initializing a device will automaticaly configure inputs and associated feeds as described.'); ?><br>
            <b><?php echo _('Warning: '); ?></b><?php echo _('Process lists with dependencies to deselected feeds or inputs will be skipped as a whole'); ?>
        </p>
        
        <div id="device-init-feeds" style="display:none">
            <label><b><?php echo _('Feeds'); ?></b></label>
            <table class="table table-hover">
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
            <table class="table table-hover">
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
    });
</script>
