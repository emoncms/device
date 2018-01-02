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
        background-color:#ddd;
        cursor:pointer;
    }

    .thing-list {
        padding: 0px 5px 5px 5px;
        background-color:#ddd;
    }

    .thing-list-item {
        margin-bottom: 12px;
    }

    .item-list {
        background-color:#f0f0f0;
        border-bottom:1px solid #fff;
        border-item-left:2px solid #f0f0f0;
        height:41px;
    }

    .item {
        color: grey;
        font-weight: bold;
        padding-top: 5px;
    }

    .item-name {
        text-align: right;
        width: 75%;
    }

    .item-left {
        text-align: right;
        width: 15%;
        padding-right: 0.7%;
    }

    .item-input {
        text-align: right;
        width: 5%;
    }

    .item-right {
        text-align: item-left;
        width: 10%;
    }

    input.number {
        width: 45px;
        color: grey;
        background-color: white;
        margin-bottom: 5px;
        margin-right: 5px;
    }

    input.number[disabled] {
        background-color: #eee;
    }

    .checkbox-slider--b {
        width: 20px;
        border-radius: 25px;
        background-color: gainsboro;
        height: 20px;
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
                
                var left = "";
                var right = "";
                var input = "";
                if (type === "switch") {
                    var checked = item.value ? "checked" : "";
                    
                    left = "<span>Off</span>";
                    right = "<span>On</span>";
                    input = 
                        "<div class='checkbox checkbox-slider--b checkbox-slider-info'>" +
                            "<label>" +
                                "<input id='thing-"+thing+"-"+id+"' type='checkbox' "+checked+"><span></span>" +
                            "</label>" +
                        "</div>";
                }
                else if (type === "text") {
                    var value = (item.value ? item.value : 0).toFixed(item.format[2]);
                    var disabled = "disabled";
                    
                    right = "<span>" + item.format.split(" ")[1] + "</span>";
                    input = "<input id='thing-"+thing+"-"+id+"' class='number' type='text' value="+value+" "+disabled+" />";
                }
                
                list += 
                    "<tr>" +
                        "<td class='item item-name'>" +
                            "<span>"+item.label+"</span>" +
                        "</td>" +
                        "<td class='item item-left'>"+left+"</td>" +
                        "<td class='item item-input' thing='"+thing+"' item='"+item.id+"'>"+input+"</td>" +
                        "<td class='item item-right'>"+right+"</td>" +
                    "</tr>";
            }
        }
    }
    return "<table class='item-list' style='width:100%'>"+list+"</table>";
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
                    if (type == "switch") {
                        var checked = false;
                        if (item.value != null && Number(item.value) == 1) {
                            checked = true;
                        }
                    	input.prop("checked", checked);
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