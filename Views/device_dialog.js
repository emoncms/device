var device_dialog =
{
    templates: null,
    deviceTemplate: null,
    deviceType: null,
    device: null,

    'loadConfig':function(templates, device) {
        this.templates = templates;
        
        if (device != null) {
            this.deviceTemplate = null;
            this.deviceType = device.type;
            this.device = device;
        }
        else {
            this.deviceTemplate = null;
            this.deviceType = null;
            this.device = null;
        }
        
        this.drawConfig();
        
        // Initialize callbacks
        this.registerConfigEvents();
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
                    
                    out += "<tr class='group-row' type='"+id+"' style='cursor:pointer'>";
                    out += "<td>"+name+"</td>";
                    out += "</tr>";
                }
                out += "</tbody>";    
            }
        }
        $("#template-table").html(out);
        
        if (this.deviceType != null && this.deviceType != '') {
            if (this.templates[this.deviceType]!=undefined) {
                var template = this.templates[this.deviceType]
                
                $(".category-body[category='"+template.category+"']").show();
                $(".group-body[category='"+template.category+"'][group='"+template.group+"']").show();
                $(".group-row[type='"+this.deviceType+"']").addClass("device-selected");
                
                $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
                $('#template-info').show();
            }
        }
    },

    'drawTemplate':function() {
        if (this.deviceType !== null && this.deviceType in this.templates) {
            var template = this.templates[this.deviceType];
            $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
            $('#template-info').show();

            if (template.module == 'muc' && this.device == null) {
                // Append Controllers from database to select
                $.ajax({ url: path+"muc/controller/list.json", dataType: 'json', async: true, success: function(data, textStatus, xhr) {
                    
                    var tooltip = "The communication controller this device should be registered for.";
                    $('#template-options-ctrl-tooltip').attr("title", tooltip).tooltip({html: true});
                    
                    var ctrlSelect = $("#template-options-ctrl-select").empty();
                    ctrlSelect.append("<option selected hidden='true' value=''>Select a controller</option>").val('');
                    $.each(data, function() {
                        ctrlSelect.append($("<option />").val(this.id).text(this.description));
                    });
                    
                    $("#template-options-ctrl").show();
                    $("#template-options").show();
                }});
            }
            else {
                $("#template-options").hide();
                $("#template-options-ctrl").hide();
                $("#template-options-ctrl-select").empty();
            }
        }
        else {
            $('#template-description').text('');
            $('#template-info').hide();
            $("#template-options").hide();
            $("#template-options-ctrl").hide();
            $("#template-options-ctrl-select").empty();
        }
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
            $('#device-config-delete').show();
            $("#device-init").show();
            $("#device-save").html("Save");
        }
        else {
            $('#device-config-node').val('');
            $('#device-config-name').val('');
            $('#device-config-description').val('');
            $('#device-config-devicekey').val('').prop("disabled", true);
            $("#device-config-devicekey-new").prop("disabled", true);
            $('#device-config-delete').hide();
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
                    
                    var result = device.set(device_dialog.device.id, fields);
                    if (typeof result.success !== 'undefined' && !result.success) {
                        alert('Unable to update device fields:\n'+result.message);
                        return false;
                    }
                    update();
                }
                else {
                    var result = device.create(node, name, desc, device_dialog.deviceType);
                    if (typeof result.success !== 'undefined' && !result.success) {
                        alert('Unable to create device:\n'+result.message);
                        return false;
                    }
                    init = true;
                    device_dialog.device = {
                            id: result,
                            nodeid: node,
                            name: name,
                            type: device_dialog.deviceType
                    };
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
            
            var options = {};
            options['ctrlid'] = $('#template-options-ctrl-select option:selected').val();
            
            var template = device_dialog.parseTemplate();
            var result = device.init(device_dialog.device.id, template, options);
            if (typeof result.success !== 'undefined' && !result.success) {
                alert('Unable to initialize device:\n'+result.message);
                return false;
            }
        });
    },

    'drawInit': function (result) {
        $('#device-init-modal').modal('show');
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
