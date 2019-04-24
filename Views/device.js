var device = {

    'create':function(nodeid, name, description, type, options, callback) {
        return device.request(callback, "device/create.json", "nodeid="+nodeid+"&name="+name+"&description="+description+"&type="+type+
                "&options="+JSON.stringify(options));
    },

    'list':function(callback) {
        return device.request(callback, "device/list.json");
    },

    'get':function(id, callback) {
        return device.request(callback, "device/get.json", "id="+id);
    },

    'options':function(type, callback) {
        return device.request(callback, "device/template/options.json", "type="+type);
    },

    'newDeviceKey':function(id, callback) {
        return device.request(callback, "device/newdevicekey.json", "id="+id);
    },

    'set':function(id, fields, callback) {
        return device.request(callback, "device/set.json", "id="+id+"&fields="+JSON.stringify(fields));
    },

    'remove':function(id, callback) {
        return device.request(callback, "device/delete.json", "id="+id);
    },

    'scanStart':function(type, options, callback) {
        return device.request(callback, "device/scan/start.json", "type="+type+"&options="+JSON.stringify(options));
    },

    'scanProgress':function(type, callback) {
        return device.request(callback, "device/scan/progress.json", "type="+type);
    },

    'scanCancel':function(type, callback) {
        return device.request(callback, "device/scan/cancel.json", "type="+type);
    },

    'reload':function(callback) {
        return device.request(callback, "device/template/reload.json");
    },

    'prepare':function(id, callback) {
        return device.request(callback, "device/template/prepare.json", "id="+id);
    },

    'init':function(id, template, callback) {
        return $.ajax({
            'url': path+"device/init.json?id="+id,
            'data': "template="+JSON.stringify(template),
            'dataType': 'json',
            'type': 'POST',
            'async': true,
            'success': callback
        });
    },

    'request':function(callback, action, data) {
        var request = {
            'url': path+action,
            'dataType': 'json',
            'async': true,
            'success': callback,
            'error': function(error) {
                var message = "Failed to request server";
                if (typeof error !== 'undefined') {
                    message += ": ";
                    
                    if (typeof error.responseText !== 'undefined') {
                        message += error.responseText;
                    }
                    else if (typeof error !== 'string') {
                        message += JSON.stringify(error);
                    }
                    else {
                        message += error;
                    }
                }
                console.warn(message);
                if (typeof callback === 'function') {
                    callback({
                        'success': false,
                        'message': message
                    });
                }
//                return device.request(callback, action, data);
            }
        }
        if (typeof data !== 'undefined') {
            request['data'] = data;
        }
        return $.ajax(request);
    }
}
