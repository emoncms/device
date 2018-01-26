<?php
    global $path;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device.js"></script>
<link href="<?php echo $path; ?>Modules/device/Lib/titatoggle-dist-min.css" rel="stylesheet">

<style>
    .block-bound {
      background-color: rgb(68,179,226);
    }

    .block-content {
        background-color:#fff;
        color:#333;
        padding:10px;
    }

    .block-title {
        padding: 10px;
        float:left;
        color: grey;
        font-weight: bold;
    }

    .thing {
        margin-bottom:10px;
        border: 1px solid #aaa;
    }

    .thing-info {
        background-color: #ddd;
        cursor: pointer;
    }

    .thing-configure {
        float:right;
        padding:10px;
        width:30px;
        text-align:center;
        color:#666;
        border-left: 1px solid #eee;
    }

    .thing-configure:hover {
        background-color:#eaeaea;
    }

    .thing-list {
        padding: 0px 5px 5px 5px;
        background-color: #ddd;
    }

    .thing-list-item {
        margin-bottom: 12px;
    }

    .item-list {
        background-color: #f0f0f0;
        border-bottom: 1px solid #fff;
        border-item-left: 2px solid #f0f0f0;
        height: 41px;
    }

    .item {
        color: grey;
        padding-top: 5px;
    }

    .item-name {
        text-align: right;
        font-weight: bold;
        width: 80%;
    }

    .item-input {
        text-align: right;
        font-weight: bold;
        width: 1%;
    }

    .item-input .text {
        color: dimgrey;
        margin-right: 8px;
    }

    .item-left {
        text-align: right;
    }

    .item-right {
        text-align: left;
        width: 5%;
    }

    input.number {
        margin-bottom: 2px;
        margin-right: 8px;
        text-align: right;
        width: 55px;
        color: grey;
        background-color: white;
    }

    input.number[disabled] {
        background-color: #eee;
    }
    
    /* Chrome */
    @supports (-webkit-appearance:none) {
        .checkbox-slider--b {
            margin-left: 8px;
            margin-right: 8px;
            height: 20px;
        }
    }
    
    /* IE */
    @media screen and (-ms-high-contrast: active), (-ms-high-contrast: none) {
        .checkbox-slider--b {
            margin-left: 8px;
            margin-right: 8px;
            height: 20px;
        }
    }
    
    /* Firefox */
    _:-moz-tree-row(hover), .checkbox-slider--b {
        margin-left: 8px;
        margin-right: 8px;
        height: 20px;
        width: 20px;
        border-radius: 25px;
        background-color: gainsboro;
    }

    *::before, *::after {
        box-sizing: border-box;
    }

    .slider {
        -webkit-appearance: none;
        margin-left: 8px;
        margin-right: 8px;
        width: 150px;
        height: 15px;
        border-radius: 5px;
        outline: none;
    }

    .slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        border-width: 0px;
        border-radius: 50%;
        background: #44b3e2;
        cursor: pointer;
    }

    .slider::-webkit-slider-thumb:hover {
        background: #209ed3;
    }

    .slider::-moz-range-thumb {
        width: 20px;
        height: 20px;
        border-width: 0px;
        border-radius: 50%;
        background: #44b3e2;
        cursor: pointer;
    }

    .slider::-moz-range-thumb:hover {
        background: #209ed3;
    }
</style>

<div>
    <div id="api-help-header" style="float:right;"><a href="api"><?php echo _('Things Help'); ?></a></div>
    <div id="local-header"><h2><?php echo _('Things'); ?></h2></div>

    <div id="thing-list"></div>

    <div id="thing-none" class="alert alert-block hide">
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

const INTERVAL = 10000;
var path = "<?php echo $path; ?>";
var templates = <?php echo json_encode($templates); ?>;

var things = {};

function update() {
    device.listThings(function(result) {
        if (result.length != 0) {
            $("#thing-none").hide();
            $("#local-header").show();
            var redraw = Object.keys(things).length == 0 ? true : false;
            
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
            update_inputs();
        }
        else {
            $("#thing-none").show();
            $("#local-header").hide();
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
            var name = thing.name;
            var items = draw_items(thing.id);
            
            list += 
                "<div class='block-bound thing-list-item thing'>" +
                    "<div class='thing-info'>" +
                        "<div class='thing-list'>" +
                            "<table id='thing-"+name+"' style='width:100%'>" +
                                "<tr>" +
                                    "<td>" +
                                        "<div class='block-title'>"+name+"</div>" +
                                    "</td>" +
                                    "<td>" +
                                        "<div class='thing-configure' thing='"+id+"'><i class='icon-wrench icon-white'></i></div>" +
                                    "</td>" +
                                "</tr>" +
                            "</table>" +
                            items +
                        "</div>" +
                    "</div>" +
                "</div>";
        }
    }
    $("#thing-list").html(list);
}

function draw_items(thing) {
    var list = "";
    
    if (things.hasOwnProperty(thing)) {
        var items = things[thing].items;
        for (var id in items) {
            if (items.hasOwnProperty(id)) {
                var item = items[id];
                var type = item.type.toLowerCase();
                var value = parse_input_value(item, type, item.value);
                
                var row = "";
                if (type === "switch") {
                    row = 
                        "<td class='item item-left'><span>Off</span></td>" +
                        "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>" +
                            "<div class='checkbox checkbox-slider--b checkbox-slider-info'>" +
                                "<label>" +
                                    "<input id='thing-"+thing+"-"+id+"' type='checkbox' checked='"+value+"'><span></span>" +
                                "</label>" +
                            "</div>" +
                        "</td>" +
                        "<td class='item item-right'><span>On</span></td>";
                }
                else {
                    var scale = 1;
                    if (typeof item.scale !== 'undefined') {
                        scale = item.scale;
                    }
                    
                    var postfix = "";
                    if (typeof item.format !== 'undefined') {
                        var format = item.format;
                        if (format.startsWith('%s') || format.startsWith('%i')) {
                            postfix = format.substr(2).trim();
                        }
                        else if (format.startsWith('%.') && format.charAt(3) == 'f') {
                            postfix = format.substr(4).trim();
                        }
                    }
                    
                    row = "<td class='item item-left'></td>";
                    if (type === "text") {
                        row += 
                            "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>" +
                                "<span id='thing-"+thing+"-"+id+"' class='text'>"+format_input_value(item, value*scale)+"</span>" +
                            "</td>" +
                            "<td class='item item-right'><span>"+postfix+"</span></td>";
                    }
                    else if (type === "number") {
                        row += 
                            "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>" +
                                "<input id='thing-"+thing+"-"+id+"' class='number' type='text' value='"+format_input_value(item, value*scale)+"' />" +
                            "</td>" +
                            "<td class='item item-right'><span>"+postfix+"</span></td>";
                    }
                    else if (type == "slider" && typeof item.max !== 'undefined' && typeof item.min !== 'undefined' && typeof item.step !== 'undefined') {
                        row = 
                            "<td class='item item-left'></td>" +
                            "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>" +
                                "<input id='thing-"+thing+"-"+id+"' class='slider' type='range' min='"+item.min+"' max='"+item.max+"'  step='"+item.step+"' value='"+format_input_value(item, value)+"' />" +
                            "</td>" +
                            "<td class='item item-right'>" +
                                "<span id='thing-"+thing+"-"+id+"-value'>"+format_input_value(item, value*scale)+"</span><span> "+postfix+"</span>" +
                            "</td>";
                    }
                }
                list += 
                    "<table class='item-list' style='width:100%'>" +
                        "<tr>" +
                            "<td class='item item-name'>" +
                                "<span>"+item.label+"</span>" +
                            "</td>" +
                            row +
                        "</tr>" +
                    "</table>";
            }
        }
    }
    return list;
}

function update_inputs() {
    for (var thing in things) {
        if (things.hasOwnProperty(thing)) {
            var items = things[thing].items;
            for (var id in items) {
                if (items.hasOwnProperty(id)) {
                    var item = items[id];
                    var input = $("#thing-"+thing+"-"+id);
                    
                    var type = item.type.toLowerCase();
                    var value = parse_input_value(item, type, item.value);
                    if (type == "switch") {
                        input.prop("checked", value);
                    }
                    else {
                        var scale = 1;
                        if (typeof item.scale !== 'undefined') {
                            scale = item.scale;
                        }
                        
                        if (type == "text") {
                            if (!isNaN(value)) {
                                value *= scale;
                            }
                            input.text(format_input_value(item, value));
                        }
                        else if (type == "number") {
                            input.val(format_input_value(item, value*scale));
                        }
                        else if (type == "slider") {
                            input.val(format_input_value(item, value));
                            $("#thing-"+thing+"-"+id+"-value").text(format_input_value(item, value*scale));
                        }
                    }
                }
            }
        }
    }
}

function parse_input_value(item, type, value) {
    if (type === "switch") {
        value = (value && Number(value) == 1) ? true : false
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

function format_input_value(item, value) {
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

function item_click(thing, id) {
    var item = things[thing].items[id];
    var input = $("#thing-"+thing+"-"+id);
    
    var type = item.type.toLowerCase();
    if (type == "switch") {
        // The click event toggled the check already
        // Set the item value to the current state
        if (input.is(":checked")) {
            device.setItemOff(thing, id);
            input.prop("checked", false);
        }
        else {
            device.setItemOn(thing, id);
            input.prop("checked", true);
        }
    }
}

// -------------------------------------------------------------------------------
// Events
// -------------------------------------------------------------------------------
$("#thing-list").on('click', '.item-input', function(e) {
    e.stopPropagation();
    e.preventDefault();
    var $me=$(this);
    if ($me.data('clicked')) {
        $me.data('clicked', false); // reset
        if ($me.data('alreadyclickedTimeout')) clearTimeout($me.data('alreadyclickedTimeout')); // prevent this from happening

        // Do what needs to happen on double click.
        var id = $(this).attr('item');
        var thing = $(this).attr('thing');
        item_click(thing, id);
    }
    else {
        $me.data('clicked', true);
        var alreadyclickedTimeout=setTimeout(function() {
            $me.data('clicked', false); // reset when it happens

            // Do what needs to happen on single click. Use $me instead of $(this) because $(this) is  no longer the element
            var id = $me.attr('item');
            var thing = $me.attr('thing');
            item_click(thing, id);
            
        }, 250); // dblclick tolerance
        $me.data('alreadyclickedTimeout', alreadyclickedTimeout); // store this id to clear if necessary
    }
});

$('#thing-list').on('input', '.item-input', function () {
    var id = $(this).attr('item');
    var thing = $(this).attr('thing');
    var item = things[thing].items[id];
    
    var type = item.type.toLowerCase();
    if (type == "slider") {
        var scale = 1;
        if (typeof item.scale !== 'undefined') {
            scale = item.scale;
        }
        var value = format_input_value(item, $(this).children('.slider').val()*scale);
        
        $("#thing-"+thing+"-"+id+"-value").text(value);
    }
});

$('#thing-list').on('change', '.item-input', function () {
    var id = $(this).attr('item');
    var thing = $(this).attr('thing');
    var item = things[thing].items[id];
    
    var type = item.type.toLowerCase();
    var value = parse_input_value(item, type, $(this).children('input').val());
    if (type == "number") {
        var scale = 1;
        if (typeof item.scale !== 'undefined' && item.scale != 0) {
            scale = item.scale;
        }
        $("#thing-"+thing+"-"+id).val(format_input_value(item, value));
        
        device.setItemValue(thing, id, value/scale);
        $(this).children('input').trigger('focusout');
    }
    else if (type == "slider") {
        device.setItemValue(thing, id, value);
        $(this).children('input').trigger('focusout');
    }
});

$("#thing-list").on("click", ".thing-configure", function() {
    // Get device of clicked thing
    var thing = $(this).attr('thing');
    device_dialog.loadConfig(templates, device.get(thing));
});

$('#thing-list').on('focus', '.item-input input', function () {
    updaterStop();
});

$('#thing-list').on('focusout', '.item-input input', function () {
    updaterStart();
});
</script>