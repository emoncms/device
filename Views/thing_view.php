<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/device/Lib/titatoggle-dist-min.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/device/Views/thing.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/thing.js"></script>

<div class="view-container">
    <div id="thing-header" class="hide">
        <span id="api-help" style="float:right"><a href="api"><?php echo _('Things API Help'); ?></a></span>
        <h2><?php echo _('Device things'); ?></h2>
    </div>
    <div id="thing-none" class="alert alert-block hide" style="margin-top: 20px">
        <h4 class="alert-heading"><?php echo _('No Device Things configured'); ?></h4>
        <p>
            <?php echo _('Device things are configured in templates and enable the communication with different physical devices.'); ?>
            <br>
            <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('Device Thing API helper'); ?></a>
        </p>
    </div>
    <div id="thing-actions" class="hide"></div>

    <div id="thing-list"></div>

    <div id="thing-loader" class="ajax-loader"></div>
</div>

<script>

const INTERVAL_ITEMS = 5000;
const INTERVAL_REDRAW = 60000;
var redraw = true;
var redrawTime;
var timeout;
var updater;
var path = "<?php echo $path; ?>";
var templates = <?php echo json_encode($templates); ?>;

var things = {};
var items = {};

var collapsed = {};

setTimeout(function() {
    thing.list(function(result) {
        draw(result);
        
        updaterStart();
    });
}, 100);

function update() {
    thing.list(draw);
}

function updateView() {
    if (redraw) {
        thing.list(function(result) {
            var time = new Date().getTime();
            if (time - redrawTime >= INTERVAL_REDRAW) {
                redrawTime = time;
                draw(result);
            }
            else if ((time - redrawTime) % INTERVAL_ITEMS < 1000) {
                updateItems(result);
            }
        });
    }
}

function updateItems(result) {
    if (typeof result.success !== 'undefined' && !result.success) {
        alert("Error:\n" + result.message);
        return;
    }

    for (var t in result) {
        var thing = result[t];
        if (typeof thing.items === 'undefined' || thing.items.length == 0) {
            continue;
        }
        var thingid = 'thing-'+thing.nodeid.toLowerCase().replace(/[_.:/ ]/g, '-');
        
        for (var i in thing.items) {
            var item = thing.items[i];
            var itemid = thingid+'-'+item.id.toLowerCase().replace(/[_.:/ ]/g, '-');
            if (typeof items[itemid] !== 'undefined' && items[itemid].value != item.value) {
                if (typeof item.header !== 'undefined' && item.header) {
                    $("#"+itemid+"-header").html(drawItemValue(item));
                }
                $("#"+itemid+"-item").html(drawItemValue(item));
                
                item['thingid'] = thing.id;
                items[itemid] = item;
            }
        }
    }
}

function updaterStart() {
    if (updater != null) {
        clearInterval(updater);
    }
    redrawTime = new Date().getTime();
    updater = setInterval(updateView, 1000);
}

function updaterStop() {
    clearInterval(updater);
    updater = null;
}

//---------------------------------------------------------------------------------------------
// Draw things
//---------------------------------------------------------------------------------------------
function draw(result) {
    $('#thing-loader').hide();
    $("#thing-list").empty();
    
    if (typeof result.success !== 'undefined' && !result.success) {
        alert("Error:\n" + result.message);
        return;
    }
    else if (result.length == 0) {
        $("#thing-header").hide();
        $("#thing-actions").hide();
        $("#thing-none").show();

        return;
    }
    things = {};
    items = {};
    
    $("#thing-header").show();
    $("#thing-actions").show();
    $("#thing-none").hide();
    
    for (var i in result) {
        drawThing(result[i]);
    }
    registerEvents();
}

function drawThing(thing) {
    if (typeof thing.items === 'undefined' || thing.items.length == 0) {
        return;
    }
    var thingid = 'thing-'+thing.nodeid.toLowerCase().replace(/[_.:/ ]/g, '-');
    
    if (typeof collapsed[thingid] === 'undefined') {
        collapsed[thingid] = true;
    }
    
    var name = thing.name.length>0 ? thing.name : thing.nodeid;
    var description;
    if (typeof thing.description !== 'undefined') {
        description = thing.description;
    }
    else description = "";

    var header = "";
    var list = "";
    for (var i in thing.items) {
        var item = thing.items[i];
        var itemid = thingid+'-'+item.id.toLowerCase().replace(/[_.:/ ]/g, '-');
        
        if (typeof item.header !== 'undefined' && item.header) {
            header += drawItem("header", itemid, item, collapsed[thingid]);
        }
        list += "<div class='group-item'>" +
                "<div class='group-grow'></div>" +
                "<div class='item-name'><span>"+thing.items[i].label+"</span></div>" +
                drawItem("item", itemid, item, true) +
            "</div>";

        item['thingid'] = thing.id;
        items[itemid] = item;
    }
    things[thingid] = thing;
    
    $("#thing-list").append(
        "<div class='device group'>" +
            "<div id='"+thingid+"-header' class='group-header' data-toggle='collapse' data-target='#"+thingid+"-body'>" +
                "<div class='group-item' data-id='"+thingid+"'>" +
                    "<div class='group-collapse'>" +
                        "<span id='"+thingid+"-icon' class='icon-chevron-"+(collapsed[thingid] ? 'right' : 'down')+" icon-collapse'></span>" +
                    "</div>" +
                    "<div class='name'><span>"+name+(description.length>0 ? ":" : "")+"</span></div>" +
                    "<div class='description'><span>"+description+"</span></div>" +
                    "<div class='group-grow'></div>" +
                    header +
                "</div>" +
            "</div>" +
            "<div id='"+thingid+"-body' class='group-body collapse "+(collapsed[thingid] ? '' : 'in')+"'>" +
            list +
            "</div>" +
        "</div>"
    );
}

function drawItem(suffix, id, item, show) {
    
    var left = "";
    if (typeof item.left !== 'undefined') {
        left = item.left;
    }
    var right = "";
    if (typeof item.format !== 'undefined') {
        var format = item.format;
        if (format.startsWith('%s') || format.startsWith('%i')) {
            right = format.substr(2).trim();
        }
        else if (format.startsWith('%.') && format.charAt(3) == 'f') {
            right = format.substr(4).trim();
        }
    }
    if (typeof item.right !== 'undefined') {
        right += item.right;
    }
    var hide = (show ? "" : " style='display:none;'");
    
    return "<div class='item' data-id='"+id+"' data-type='"+item.type.toLowerCase()+"'>" +
            "<span class='item-left'"+hide+">"+left+"</span>" +
            "<span class='item-value'"+hide +
                " id='"+id+"-"+suffix+"'>"+drawItemValue(item)+"</span>" +
        "</div>" +
        "<div class='item item-right'><span"+hide+">"+right+"</span></div>";
}

function drawItemValue(item) {
    var type = item.type.toLowerCase();
    var value = parseItemValue(item, type, item.value);
    
    if (type === "switch") {
        var checked = "";
        if (value) {
            checked = "checked";
        }
        return "<div class='checkbox checkbox-slider--b-flat checkbox-slider-info'>" +
                "<label><input class='item-value' type='checkbox' "+checked+"><span></span></input></label>" +
            "</div>";
    }
    else {
        if (type == "slider" && typeof item.max !== 'undefined' && typeof item.min !== 'undefined' && typeof item.step !== 'undefined') {
            return "<input class='item-value slider' type='range' min='"+item.min+"' max='"+item.max+"' step='"+item.step+"' value='"+value+"' />" +
                "<span class='slider-text'>"+formatItemValue(item, value)+"</span>";
        }
        else {
            if (typeof item.write !== 'undefined' && item.write) {
                return "<input class='item-value item-center input-small' type='"+type+"' value='"+formatItemValue(item, value)+"' />";
            }
            else {
                return "<span>"+formatItemValue(item, value)+"</span>";
            }
        }
    }
}

function parseItemValue(item, type, value) {
    if (typeof value == 'undefined' || value == null) {
        if (typeof item['default'] !== 'undefined') {
            value =  item['default'];
        }
        else {
            value = '';
        }
    }
    if (!isNaN(value)) {
        value = Number(value);
    }
    else if (typeof item['default'] !== 'undefined') {
        value =  item['default'];
    }
    if (type === "switch") {
        return (value !== 0) ? true : false;
    }
    else if (type === "text" && (typeof item['select'] !== 'undefined' && item.select.hasOwnProperty(value))) {
        value = item.select[value];
    }
    return value;
}

function formatItemValue(item, value) {
    if (typeof value == 'undefined' || value == null) {
        value = '';
    }
    else if (!isNaN(value)) {
        value = Number(value);
        
        if (typeof item.scale !== 'undefined') {
            value = value*item.scale;
        }
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

//-------------------------------------------------------------------------------
// Events
//-------------------------------------------------------------------------------
function registerEvents() {

    $(".collapse").off('show hide').on('show hide', function(e) {
        // Remember if the device block is collapsed, to redraw it correctly
        var id = $(this).attr('id').replace('-body', '');
        var collapse = $(this).hasClass('in');

        collapsed[id] = collapse;
        if (collapse) {
            $("#"+id+"-icon").removeClass('icon-chevron-down').addClass('icon-chevron-right');
            $("#"+id+"-header .item > span").slideDown(200);
        }
        else {
            $("#"+id+"-icon").removeClass('icon-chevron-right').addClass('icon-chevron-down');
            $("#"+id+"-header .item > span").slideUp(200);
        }
    });

    $("#thing-list").off();
    $("#thing-list").on('click', '.item', function(e) {
        e.stopPropagation();
        
        var type = $(this).data('type');
        if (type == "switch") {
            var self = $(this);
            
            if (timeout != null) {
                clearTimeout(timeout);
            }
            timeout = setTimeout(function() {
                timeout = null;
                
                // Disable redrawing while stopping the updater, to avoid toggle buttons to be
                // switched back again, caused by badly timed asynchronous draw() calls
                redraw = false;
                updaterStop();
                
                var id = self.data('id');
                var item = items[id];
                // The click event toggled the check already
                // Set the item value to the current state
                updateResume = function() {
                    redraw = true;
                    updaterStart();
                };
                
                var input = self.find('input');
                if (input.is(":checked")) {
                    thing.setItemOn(item.thingid, item.id, updateResume);
                    input.prop("checked", true);
                }
                else {
                    thing.setItemOff(item.thingid, item.id, updateResume);
                    input.prop("checked", false);
                }
            }, 250);
        }
    });

    $('#thing-list').on('change', '.item', function (e) {
        e.stopPropagation();
        
        var type = $(this).data('type');
        if (type != "switch") {
            var id = $(this).data('id');
            var item = items[id];
            var input = $(this).find('input');
            
            var value = parseItemValue(item, type, input.val());
            if (type == "number") {
                var scale = 1;
                if (typeof item.scale !== 'undefined' && item.scale != 0) {
                    scale = item.scale;
                }
                input.val(formatItemValue(item, value));
                
                value = value/scale;
            }
            
            var self = $(this);
            thing.setItemValue(item.thingid, item.id, value, function() {
                self.trigger('focusout');
            });
        }
    });

    $('#thing-list').on('input', '.item', function(e) {
        e.stopPropagation();
        
        var type = $(this).data('type');
        if (type == "slider") {
            var id = $(this).data('id');
            var item = items[id];
            var input = $(this).find('input');
            
            var scale = 1;
            if (typeof item.scale !== 'undefined') {
                scale = item.scale;
            }
            var value = formatItemValue(item, input.val());
            $(this).find('.slider-text').text(value);
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
}

</script>
