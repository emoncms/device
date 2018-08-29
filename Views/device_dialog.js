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
        
        if (device_dialog.device != null) {
        	if (typeof this.device.devicekey !== 'undefined') {
                $('#device-config-devicekey').val(this.device.devicekey).prop("disabled", false);
                $("#device-config-devicekey-new").prop("disabled", false);
        	}
        	else {
                $('#device-config-devicekey').val('').prop("disabled", true);
                $("#device-config-devicekey-new").prop("disabled", true);
        	}
        	
            $("#device-save").html("Save");
            if (typeof this.device.scanned !== 'undefined' && this.device.scanned) {
                $('#device-scan').hide();
                $('#device-back').show();
                $('#device-delete').hide();
            }
            else {
                $('#device-config-node').val(this.device.nodeid);
                $('#device-config-name').val(this.device.name);
                $('#device-config-description').val(this.device.description);
                
                if (this.device.type != null && this.device.type != '') {
                    $("#device-init").show();
                }
                else {
                    $("#device-init").hide();
                }
                $('#device-scan').hide();
                $('#device-back').hide();
                $('#device-delete').show();
            }
        }
        else {
            $('#device-config-node').val('');
            $('#device-config-name').val('');
            $('#device-config-description').val('');
            $('#device-config-devicekey').val('').prop("disabled", true);
            $("#device-config-devicekey-new").prop("disabled", true);
            
            $("#device-save").html("Save & Initialize");
            $("#device-init").hide();
            $('#device-scan').hide();
            $('#device-back').hide();
            $('#device-delete').hide();
        }
        this.drawTemplate();
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
        }
        else {
            $("#device-sidebar-open").hide();
            $("#device-sidebar-close").hide();
            
            $("#device-sidebar").css("transition","0");
            $("#device-sidebar").css("width","250px");
            
            $("#device-content").css("transition","0");
            $("#device-content").css("margin-left","250px");
            $("#device-config-modal").css("margin-left","auto").css("margin-right","auto");
        }
        var foo = $("#device-content").width();
        
        var wide = false;
        if (device_dialog.deviceType != null && device_dialog.deviceType != ''
                && device_dialog.templates[device_dialog.deviceType]!=undefined) {
            wide = device_dialog.templates[device_dialog.deviceType].options;
        }
        var node = $('#device-config-node').val();
        var name = $('#device-config-name').val();
        var desc = $('#device-config-description').val();
        if ($("#device-content").width() > 512 && wide) {
            $('#device-config-inputs').html(
                  "<table class='device-input'>" +
                      "<tr>" +
                          "<th>Node</th>" +
                          "<th>Name</th>" +
                          "<th>Location</th>" +
                      "</tr>" +
                      "<tr>" +
                          "<td><input id='device-config-node' class='input-small' type='text' value='"+node+"' required></td>" +
                          "<td><input id='device-config-name' class='input-medium' type='text' value='"+name+"' required></td>" +
                          "<td><input id='device-config-description' class='input-large' type='text' value='"+desc+"'></td>" +
                      "</tr>" +
                  "</table>"
              );
        }
        else {
            $('#device-config-inputs').html(
                "<label><b>Node</b></label>" +
                "<input id='device-config-node' class='input-medium' type='text' value='"+node+"' required>" +
                
                "<label><b>Name</b></label>" +
                "<input id='device-config-name' class='input-large' type='text' value='"+name+"' required>" +
                
                "<label><b>Location</b></label>" +
                "<input id='device-config-description' class='input-large' type='text' value='"+desc+"'>"
            );
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
                $("#device-scan").show();
            }
            else {
                device_dialog.deviceType = null;
                
                $('#template-description').text('');
                $('#template-info').hide();
                
                if (device_dialog.device != null) {
                    $("#device-init").show()
                }
                $("#device-scan").hide();
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
                var options = null;
                if (device_dialog.deviceType != null && device_dialog.templates[device_dialog.deviceType].options) {
                    options = device_dialog.parseOptions();
                    if (options == null) {
                        alert('Required options need to be configured first.');
                        return false;
                    }
                }
                
                var init = false;
                if (device_dialog.device != null && typeof device_dialog.device.id !== 'undefined') {
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

        $("#device-back").off('click').on('click', function () {
            $('#device-config-modal').modal('hide');
            $('#device-scan-modal').modal('show');
        });

        $("#device-scan").off('click').on('click', function () {
            var options = null;
            if (device_dialog.deviceType != null && device_dialog.templates[device_dialog.deviceType].options) {
                options = device_dialog.parseOptions(true);
                if (options == null) {
                    alert('Required options need to be configured first.');
                    return false;
                }
            }
            $('#device-config-modal').modal('hide');
            
            device_dialog.loadScan(options);
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
        device_dialog.adjustConfigModal();
        device_dialog.deviceOptions = [];
        
        if (device_dialog.deviceType == null || device_dialog.deviceType == "" || !device_dialog.deviceType in device_dialog.templates) {
            $('#template-description').text('');
            $('#template-info').hide();
            
            device_dialog.showOptions(false);
            return;
        }
        
        var template = device_dialog.templates[device_dialog.deviceType];
        $('#template-description').html('<em style="color:#888">'+template.description+'</em>');
        $('#template-info').show();
        
        $("#device-config-options-table").hide();
        $("#device-config-options-select").prop("disabled", true).empty().append("<option selected hidden value=''>Select an Option</option>");
        $("#device-config-options-add").prop("disabled", true);
        
        if (template.options) {
            device.getTemplateOptions(device_dialog.deviceType, function(result) {
                if (typeof result.success !== 'undefined' && !result.success) {
                    alert('Unable to retrieve template options:\n'+result.message);
                }
                if (result.length > 0) {
                    $("#device-config-options").show();
                    
                    device_dialog.deviceOptions = result;
                    device_dialog.drawOptions();
                }
            });
        }
        else {
            device_dialog.showOptions(false);
        }
    },

    'drawOptions':function() {
    	$("#device-config-options-table").empty();
    	
        // Show options, if at least one of them is defined or mandatory
        var select = $("#device-config-options-select");
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
        if (show) {
            $("#device-config-options-table").show();
            $("#device-config-options-none").hide();
        }
        else {
            $("#device-config-options-table").hide();
            $("#device-config-options-none").show();
        }
        
        select.css('color', '#888').css('font-style', 'italic');
        select.on('change', function() {
            select.off('change');
            select.css('color', 'black').css('font-style', 'normal');
        });
        if ($("option", select).length > 1) {
            select.prop("disabled", false).val('');
            $("#device-config-options-add").prop("disabled", false);
        }
        else {
            select.prop("disabled", true).val('');
            $("#device-config-options-add").prop("disabled", true);
        }
        device_dialog.showOptions(true);
        device_dialog.registerOptionEvents();
    },

    'drawOptionInput':function(option) {
        $("#device-config-options-table").append(
            "<tbody>" +
                "<tr id='device-config-option-"+option.id+"-row' class='option' data-id='"+option.id+"'>" +
                    "<td>"+option.name+"</td>" +
                "</tr>" +
                "<tr id='device-config-option-"+option.id+"-info' class='option' data-id='"+option.id+"' data-show='false' style='display:none'>" +
                    "<td colspan='4'>" +
                        "<div class='alert alert-comment'>"+option.description+"</div>" +
                    "</td>" +
                "</tr>" +
            "</tbody>"
        );
        var row = $("#device-config-option-"+option.id+"-row");
        
        var value = null;
        if (device_dialog.device != null && typeof device_dialog.device.options[option.id] !== 'undefined') {
            value = device_dialog.device.options[option.id];
        }
        if (value == null && typeof option['default'] !== 'undefined') {
            value = option['default'];
        }
        
        var type = option.type;
        if (type === 'selection') {
            row.append("<td><select id='device-config-option-"+option.id+"' class='option-input input-large'></select></td>").hide().fadeIn(300);
            
            var select = $("#device-config-option-"+option.id);
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
                "<td><div class='option-input checkbox checkbox-slider--b-flat checkbox-slider-info'>" +
                    "<label>" +
                        "<input id='device-config-option-"+option.id+"' type='checkbox'><span></span></input>" +
                    "</label>" +
                "</div></td>"
            ).hide().fadeIn(300);
            if (value != null) {
                if (typeof value === 'string' || value instanceof String) {
                    value = (value == 'true');
                }
                $("#device-config-option-"+option.id).html('<span>checked</span>').prop("checked", value);
            }
        }
        else {
            row.append("<td><input id='device-config-option-"+option.id+"' type='text' class='option-input input-large'></input></td>").hide().fadeIn(300);
            if (value != null) {
                $("#device-config-option-"+option.id).val(value);
            }
        }
        
        if(!option.mandatory) {
            row.append("<td></td>")
            row.append("<td><a id='device-config-option-"+option.id+"-remove' class='option-remove' title='Remove'><i class='icon-trash' style='cursor:pointer'></i></a></td>");
        }
        else {
            var comment = 'mandatory';
            if (typeof option.scan !== 'undefined' && option.scan) {
                comment = 'scannable';
            }
            row.append("<td><span style='color:#888; font-size:12px'><em>"+comment+"</em></span></td>")
            row.append("<td><a><i class='icon-trash' style='cursor:not-allowed;opacity:0.3'></i></a></td>");
            
//            $("#device-config-option-"+option.id).prop("required", true);
        }
    },

    'showOptions':function(show) {
        if (show) {
            if (!$("#device-config-options-body").hasClass('in')) {
                $("#device-config-options-body").collapse('show');
                $("#device-config-options-header .icon-collapse").removeClass('icon-chevron-right').addClass('icon-chevron-down');
            }
            $('#device-config-options-overlay').hide();
        }
        else {
            if ($("#device-config-options-body").hasClass('in')) {
                $("#device-config-options-body").collapse('hide');
                $("#device-config-options-header .icon-collapse").removeClass('icon-chevron-down').addClass('icon-chevron-right');
            }
            $('#device-config-options-overlay').show();
        }
    },

    'registerOptionEvents':function() {

        $("#device-config-options-header").off().on("click", function() {
            if ($("#device-config-options-body").hasClass('in')) {
                $("#device-config-options-header .icon-collapse").removeClass('icon-chevron-down').addClass('icon-chevron-right');
            }
            else {
                $("#device-config-options-header .icon-collapse").removeClass('icon-chevron-right').addClass('icon-chevron-down');
//                $("#device-content").animate({ scrollTop: $('#device-config-options-footer').offset().top }, 250);
            }
        });

        $("#device-config-options-body").off('hidden').on("hidden", function() {
        	// Only hide the options collapse after the animation finished.
        	// Otherwise, expanding it again may fail
            if (device_dialog.deviceType == null || device_dialog.deviceType == ""
                    || !device_dialog.deviceType in device_dialog.templates
                    || !device_dialog.templates[device_dialog.deviceType].options) {
            	
                $("#device-config-options").hide();
            }
        });

        $('#device-config-options-table').off('click');
        $('#device-config-options-table').on('click', '.alert', function(e) {
        	e.stopPropagation();
        });
        $('#device-config-options-table').on('click', '.option', function() {
            var id = $(this).closest('tr').data('id');
            var info = $("#device-config-option-"+id+"-info");
            if (typeof info !== 'undefined' && info.data('show')) {
                info.data('show', false);
                info.find('td > div').slideUp(function() { info.hide(); });
            }
            else {
                // Hide already shown option infos and open the selected afterwards
                // TODO: find a way to avoid display errors with select inputs if an info above it is collapsed
//                $(".table-options tr[data-show]").each(function() {
//                    if ($(this).data('show')) {
//                        info.find('td > div').slideUp(function() { info.hide(); });
//                    }
//                });
                info.data('show', true).show().find('td > div').slideDown();
            }
        });

        $('#device-config-options-table').on('click', '.option-input', function(e) {
            e.stopPropagation();

            var id = $(this).closest('tr').data('id');
            var info = $("#device-config-option-"+id+"-info");
            if (typeof info !== 'undefined' && !info.data('show')) {
                info.data('show', true).show().find('td > div').slideDown();
            }
        });

        $('#device-config-options-table').on('click', '.option-remove', function() {
            e.stopPropagation();
            
            var id = $(this).closest('tr').data('id');
            var removeRow = function() {
                $(this).remove(); 
                
                if ($('#device-config-options-table tr').length == 0) {
                    $('#device-config-options-table').hide();
                    $('#device-config-options-none').show();
                }
            }
            $("#device-config-option-"+id+"-row").fadeOut(removeRow);
            $("#device-config-option-"+id+"-info").fadeOut(removeRow);
            
            var option = device_dialog.deviceOptions.find(function(opt) {
                return opt.id === id;
            });
            
            var select = $("#device-config-options-select");
            select.append($("<option />").val(option.id).text(option.name).css('color', 'black'));
            if ($("option", select).length > 1) {
                select.prop("disabled", false).val('');
                $("#device-config-options-add").prop("disabled", false);
            }
            else {
                select.prop("disabled", true).val('');
                $("#device-config-options-add").prop("disabled", true);
            }
        });

        $("#device-config-options-add").off('click').on('click', function() {
            var select = $("#device-config-options-select");
            var value = $('option:selected', select);
            
            var id = value.val();
            if (id != "" && $("#device-config-option-"+id+"-row").val() === undefined) {
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
                if ($("option", select).length > 1) {
                    select.prop("disabled", false).val('');
                    $("#device-config-options-add").prop("disabled", false);
                }
                else {
                    select.prop("disabled", true).val('');
                    $("#device-config-options-add").prop("disabled", true);
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

    'parseOptions': function(scan) {
        var options = {};
        for (var i = 0; i < device_dialog.deviceOptions.length; i++) {
            var option = device_dialog.deviceOptions[i];
            var input = $('#device-config-option-'+option.id);
            var value = null;
            
            if (input.val() != undefined) {
                var type = option.type;
                if (type === 'text') {
                    value = input.val();
                }
                else if (type === 'selection') {
                    value = $('#device-config-option-'+option.id+' option:selected').val();
                }
                else if (type === 'switch') {
                    value = input.is(':checked');
                }
            }
            if (value !== null && value !== "") {
            	if (!isNaN(value)) {
            		value = Number(value);
            	}
                options[option.id] = value;
            }
            else if (option.mandatory && (scan && (typeof option.scan === 'undefined' || !option.scan))) {
                return null;
            }
        }
        return options;
    },

    'loadScan':function(options) {
        this.scanUpdater = null;
        this.scanDevices = [];
        
        this.drawScan();
        
        device.scanStart(device_dialog.deviceType, options, function(result) {
            $('#device-scan-loader').hide();
            
            device_dialog.scanProgress(result);
        });

        $('#device-scan-results').on('click', '.device-scan-row', function() {
            var row = $(this).data('row');
            var device = {};
            if (device_dialog.device != null) {
            	device = device_dialog.device
            }
            device['options'] = device_dialog.scanDevices[row].options;
            device['scanned'] = true;
            
            $("#device-scan-modal").modal('hide');
            device_dialog.device = device;
            device_dialog.drawConfig();
        });
    },

    'drawScan':function() {
        $("#device-scan-modal").modal('show');
        
        var template = device_dialog.templates[device_dialog.deviceType];
        $('#device-scan-description').html("<b>"+template.group+"</b>: "+template.name+"<br>" +
                "<em style='color:#888'>"+template.description+"</em>");
        
        $('#device-scan-progress').html("<div id='device-scan-progress-bar' class='bar' style='width:100%;'></div>")
        		.removeClass('progress-info progress-success progress-warning progress-danger').addClass('progress-default');;
        $('#device-scan-results').empty().hide();
        $('#device-scan-results-none').hide();
        
        device_dialog.adjustScanModal();
    },

    'drawScanProgress':function(progress) {
        device_dialog.drawScanProgressBar(progress);
        
        if (!progress.success) {
            alert(progress.message);
            return;
        }
        
        device_dialog.scanDevices = progress.devices;
        if (device_dialog.scanDevices.length > 0) {
            
            $('#device-scan-results').show();
            $('#device-scan-results-none').hide();
            
            var list = '';
            for (var i = 0; i < device_dialog.scanDevices.length; i++) {
                var name = device_dialog.scanDevices[i].name;
                var description = device_dialog.scanDevices[i].description;
                list += '<li class="device-scan-row" title="Add" data-row='+i+'>' +
                        name+(description.length>0 ? ": <em style='color:#888'>"+description+"</em>" : "") +
                    '</li>';
            }
            $('#device-scan-results').html(list);
        }
        else {
            $('#device-scan-results').hide();
            $('#device-scan-results-none').show();
        }
    },

    'drawScanProgressBar':function(progress) {
        var bar = $('#device-scan-progress');
        
        var value = 100;
        var type = 'danger';
        if (progress.success) {
            value = progress.info.progress;
            
            if (progress.info.interrupted) {
                value = 100;
                type = 'warning';
            }
            else if (progress.info.finished) {
                value = 100;
                type = 'success';
            }
            else if (value > 0) {
                // If the progress value equals zero, set it to 5%, so the user can see the bar already
                if (value == 0) {
                    value = 5;
                }
                type = 'info';
            }
            else {
                value = 100;
                type = 'default';
            }
        }
        
        if (bar.css('width') == $('#device-scan-progress-bar').css('width')) {
            bar.html("<div id='device-scan-progress-bar' class='bar' style='width:"+value+"%;'></div>");
        }
        else {
            $('#device-scan-progress-bar').css('width', value+'%');
        }
        
        if (value < 100 || type == 'default') {
            bar.addClass('active');
        }
        else {
            bar.removeClass('active');
        }
        bar.removeClass('progress-default progress-info progress-success progress-warning progress-danger');
        bar.addClass('progress-'+type);
        bar.show();
    },

    'scanProgress':function(progress) {
        if (device_dialog.scanUpdater != null) {
            clearTimeout(device_dialog.scanUpdater);
            device_dialog.scanUpdater = null;
        }
        device_dialog.drawScanProgress(progress);
        
        // Continue to schedule scan progress requests every second until the scan info signals completion
        if (progress.success && !progress.info.finished && !progress.info.interrupted) {
            
            device_dialog.scanUpdater = setTimeout(function() {
            	device.scanProgress(device_dialog.deviceType, device_dialog.scanProgress);
            }, 1000);
        }
    },

    'adjustScanModal':function() {
        if ($("#device-scan-modal").length) {
            var h = $(window).height() - $("#device-scan-modal").position().top - 180;
            $("#device-scan-body").height(h);
        }
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
            $('#wrap').trigger("device-delete");
            
            $('#device-delete-modal').modal('hide');
        });
    }
}
