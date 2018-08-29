<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/device/Lib/titatoggle-dist-min.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/device/Views/thing.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device.js"></script>

<div>
    <div id="api-help-header" style="float:right;"><a href="api"><?php echo _('Things Help'); ?></a></div>
    <div id="thing-header"><h2><?php echo _('Things'); ?></h2></div>

    <div id="thing-list"></div>

    <div id="thing-none" class="alert alert-block hide" style="margin-top: 20px">
        <h4 class="alert-heading"><?php echo _('No Device Things configured'); ?></h4>
        <p>
            <?php echo _('Device things are configured in templates and enable the communication with different physical devices.'); ?>
            <br>
            <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('Device Thing API helper'); ?></a>
        </p>
       </div>
    <div id="thing-loader" class="ajax-loader"></div>
</div>

<?php require "Modules/device/Views/device_dialog.php"; ?>

<script>

const INTERVAL = 5000;
var path = "<?php echo $path; ?>";
var templates = <?php echo json_encode($templates); ?>;

var things = {};
var collapsed = {};

var redraw = true;

function update() {
    device.listThings(function(result) {
        if (result.length != 0) {
            $("#thing-none").hide();
            $("#thing-header").show();
            $("#api-help-header").show();
            if (redraw) {
                redraw = Object.keys(things).length == 0 ? true : false;
            }
            things = {};
            for (var i=0; i<result.length; i++) {
                var thing = result[i];
                var items = {};
                if (typeof thing.items !== 'undefined' && thing.items.length > 0) {
                    for (var j=0; j<thing.items.length; j++) {
                        var item = thing.items[j];
                        items[item.id] = item;
                    }
                }
                thing['items'] = items;
                things[thing.id] = thing;
            }
            if (redraw && updater) {
                draw();
            }
            updateItems();
        }
        else {
            $("#thing-none").show();
            $("#thing-header").hide();
            $("#api-help-header").hide();
        }
        $('#thing-loader').hide();
    });
}

update();

var updater;
function updaterStart() {
    clearInterval(updater);
    updater = null;
    if (INTERVAL > 0) updater = setInterval(update, INTERVAL);
}
function updaterStop() {
    clearInterval(updater);
    updater = null;
}
updaterStart();

//---------------------------------------------------------------------------------------------
// Draw things
//---------------------------------------------------------------------------------------------
function draw() {
    var list = "";
    
    for (var id in things) {
        if (things.hasOwnProperty(id)) {
            var thing = things[id];
            var items = drawItems(thing);

            if (typeof collapsed[thing.id] === 'undefined') {
                collapsed[thing.id] = false;
            }
            list += 
                "<div class='thing'>" +
                    "<div id='thing-"+thing.id+"-header' class='thing-header' data-toggle='collapse' data-target='#thing-"+thing.id+"-body'>" +
                        "<table>" +
                            "<tr data-thing='"+thing.id+"'>" +
                                "<td>" +
                                    "<span class='thing-name'>"+thing.name+(thing.description.length>0 ? ":" : "")+"</span>" +
                                    "<span class='thing-description'>"+thing.description+"</span>" +
                                "</td>" +
                                "<td class='thing-config'><span class='icon-wrench icon-white' title='Configure'></span></td>" +
                            "</tr>" +
                        "</table>" +
                    "</div>" +
                    "<div id='thing-"+thing.id+"-body' class='collapse "+(collapsed[thing.id] ? '' : 'in')+"' data-thing='"+thing.id+"'>" +
                        "<div class='items'><table>"+items+"</table></div>" +
                    "</div>" +
                "</div>";
        }
    }
    $("#thing-list").html(list);
}

function drawItems(thing) {
    var items = "";
    for (var id in thing.items) {
        if (thing.items.hasOwnProperty(id)) {
            var item = thing.items[id];
            var type = item.type.toLowerCase();
            var value = parseItemValue(item, type, item.value);
            
            var left = "";
            if (typeof item.left !== 'undefined') {
                left = item.left;
            }
            var right = "";
            if (typeof item.right !== 'undefined') {
                right = item.right;
            }
            var row = "<td><span>"+item.label+"</span></td>";
            
            if (type === "switch") {
                var checked = "";
                if (value) {
                    checked = "checked";
                }
                row += 
                    "<td><span class='item-left'>"+left+"</span></td>" +
                    "<td class='item-checkbox'>" +
                        "<div class='checkbox checkbox-slider--b-flat checkbox-slider-info'>" +
                            "<label>" +
                                "<input id='thing-"+thing.id+"-"+id+"' class='item item-content', type='checkbox' "+value+"><span></span>" +
                            "</label>" +
                        "</div>" +
                    "</td>" +
                    "<td><span class='item-right'>"+right+"</span></td>";
            }
            else {
                var scale = 1;
                if (typeof item.scale !== 'undefined') {
                    scale = item.scale;
                }
                if (typeof item.format !== 'undefined' && right.length == 0) {
                    var format = item.format;
                    if (format.startsWith('%s') || format.startsWith('%i')) {
                        right = format.substr(2).trim();
                    }
                    else if (format.startsWith('%.') && format.charAt(3) == 'f') {
                        right = format.substr(4).trim();
                    }
                }
                
                if (type === "text" || type === "number") {
                    var content;
                    if (typeof item.write !== 'undefined' && item.write) {
                        content = "<input id='thing-"+thing.id+"-"+id+"' class='item item-content input-small' type='"+type+"' value='"+formatItemValue(item, value*scale)+"' />";
                    }
                    else {
                        content = "<span id='thing-"+thing.id+"-"+id+"' class='item-content'>"+formatItemValue(item, value*scale)+"</span>";
                    }
                    row += "<td colspan='2'>"+content+"</td><td><span class='item-right'>"+right+"</span></td>";
                }
                else if (type == "slider" && typeof item.max !== 'undefined' && typeof item.min !== 'undefined' && typeof item.step !== 'undefined') {
                    row += 
                        "<td colspan='2'><input id='thing-"+thing.id+"-"+id+"' class='item item-content slider' type='range' min='"+item.min+"' max='"+item.max+"' step='"+item.step+"' value='"+formatItemValue(item, value)+"' /></td>" +
                        "<td><span id='thing-"+thing.id+"-"+id+"-value' class='item-content'>"+formatItemValue(item, value*scale)+"</span><span class='item-right'>"+right+"</span></td>";
                }
            }
            items += 
                "<tr data-thing='"+thing.id+"' data-item='"+id+"'>" + row + "</tr>";
        }
    }
    return items;
}

function updateItems() {
    for (var thing in things) {
        if (things.hasOwnProperty(thing)) {
            var items = things[thing].items;
            for (var id in items) {
                if (items.hasOwnProperty(id)) {
                    var item = items[id];
                    var input = $("#thing-"+thing+"-"+id);
                    
                    var type = item.type.toLowerCase();
                    var value = parseItemValue(item, type, item.value);
                    if (type == "switch") {
                        input.prop("checked", value);
                    }
                    else {
                        var scale = 1;
                        if (typeof item.scale !== 'undefined') {
                            scale = item.scale;
                        }
                        
                        if (type === "text" || type === "number") {
                            if (!isNaN(value)) {
                                value *= scale;
                            }
                            if (typeof item.write !== 'undefined' && item.write) {
                                input.val(formatItemValue(item, value));
                            }
                            else {
                                input.text(formatItemValue(item, value));
                            }
                        }
                        else if (type == "slider") {
                            input.val(formatItemValue(item, value));
                            $("#thing-"+thing+"-"+id+"-value").text(formatItemValue(item, value*scale));
                        }
                    }
                }
            }
        }
    }
}

function parseItemValue(item, type, value) {
    if (type === "switch") {
        return (value && Number(value) == 1) ? true : false;
    }
    else if (type === "text") {
        if (!value) value = "";
        
        if (typeof item['select'] !== 'undefined' && item.select.hasOwnProperty(value)) {
            value = item.select[value];
        }
    }
    if (!isNaN(value)) {
        if (value) {
            value = Number(value);
        }
        else {
            value = Number(typeof item['default'] !== 'undefined' ? item['default'] : 0);
        }
    }
    return value;
}

function formatItemValue(item, value) {
    if (!isNaN(value)) {
        if (typeof item.format !== 'undefined') {
            var format = item.format;
            if (format.startsWith('%i')) {
                value = value.toFixed(0);
            }
            else if (format.startsWith('%.') && format.charAt(3) == 'f') {
                var fixed = format.charAt(2);
                value = value.toFixed(fixed);
            }
        }
    }
    return value;
}

function itemClick(thing, id) {
    // Disable redrawing while stopping the updater, to avoid toggle buttons to be
    // switched back again, caused by badly timed asynchronous draw() calls
    redraw = false;
    updaterStop();
    
    var item = things[thing].items[id];
    var input = $("#thing-"+thing+"-"+id);
    
    var type = item.type.toLowerCase();
    if (type == "switch") {
        // The click event toggled the check already
        // Set the item value to the current state
        updateResume = function() {
            redraw = true;
            updaterStart();
        };
        if (input.is(":checked")) {
            device.setItemOn(thing, id, updateResume);
            input.prop("checked", true);
        }
        else {
            device.setItemOff(thing, id, updateResume);
            input.prop("checked", false);
        }
    }
}

// -------------------------------------------------------------------------------
// Events
// -------------------------------------------------------------------------------
$("#thing-list .collapse").off('show hide').on('show hide', function(e) {
    // Remember if the device block is collapsed, to redraw it correctly
    var id = $(this).data('thing');
    var collapse = $(this).hasClass('in');

    collapsed[id] = collapse;
});

$("#thing-list").on('click', '.item', function() {
    var self = $(this);
    var row = self.closest('tr');
    var thing = row.data('thing');
    var id = row.data('item');
    
    clearTimeout(self.data('itemClickTimeout'));
    var itemClickTimeout = setTimeout(function() {
        itemClick(thing, id);
        
    }, 250);
    self.data('itemClickTimeout', itemClickTimeout);
});

$('#thing-list').on('change', '.item', function () {
    var row = $(this).closest('tr');
    var thing = row.data('thing');
    var id = row.data('item');
    
    var item = things[thing].items[id];
    
    var type = item.type.toLowerCase();
    var value = parseItemValue(item, type, $(this).val());
    if (type == "number") {
        var scale = 1;
        if (typeof item.scale !== 'undefined' && item.scale != 0) {
            scale = item.scale;
        }
        $("#thing-"+thing+"-"+id).val(formatItemValue(item, value));
       
        value = value/scale;
    }
    
    var self = $(this);
    device.setItemValue(thing, id, value, function() {
        self.trigger('focusout');
    });
});

$('#thing-list').on('input', '.item', function () {
    var row = $(this).closest('tr');
    var thing = row.data('thing');
    var id = row.data('item');
    
    var item = things[thing].items[id];
    
    var type = item.type.toLowerCase();
    if (type == "slider") {
        var scale = 1;
        if (typeof item.scale !== 'undefined') {
            scale = item.scale;
        }
        var value = formatItemValue(item, $(this).val()*scale);
        
        $("#thing-"+thing+"-"+id+"-value").text(value);
    }
});

$('#thing-list').on('focus', '.item', function () {
    // Disable redrawing while stopping the updater, to avoid toggle buttons to be
    // switched back again, caused by badly timed asynchronous draw() calls
    redraw = false;
    updaterStop();
});

$('#thing-list').on('focusout', '.item', function () {
    redraw = true;
    updaterStart();
});

$("#thing-list").on("click", ".thing-config", function(e) {
    e.stopPropagation();
    
    // Get device of clicked thing
    var thing = device.get($(this).closest('tr').data('thing'));
    device_dialog.loadConfig(templates, thing);
});

</script>
