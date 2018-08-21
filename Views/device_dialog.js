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
    },

    'drawConfig':function() {
        $("#device-config-modal").modal('show');
        this.adjustConfigModal();
        this.clearConfigModal();

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
            var categoryid = category.replace(/\W/g, '').toLowerCase();
            
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
            
            $('#template-list').append(
                "<div class='accordion-group'>" +
                    "<div class='accordion-heading category-heading'>" +
                        "<span class='accordion-toggle' data-toggle='collapse' " +
                            "data-parent='#template-list' data-target='#template-"+categoryid+"-collapse'>" +
                            category +
                        "</span>" +
                    "</div>" +
                    "<div id='template-"+categoryid+"-collapse' class='accordion-body collapse'>" +
                        "<div class='accordion-inner'>" +
                            "<div id='template-"+categoryid+"' class='accordion'></div>" +
                        "</div>" +
                    "</div>" +
                "</div>"
            );
            
            for (var i in groups) {
                var group = groups[i];
                var groupid = group.replace(/\W/g, '').toLowerCase();
                $('#template-'+categoryid).append(
                    "<div class='accordion-group'>" +
                        "<div class='accordion-heading group-heading'>" +
                            "<span class='accordion-toggle' data-toggle='collapse' " +
                                "data-parent='#template-"+categoryid+"' data-target='#template-"+categoryid+"-"+groupid+"-collapse'>" +
                                group +
                            "</span>" +
                        "</div>" +
                        "<div id='template-"+categoryid+"-"+groupid+"-collapse' class='accordion-body collapse'>" +
                            "<div id='template-"+categoryid+"-"+groupid+"' class='accordion-inner'></div>" +
                        "</div>" +
                    "</div>"
                );
                var body = $('#template-'+categoryid+'-'+groupid);
                
                for (var i in devicesByGroup[group]) {
                    var id = devicesByGroup[group][i].id;
                    var name = devicesByGroup[group][i].name;
                    if (name.length > 25) {
                        name = name.substr(0, 25) + "...";
                    }
                    
                    body.append(
                        "<div id='template-"+categoryid+"-"+groupid+"-"+id.replace('/', '-')+"'" +
                        		"data-type='"+id+"' class='group-device'>" +
                            "<span>"+name+"</span>" +
                        "</div>"
                    );
                }
            }
        }
        
        if (this.deviceType != null && this.deviceType != '') {
            if (this.templates[this.deviceType]!=undefined) {
                var template = this.templates[this.deviceType];
                var category = template.category.replace(/\W/g, '').toLowerCase();
                var group = template.group.replace(/\W/g, '').toLowerCase();
                var id = this.deviceType.replace('/', '-');

                $("#template-"+category+"-collapse").collapse('show');
                $("#template-"+category+"-"+group+"-collapse").collapse('show');
                $("#template-"+category+"-"+group+"-"+id).addClass("device-selected");
                
                $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
                $('#template-info').show();
            }
        }
        
        // Initialize callbacks
        this.registerConfigEvents();
    },

    'clearConfigModal':function() {
        $("#template-list").text('');
        
        var tooltip = "Defaults, like inputs and associated feeds will be automaticaly configured together with the device.<br>" +
                "Initializing a device usualy should only be done once on installation. " +
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
        
        if (width < 680) {
            $("#device-sidebar-open").show();
            $("#device-sidebar-close").show();
            
            $("#device-sidebar").css("transition","0.5s");
            $("#device-sidebar").css("width","0");
            
            $("#device-content").css("transition","0.5s");
            $("#device-content").css("margin-left","0");
        	$("#device-config-modal").css("margin-left","0").css("margin-right","0");
        } else {
            $("#device-sidebar-open").hide();
            $("#device-sidebar-close").hide();
            
            $("#device-sidebar").css("transition","0");
            $("#device-sidebar").css("width","250px");
            
            $("#device-content").css("transition","0");
            $("#device-content").css("margin-left","250px");
        	$("#device-config-modal").css("margin-left","auto").css("margin-right","auto");
        }
    },

    'registerConfigEvents':function() {

        $("#template-list").off('click').on('click', '.group-device', function () {
            var type = $(this).data("type");
            
            $(".group-device[data-type='"+device_dialog.deviceType+"']").removeClass("device-selected");
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
            if ($(window).width() < 1024) {
                $("#device-sidebar").css("width","0");
            }
            
            device_dialog.drawTemplate();
        });

        $("#device-sidebar-open").off('click').on('click', function () {
            $("#device-sidebar").css("width","250px");
        });

        $("#device-sidebar-close").off('click').on('click', function () {
            $("#device-sidebar").css("width","0");
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
                    var type = device_dialog.deviceType;
                    var result = device.create(node, name, desc, type);
                    if (typeof result.success !== 'undefined' && !result.success) {
                        alert('Unable to create device:\n'+result.message);
                        return false;
                    }
                    if (type != null) {
                        device_dialog.device = {
                                id: result,
                                nodeid: node,
                                name: name,
                                type: type
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
        if (device_dialog.deviceType == null || device_dialog.deviceType == "" || !device_dialog.deviceType in device_dialog.templates) {
            $('#template-description').text('');
            $('#template-info').hide();
            return;
        }
        
        console.log("deviceType:"+device_dialog.deviceType)
        console.log(device_dialog.templates)
        var template = device_dialog.templates[device_dialog.deviceType];
        $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
        $('#template-info').show();
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
            
            $('#wrap').trigger("device-init");
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
            if (process['arguments'] != undefined && process['arguments']['type'] != undefined) {
                var title;
                var label;
                switch(process['arguments']['type']) {
                case 0: // VALUE
                	label = "important";
                    title = "Value - ";
                    break;
                    
                case 1: //INPUTID
                	label = "warning";
                    title = "Input - ";
                    break;
                    
                case 2: //FEEDID
                	label = "info";
                    title = "Feed - ";
                    break;
                    
                case 3: // NONE
                	label = "important";
                    title = "";
                    break;
                    
                case 4: // TEXT
                	label = "important";
                    title = "Text - ";
                    break;
                    
                case 5: // SCHEDULEID
                	label = "warning";
                    title = "Schedule - "
                    break;
                    
                default:
                	label = "important";
                    title = "ERROR - ";
                    break;
                }
            	title += process["name"];
                
                if (process['arguments']['value'] != undefined) {
                	title += ": " + process['arguments']['value'];
                }
                
                out += "<span class='label label-"+label+"' title='"+title+"' style='cursor:default'><small>"+process["short"]+"</small></span> ";
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
            $('#wrap').trigger("device-delete");
        });
    }
}
