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
        width: 35px;
        color: grey;
        background-color: white;
    }

    input.number[disabled] {
        background-color: #eee;
    }

    .checkbox-slider--b {
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
<script>

var path = "<?php echo $path; ?>";

var things = {};

var updater;
function updaterStart(func, interval) {
    clearInterval(updater);
    updater = null;
    if (interval > 0) updater = setInterval(func, interval);
}
updaterStart(update, 5000);

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
            if (redraw) {
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
                var value = parse_input_value(item);

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
                else if (type === "text" || type === "number") {
                    var right = "";
                    if (typeof item.format !== 'undefined') {
                        var format = item.format;
                        if (format.startsWith('%s')) {
                            right = format.substr(2);
                        }
                        else if (format.startsWith('%i')) {
                            right = format.substr(2);
                        }
                        else if (format.startsWith('%.') && format.charAt(3) == 'f') {
                            right = format.substr(4);
                        }
                    }
                    
                    row = "<td class='item item-left'></td>";
                    if (type === "text") {
                        row += 
                            "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>" +
                                "<span id='thing-"+thing+"-"+id+"' class='text'>"+value+"</span>" +
                            "</td>" +
                            "<td class='item item-right'><span>"+right+"</span></td>";
                    }
                    else if (type === "number") {
                        row += 
                            "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>" +
                                "<input id='thing-"+thing+"-"+id+"' class='number' type='text' value="+value+" />" +
                            "</td>" +
                            "<td class='item item-right'><span>"+right+"</span></td>";
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

function parse_input_value(item) {
    var value = item.value;

    var type = item.type.toLowerCase();
    if (type === "switch") {
        value = (value != null && Number(value) == 1) ? true : false
    }
    else if (type === "text" || type === "number") {
        if (type === "text") {
            if (value == null) value = "";
            
            if (typeof item.select !== 'undefined' && item.select.hasOwnProperty(value)) {
                value = item.select[value];
            }
        }
        if (typeof item.format !== 'undefined') {
            var format = item.format;
            if (format.startsWith('%i')) {
                value = (value != null ? value : 0).toFixed(0);
            }
            else if (format.startsWith('%.') && format.charAt(3) == 'f') {
                var fixed = format.charAt(2);
                value = (value != null ? value : 0).toFixed(fixed);
            }
        }
    }
    return value;
}

function update_inputs() {
    for (var thing in things) {
        if (things.hasOwnProperty(thing)) {
            var items = things[thing].items;
            for (var id in items) {
                if (items.hasOwnProperty(id)) {
                    var item = items[id];
                    var input = $("#thing-"+thing+"-"+id);
                    var value = parse_input_value(item);
                    
                    var type = item.type.toLowerCase();
                    if (type == "switch") {
                        input.prop("checked", value);
                    }
                    else if (type == "text") {
                        input.text(value);
                    }
                    else if (type == "number") {
                        input.val(value);
                    }
                }
            }
        }
    }
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
</script>