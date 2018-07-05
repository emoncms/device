var device_dialog =
{
    templates: null,
    deviceTemplate: null,
    deviceOptions: null,
    deviceType: null,
    device: null,

    'loadConfig':function(templates, device) {
        this.templates = templates;
        
        if (device != null) {
            this.deviceTemplate = null;
            this.deviceOptions = null;
            this.deviceType = device.type;
            this.device = device;
        }
        else {
            this.deviceTemplate = null;
            this.deviceOptions = null;
            this.deviceType = null;
            this.device = null;
        }
        
        this.drawConfig();
    },

    'drawConfig':function() {
        $("#device-config-modal").modal('show');
        this.adjustConfigModal();
        this.clearConfigModal();

        var out = "";

        var categories = [];
        var devicesByCategory = {};
        for (var id in this.templates) {
            var device = this.templates[id];
            device['id'] = id;
            
            if (devicesByCategory[device.category] == undefined) {
                devicesByCategory[device.category] = [];
                categories.push(device.category);
            }
            devicesByCategory[device.category].push(device);
        }
        // Place OpenEnergyMonitor prominently at first place, while sorting other categories
        if (categories.indexOf('OpenEnergyMonitor') > -1) {
            categories.splice(categories.indexOf('OpenEnergyMonitor'), 1);
            categories.sort()
            categories = ['OpenEnergyMonitor'].concat(categories);
        }
        
        for (var i in categories) {
            var category = categories[i];

            var groups = [];
            var devicesByGroup = {};
            for (var i in devicesByCategory[category]) {
                var group = devicesByCategory[category][i].group;
                if (devicesByGroup[group] == undefined) {
                    devicesByGroup[group] = [];
                    groups.push(group);
                }
                devicesByGroup[group].push(devicesByCategory[category][i]);
            }
            groups.sort();
            
            out += "<tbody>"
            out += "<tr class='category-header' category='"+category+"' style='background-color:#aaa; cursor:pointer'>";
            out += "<td style='font-size:12px; padding:4px; padding-left:8px; font-weight:bold'>"+category+"</td>";
            out += "</tr>";
            out += "</tbody>";
            
            for (var i in groups) {
                var group = groups[i];
                
                out += "<tbody class='group-header' group='"+group+"' category='"+category+"' style='display:none'>"
                out += "<tr style='background-color:#ccc; cursor:pointer'>";
                out += "<td style='font-size:12px; padding:4px; padding-left:16px; font-weight:bold'>"+group+"</td>";
                out += "</tr>";
                out += "</tbody>";
                
                out += "<tbody class='group-body' group='"+group+"' category='"+category+"' style='display:none'>";
                
                for (var i in devicesByGroup[group]) {
                    var id = devicesByGroup[group][i].id;
                    var name = devicesByGroup[group][i].name;
                    if (name.length > 25) {
                        name = name.substr(0, 25) + "...";
                    }
                    
                    out += "<tr class='group-row' type='"+id+"'>";
                    out += "<td style='padding-left:24px; cursor:pointer'>"+name+"</td>";
                    out += "</tr>";
                }
                out += "</tbody>";    
            }
        }
        $("#template-table").html(out);
        
        if (this.deviceType != null && this.deviceType != '') {
            if (this.templates[this.deviceType]!=undefined) {
                var template = this.templates[this.deviceType]
                
                $(".group-header[category='"+template.category+"']").show();
                $(".group-body[category='"+template.category+"'][group='"+template.group+"']").show();
                $(".group-row[type='"+this.deviceType+"']").addClass("device-selected");
                
                $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
                $('#template-info').show();
            }
        }
        
        // Initialize callbacks
        this.registerConfigEvents();
    },

    'clearConfigModal':function() {
        $("#template-table").text('');
        
        var tooltip = "Defaults, like inputs and associated feeds will be automaticaly configured together with the device." +
                "<br><br>" +
                "Initializing a device usualy should only be done once on installation.<br>" +
                "If the configuration was already applied, only missing inputs and feeds will be created.";
        
        $('#template-tooltip').attr("title", tooltip).tooltip({html: true});
        
        if (this.device != null) {
            $('#device-config-node').val(this.device.nodeid);
            $('#device-config-name').val(this.device.name);
            $('#device-config-description').val(this.device.description);
            $('#device-config-devicekey').val(this.device.devicekey).prop("disabled", false);
            $("#device-config-devicekey-new").prop("disabled", false);
            $('#device-delete').show();
            $("#device-save").html("Save");
            if (this.device.type != null && this.device.type != '') {
                $("#device-init").show();
            }
            else {
                $("#device-init").hide();
            }
        }
        else {
            $('#device-config-node').val('');
            $('#device-config-name').val('');
            $('#device-config-description').val('');
            $('#device-config-devicekey').val('').prop("disabled", true);
            $("#device-config-devicekey-new").prop("disabled", true);
            $('#device-delete').hide();
            $("#device-init").hide();
            $("#device-save").html("Save & Initialize");
        }
        device_dialog.drawTemplate();
    },

    'adjustConfigModal':function() {

        var width = $(window).width();
        var height = $(window).height();
        
        if ($("#device-config-modal").length) {
            var h = height - $("#device-config-modal").position().top - 180;
            $("#device-config-body").height(h);
        }

        $("#content-wrapper").css("transition","0");
        $("#sidebar-wrapper").css("transition","0");
        if (width < 1024) {
            $("#content-wrapper").css("margin-left","0");
            $("#sidebar-wrapper").css("width","0");
            $("#sidebar-open").show();

            $("#content-wrapper").css("transition","0.5s");
            $("#sidebar-wrapper").css("transition","0.5s");
        } else {
            $("#content-wrapper").css("margin-left","250px");
            $("#sidebar-wrapper").css("width","250px");
            $("#sidebar-open").hide();
            $("#sidebar-close").hide();
        }
    },

    'registerConfigEvents':function() {

        $('#template-table .category-header').off('click').on('click', function() {
            var category = $(this).attr("category");
            
            var e = $(".group-header[category='"+category+"']");
            if (e.is(":visible")) {
                $(".group-body[category='"+category+"']").hide();
                e.hide();
            }
            else {
                e.show();

                // If a device is selected and in the category to uncollapse, show and select it
                if (device_dialog.deviceType != null && device_dialog.deviceType != '') {
                    var template = device_dialog.templates[device_dialog.deviceType];
                    if (template && category == template.category) {
                        $(".group-body[category='"+template.category+"'][group='"+template.group+"']").show();
                        $(".group-row[type='"+device_dialog.deviceType+"']").addClass("device-selected");
                    }
                }
            }
        });

        $('#template-table .group-header').off('click').on('click', function() {
            var group = $(this).attr("group");
            var category = $(this).attr("category");
            
            var e = $(".group-body[group='"+group+"'][category='"+category+"']");
            if (e.is(":visible")) {
                e.hide();
            }
            else {
                e.show();

                // If a device is selected and in the category to uncollapse, show and select it
                if (device_dialog.deviceType != null && device_dialog.deviceType != '') {
                    var template = device_dialog.templates[device_dialog.deviceType];
                    if (category == template.category && group == template.group) {
                        $(".group-row[type='"+device_dialog.deviceType+"']").addClass("device-selected");
                    }
                }
            }
        });

        $("#template-table .group-row").off('click').on('click', function () {
            var type = $(this).attr("type");
            
            $(".group-row[type='"+device_dialog.deviceType+"']").removeClass("device-selected");
            if (device_dialog.deviceType !== type) {
                $(this).addClass("device-selected");
                device_dialog.deviceType = type;
                
                var template = device_dialog.templates[type];
                $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
                $('#template-info').show();
                $("#device-init").hide();
            }
            else {
                device_dialog.deviceType = null;
                
                $('#template-description').text('');
                $('#template-info').hide();
                $("#device-init").show()
            }
            
            device_dialog.drawTemplate();
        });

        $("#sidebar-open").off('click').on('click', function () {
            $("#sidebar-wrapper").css("width","250px");
            $("#sidebar-close").show();
        });

        $("#sidebar-close").off('click').on('click', function () {
            $("#sidebar-wrapper").css("width","0");
            $("#sidebar-close").hide();
        });

        $("#device-save").off('click').on('click', function () {

            var node = $('#device-config-node').val();
            var name = $('#device-config-name').val();
            
            if (name && node) {
                var desc = $('#device-config-description').val();
                var devicekey = $('#device-config-devicekey').val();
                var options = null;
                if (device_dialog.deviceType != null && device_dialog.templates[device_dialog.deviceType].options) {
                    options = device_dialog.parseOptions();
                    if (options == null) {
                        alert('Required options need to be configured first.');
                        return false;
                    }
                }
                
                var init = false;
                if (device_dialog.device != null) {
                    var fields = {};
                    if (device_dialog.device.nodeid != node) fields['nodeid'] = node;
                    if (device_dialog.device.name != name) fields['name'] = name;
                    if (device_dialog.device.description != desc) fields['description'] = desc;
                    if (device_dialog.device.devicekey != devicekey) fields['devicekey'] = devicekey;
                    
                    if (device_dialog.device.type != device_dialog.deviceType) {
                        if (device_dialog.deviceType != null) {
                            fields['type'] = device_dialog.deviceType;
                            init = true;
                        }
                        else fields['type'] = '';
                    }
                    
                    if (options != null) {
                        var updated = false;
                        if (device_dialog.device.options === '') {
                            updated = true;
                        }
                        else if (JSON.stringify(device_dialog.device.options) != JSON.stringify(options)) {
                            updated = true;
                        }
                        if (updated) {
                            fields['options'] = options;
                        }
                    }
                    
                    var result = device.set(device_dialog.device.id, fields);
                    if (typeof result.success !== 'undefined' && !result.success) {
                        alert('Unable to update device fields:\n'+result.message);
                        return false;
                    }
                    update();
                }
                else {
                    var type = device_dialog.deviceType;
                    var result = device.create(node, name, desc, type, options);
                    if (typeof result.success !== 'undefined' && !result.success) {
                        alert('Unable to create device:\n'+result.message);
                        return false;
                    }
                    if (type != null) {
                        device_dialog.device = {
                                id: result,
                                nodeid: node,
                                name: name,
                                type: type,
                                options: options
                        };
                        init = true;
                    }
                    update();
                }
                $('#device-config-modal').modal('hide');
                if (init) device_dialog.loadInit();
            }
            else {
                alert('Device needs to be configured first.');
                return false;
            }
        });
        
        $("#device-delete").off('click').on('click', function () {
            $('#device-config-modal').modal('hide');
            device_dialog.loadDelete(device_dialog.device, null);
        });
        
        $("#device-init").off('click').on('click', function () {
            $('#device-config-modal').modal('hide');
            device_dialog.loadInit();
        });
        
        $("#device-config-devicekey-new").off('click').on('click', function () {
            device_dialog.device.devicekey = device.setNewDeviceKey(device_dialog.device.id);
            $('#device-config-devicekey').val(device_dialog.device.devicekey);
        });
    },

    'drawTemplate':function() {
        device_dialog.deviceOptions = [];
        
        if (this.deviceType !== null && this.deviceType in this.templates) {
            var template = this.templates[this.deviceType];
            $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
            $('#template-info').show();
            
            if (template.options) {
                // Clear all options table while requesting template options from server
                $("#template-options").show();
                var header = $('#template-options-header-icon');
                if (header.hasClass('icon-minus-sign')) {
                    header.removeClass('icon-minus-sign');
                    header.addClass('icon-plus-sign');
                }
                $("#template-options-table-header").hide();
                $("#template-options-table").empty().hide();
                $("#template-options-select").prop("disabled", true).empty().append("<option selected hidden value=''>Select an Option</option>");
                $("#template-options-add").prop("disabled", true);
                $("#template-options-footer").hide();
                
                device.getTemplateOptions(template.id, function(result) {
                    device_dialog.deviceOptions = result;
                    device_dialog.drawOptions();
                });
            }
            else {
                $("#template-options").hide();
            }
            
            // Initialize callbacks
            this.registerTemplateEvents();
        }
        else {
            $('#template-description').text('');
            $('#template-info').hide();
            $("#template-options").hide();
        }
    },
    
    'drawOptions':function() {
        var select = $("#template-options-select");
        
        // Show options, if at least one of them is defined or mandatory
        var show = false;
        for (var i = 0; i < device_dialog.deviceOptions.length; i++) {
            var option = device_dialog.deviceOptions[i];
            if (option.mandatory || (device_dialog.device != null && typeof device_dialog.device.options[option.id] !== 'undefined')) {
                show = true;
                device_dialog.drawOptionInput(option);
            }
            else {
                select.append($("<option />").val(option.id).text(option.name).css('color', 'black'));
            }
        }
        var header = $('#template-options-header-icon');
        if (show) {
            if (header.hasClass('icon-plus-sign')) {
                header.removeClass('icon-plus-sign');
                header.addClass('icon-minus-sign');
            }
            $("#template-options-table-header").show();
            $("#template-options-table").show();
            $("#template-options-footer").show();
        }
        
        select.css('color', '#888').css('font-style', 'italic');
        select.on('change', function() {
            select.off('change');
            select.css('color', 'black').css('font-style', 'normal');
        });
        if ($("option", select).size() > 1) {
            select.prop("disabled", false).val('');
            $("#template-options-add").prop("disabled", false);
        }
        else {
            select.prop("disabled", true).val('');
            $("#template-options-add").prop("disabled", true);
        }
    },

    'drawOptionInput':function(option) {
        var table = $("#template-options-table");
        
        table.append("<tr id='template-option-"+option.id+"-row' row='"+option.id+"'><td class='template-option'>"+option.name+"</td></tr>");
        var row = $("#template-option-"+option.id+"-row");
        
        var value = null;
        if (device_dialog.device != null && typeof device_dialog.device.options[option.id] !== 'undefined') {
            value = device_dialog.device.options[option.id];
        }
        if (value == null && typeof option['default'] !== 'undefined') {
            value = option['default'];
        }
        
        var type = option.type;
        if (type === 'text') {
            row.append("<td><input id='template-option-"+option.id+"-text' type='text' /></td>").hide().fadeIn(300);
            if (value != null) {
                $("#template-option-"+option.id+"-text").val(value);
            }
        }
        else if (type === 'selection') {
            row.append("<td><select id='template-option-"+option.id+"-select' class='input-large'></select></td>").hide().fadeIn(300);
            
            var select = $("#template-option-"+option.id+"-select").empty();
            select.append("<option selected hidden value=''>Select a "+option.name+"</option>");
            option.select.forEach(function(val) {
                select.append($("<option />").val(val.value).text(val.name).css('color', 'black'));
            });
            if (value != null) {
                select.val(value);
            }
            else {
                select.css('color', '#888').css('font-style', 'italic');
                select.on('change', function() {
                    select.off('change');
                    select.css('color', 'black').css('font-style', 'normal');;
                });
            }
        }
        else if (type === 'switch') {
            row.append(
                "<td><div class='checkbox checkbox-slider--b checkbox-slider-info'>" +
                    "<label>" +
                        "<input id='template-option-"+option.id+"-switch' type='checkbox'><span></span>" +
                    "</label>" +
                "</div></td>"
            ).hide().fadeIn(300);
            if (value != null) {
                $("#template-option-"+option.id+"-switch").prop("checked", value);
            }
        }
        
        if(!option.mandatory) {
            row.append("<td></td>")
            row.append("<td class='template-option'><a id='template-option-"+option.id+"-remove' class='template-option-remove' title='Remove'><i class='icon-trash' style='cursor:pointer'></i></a></td>");
        }
        else {
            row.append("<td class='template-option'><span style='color:#888; font-size:12px'><em>mandatory</em></span></td>")
            row.append("<td class='template-option'><a><i class='icon-trash' style='cursor:not-allowed;opacity:0.3'></i></a></td>");
        }
        
        table.append("<tr><td colspan='4' id='template-option-"+option.id+"-info' class='template-option-info' style='display: none;'>" +
                "<div class='alert alert-info' style='margin:0px'>"+option.description+"</div></td></tr>");
    },

    'registerTemplateEvents':function() {

        // Event: minimise or maximise settings
        $('#template-options-header').off().on('click touchend', '.toggle-header', function(e) {
            e.stopPropagation();
            e.preventDefault();
            var $me=$(this);
            if ($me.data('clicked')) {
                $me.data('clicked', false); // reset
                if ($me.data('alreadyclickedTimeout')) clearTimeout($me.data('alreadyclickedTimeout')); // prevent this from happening

                // Do what needs to happen on double click.
                var header = $('#template-options-header');
                if (header.hasClass('icon-plus-sign')) {
                    header.removeClass('icon-plus-sign');
                    header.addClass('icon-minus-sign');
                    
                    $("#template-options-table-header").show();
                    $("#template-options-table").show();
                    $("#template-options-footer").show();

                    $("#content-wrapper").animate({ scrollTop:  $("#content-wrapper").height() }, 1000);
                }
                else {
                    header.addClass('icon-plus-sign');
                    header.removeClass('icon-minus-sign');
                    
                    $("#template-options-table-header").hide();
                    $("#template-options-table").hide();
                    $("#template-options-footer").hide();
                }
            }
            else {
                $me.data('clicked', true);
                var alreadyclickedTimeout=setTimeout(function() {
                    $me.data('clicked', false); // reset when it happens
    
                    // Do what needs to happen on single click. Use $me instead of $(this) because $(this) is  no longer the element
                    var header = $('#template-options-header-icon');
                    if (header.hasClass("icon-plus-sign")) {
                        header.removeClass("icon-plus-sign");
                        header.addClass("icon-minus-sign");
                        
                        $("#template-options-table-header").show();
                        $("#template-options-table").show();
                        $("#template-options-footer").show();
                        
                        $("#content-wrapper").animate({ scrollTop:  $("#content-wrapper").height() }, 1000);
                    }
                    else {
                        header.addClass('icon-plus-sign');
                        header.removeClass('icon-minus-sign');
                        
                        $("#template-options-table-header").hide();
                        $("#template-options-table").hide();
                        $("#template-options-footer").hide();
                    }
                
                }, 250); // dblclick tolerance
                $me.data('alreadyclickedTimeout', alreadyclickedTimeout); // store this id to clear if necessary
            }
        });

        $('#template-options-table').off();

        $('#template-options-table').on('click', '.template-option', function() {
            var id = $(this).closest('tr').attr('row');
            var row = $("#template-option-"+id+"-row");
            var info = $("#template-option-"+id+"-info");
            if (row.hasClass("template-option-selected")) {
                row.removeClass("template-option-selected");
                info.removeClass("template-option-selected").fadeOut(100);
            }
            else {
                var id = $(".template-option-selected").closest('tr').attr('row');
                $(".template-option-selected").removeClass("template-option-selected");
                $("#template-option-"+id+"-info").fadeOut(100);
                
                row.addClass("template-option-selected");
                info.addClass("template-option-selected").fadeIn(300);
            }
        });

        $('#template-options-table').on('click', '.template-option-remove', function() {
            var id = $(this).closest('tr').attr('row');
            
            $("#template-option-"+id+"-row").fadeOut(500, $("#template-option-"+id+"-row").remove());
            $("#template-option-"+id+"-info").fadeOut(500, $("#template-option-"+id+"-info").remove());
            
            var option = device_dialog.deviceOptions.find(function(opt) {
                return opt.id === id;
            });
            
            var select = $("#template-options-select");
            select.append($("<option />").val(option.id).text(option.name).css('color', 'black'));
            if ($("option", select).size() > 1) {
                select.prop("disabled", false).val('');
                $("#template-options-add").prop("disabled", false);
            }
            else {
                select.prop("disabled", true).val('');
                $("#template-options-add").prop("disabled", true);
            }
        });

        $("#template-options-add").off('click').on('click', function() {
            var select = $("#template-options-select");
            var value = $('option:selected', select);
            
            var id = value.val();
            if (id != "" && $("#template-option-"+id+"-row").val() === undefined) {
                value.remove();
                
                var option = device_dialog.deviceOptions.find(function(opt) {
                    return opt.id === id;
                });
                device_dialog.drawOptionInput(option);
                
                select.css('color', '#888').css('font-style', 'italic');
                select.on('change', function() {
                    select.off('change');
                    select.css('color', 'black').css('font-style', 'normal');
                });
                if ($("option", select).size() > 1) {
                    select.prop("disabled", false).val('');
                    $("#template-options-add").prop("disabled", false);
                }
                else {
                    select.prop("disabled", true).val('');
                    $("#template-options-add").prop("disabled", true);
                }
            }
        });
    },

    'loadInit': function() {
        var result = device.prepareTemplate(device_dialog.device.id);
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Unable to initialize device:\n'+result.message);
            return false;
        }
        device_dialog.deviceTemplate = result;
        device_dialog.drawInit(result);
        
        // Initialize callbacks
        $("#device-init-confirm").off('click').on('click', function() {
            $('#device-init-modal').modal('hide');
            
            var template = device_dialog.parseTemplate();
            var result = device.init(device_dialog.device.id, template);
            if (typeof result.success !== 'undefined' && !result.success) {
                alert('Unable to initialize device:\n'+result.message);
                return false;
            }
        });
    },

    'drawInit': function (result) {
        $('#device-init-modal').modal('show');
        device_dialog.adjustInitModal();
        
        $('#device-init-modal-label').html('Initialize Device: <b>'+device_dialog.device.name+'</b>');  
        
        if (typeof result.feeds !== 'undefined' && result.feeds.length > 0) {
            $('#device-init-feeds').show();
            var table = "";
            for (var i = 0; i < result.feeds.length; i++) {
                var feed = result.feeds[i];
                var row = "";
                if (feed.action.toLowerCase() == "none") {
                    row += "<td><input row='"+i+"' class='input-select' type='checkbox' checked disabled /></td>";
                }
                else {
                    row += "<td><input row='"+i+"' class='input-select' type='checkbox' checked /></td>";
                }
                row += "<td>"+device_dialog.drawInitAction(feed.action)+"</td>"
                row += "<td>"+feed.tag+"</td><td>"+feed.name+"</td>";
                row += "<td>"+device_dialog.drawInitProcessList(feed.processList)+"</td>";
                
                table += "<tr>"+row+"</tr>";
            }
            $('#device-init-feeds-table').html(table);
        }
        else {
            $('#device-init-feeds').hide();
        }
        
        if (typeof result.inputs !== 'undefined' && result.inputs.length > 0) {
            $('#device-init-inputs').show();
            var table = "";
            for (var i = 0; i < result.inputs.length; i++) {
                var input = result.inputs[i];
                var row = "";
                if (input.action.toLowerCase() == "none") {
                    row += "<td><input row='"+i+"' class='input-select' type='checkbox' checked disabled /></td>";
                }
                else {
                    row += "<td><input row='"+i+"' class='input-select' type='checkbox' checked /></td>";
                }
                row += "<td>"+device_dialog.drawInitAction(input.action)+"</td>"
                row += "<td>"+input.node+"</td><td>"+input.name+"</td><td>"+input.description+"</td>";
                row += "<td>"+device_dialog.drawInitProcessList(input.processList)+"</td>";
                
                table += "<tr>"+row+"</tr>";
            }
            $('#device-init-inputs-table').html(table);
        }
        else {
            $('#device-init-inputs').hide();
            $('#device-init-inputs-table').html("");
        }
        
        return true;
    },

    'drawInitAction': function (action) {
        action = action.toLowerCase();
        
        var color;
        if (action === 'create' || action === 'set') {
            color = "rgb(0,110,205)";
        }
        else if (action === 'override') {
            color = "rgb(255,125,20)";
        }
        else {
            color = "rgb(50,200,50)";
            action = "exists"
        }
        action = action.charAt(0).toUpperCase() + action.slice(1);
        
        return "<span style='color:"+color+";'>"+action+"</span>";
    },

    'drawInitProcessList': function (processList) {
        if (!processList || processList.length < 1) return "";
        var out = "";
        for (var i = 0; i < processList.length; i++) {
            var process = processList[i];
            if (process['arguments'] != undefined && process['arguments']['value'] != undefined && process['arguments']['type'] != undefined) {
                var name = "<small>"+process["name"]+"</small>";
                var value = process['arguments']['value'];
                
                var title;
                var color = "info";
                switch(process['arguments']['type']) {
                case 0: // VALUE
                    title = "Value: " + value;
                    break;
                    
                case 1: //INPUTID
                    title = "Input: " + value;
                    break;
                    
                case 2: //FEEDID
                    title = "Feed: " + value;
                    break;
                    
                case 4: // TEXT
                    title = "Text: " + value;
                    break;

                case 5: // SCHEDULEID
                    title = "Schedule: " + value;
                    break;

                default:
                    title = value;
                    break;
                }
                out += "<span class='label label-"+color+"' title='"+title+"' style='cursor:default'>"+name+"</span> ";
            }
        }
        return out;
    },

    'adjustInitModal':function() {

        var width = $(window).width();
        var height = $(window).height();
        
        if ($("#device-init-modal").length) {
            var h = height - $("#device-init-modal").position().top - 180;
            $("#device-init-body").height(h);
        }
    },

    'parseTemplate': function() {
        var template = {};
        
        template['feeds'] = [];
        if (typeof device_dialog.deviceTemplate.feeds !== 'undefined' && 
                device_dialog.deviceTemplate.feeds.length > 0) {
            
            var feeds = device_dialog.deviceTemplate.feeds;
            $("#device-init-feeds-table tr").find('input[type="checkbox"]:checked').each(function() {
                template['feeds'].push(feeds[$(this).attr("row")]); 
            });
        }
        
        template['inputs'] = [];
        if (typeof device_dialog.deviceTemplate.inputs !== 'undefined' && 
                device_dialog.deviceTemplate.inputs.length > 0) {
            
            var inputs = device_dialog.deviceTemplate.inputs;
            $("#device-init-inputs-table tr").find('input[type="checkbox"]:checked').each(function() {
                template['inputs'].push(inputs[$(this).attr("row")]); 
            });
        }
        
        return template;
    },

    'parseOptions': function() {
        var options = {};
        for (var i = 0; i < device_dialog.deviceOptions.length; i++) {
            var option = device_dialog.deviceOptions[i];
            var value = null;
            
            var type = option.type;
            if (type === 'text' && $('#template-option-'+option.id+'-text').val() != undefined) {
                value = $('#template-option-'+option.id+'-text').val();
            }
            else if (type === 'selection' && $('#template-option-'+option.id+'-select').val() != undefined) {
                value = $('#template-option-'+option.id+'-select option:selected').val();
            }
            else if (type === 'switch' && $('#template-option-'+option.id+'-switch').val() != undefined) {
                value = $('#template-option-'+option.id+'-switch').is(':checked');
            }
            if (value != null && value != "") {
                options[option.id] = value;
            }
            else if (option.mandatory) {
                return null;
            }
        }
        return options;
    },

    'loadDelete': function(device, tablerow) {
        this.device = device;
        
        $('#device-delete-modal').modal('show');
        $('#device-delete-modal-label').html('Delete Device: <b>'+device.name+'</b>');
        
        // Initialize callbacks
        this.registerDeleteEvents(tablerow);
    },

    'registerDeleteEvents':function(row) {
        
        $("#device-delete-confirm").off('click').on('click', function() {
            device.remove(device_dialog.device.id);
            if (row != null) {
                table.remove(row);
                update();
            }
            else if (typeof device_dialog.device.inputs !== 'undefined') {
                // If the table row is undefined and an input list exists, the config dialog
                // was opened in the input view and all corresponding inputs will be deleted
                var inputIds = [];
                for (var i in device_dialog.device.inputs) {
                    var inputId = device_dialog.device.inputs[i].id;
                    inputIds.push(parseInt(inputId));
                }
                input.delete_multiple(inputIds);
            }
            $('#device-delete-modal').modal('hide');
        });
    }
}
