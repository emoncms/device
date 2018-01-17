var device = {
    'list':function() {
        var result = {};
        $.ajax({ url: path+"device/list.json", dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'get':function(id) {
        var result = {};
        $.ajax({ url: path+"device/get.json", data: "id="+id, async: false, success: function(data) {result = data;} });
        return result;
    },

    'set':function(id, fields) {
        var result = {};
        $.ajax({ url: path+"device/set.json", data: "id="+id+"&fields="+JSON.stringify(fields), async: false, success: function(data) {result = data;} });
        return result;
    },

    'setNewDeviceKey':function(id) {
        var result = {};
        $.ajax({ url: path+"device/setnewdevicekey.json", data: "id="+id, async: false, success: function(data) {result = data;} });
        return result;
    },

    'remove':function(id) {
        var result = {};
        $.ajax({ url: path+"device/delete.json", data: "id="+id, async: false, success: function(data) {result = data;} });
        return result;
    },

    'create':function(nodeid, name, description, type, options) {
        var result = {};
        $.ajax({ url: path+"device/create.json", data: "nodeid="+nodeid+"&name="+name+"&description="+description+"&type="+type+"&options="+JSON.stringify(options), async: false, success: function(data) {result = data;} });
        return result;
    },

    'init':function(id, template) {
        var result = {};
        $.ajax({ url: path+"device/init.json?id="+id, type: 'POST', data: "template="+JSON.stringify(template), dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'prepareTemplate':function(id) {
        var result = {};
        $.ajax({ url: path+"device/template/prepare.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'getTemplateOptions':function(type, callback) {
        var async = false;
        if (typeof callback == 'function') {
            async = true;
        }
        
        var result = false;
        var promise = $.ajax({
            url: path+"device/template/options.json",
            dataType: 'json',
            async: async,
            data: "type="+type,
            success(data) {
                if (async) {
                    callback(data);
                }
                else {
                    result = data;
                }
            }
        });
        
        if (async) {
            return promise;
        }
        return result;
    },

    'listThings':function(callback) {
        var async = false;
        if (typeof callback == 'function') {
            async = true;
        }
        
        var result = false;
        var promise = $.ajax({
            url: path+"device/thing/list.json",
            dataType: 'json',
            async: async,
            success(data) {
                if (async) {
                    callback(data);
                }
                else {
                    result = data;
                }
            }
        });
        
        if (async) {
            return promise;
        }
        return result;
    },

    'getThing':function(id) {
        var result = {};
        $.ajax({ url: path+"device/thing/get.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setItemOn':function(id, itemid, callback) {
        return device.setItemAsync('on', id, itemid, callback);
    },

    'setItemOff':function(id, itemid, callback) {
        return device.setItemAsync('off', id, itemid, callback);
    },

    'toggleItemValue':function(id, itemid, callback) {
        return device.setItemAsync('toggle', id, itemid, callback);
    },

    'increaseItemValue':function(id, itemid, callback) {
        return device.setItemAsync('increase', id, itemid, callback);
    },

    'decreaseItemValue':function(id, itemid, callback) {
        return device.setItemAsync('decrease', id, itemid, callback);
    },

    'setItemPercent':function(id, itemid, value, callback) {
        return device.setItemValueAsync('percent', id, itemid, value, callback);
    },

    'setItemValue':function(id, itemid, value, callback) {
        return device.setItemValueAsync('set', id, itemid, value, callback);
    },

    'setItemValueAsync':function(action, id, itemid, value, callback) {
        var async = false;
        if (typeof callback == 'function') {
            async = true;
        }
        
        var result = false;
        var promise = $.ajax({
            url: path+"device/item/"+action+".json",
            dataType: 'json',
            async: async,
            data: "id="+id+"&itemid="+itemid+"&value="+value,
            success(data) {
                if (async) {
                    callback(data);
                }
                else {
                    result = data;
                }
            }
        });
        
        if (async) {
            return promise;
        }
        return result;
    },

    'setItemAsync':function(action, id, itemid, callback) {
        var async = false;
        if (typeof callback == 'function') {
            async = true;
        }
        
        var result = false;
        var promise = $.ajax({
            url: path+"device/item/"+action+".json",
            dataType: 'json',
            async: async,
            data: "id="+id+"&itemid="+itemid,
            success(data) {
                if (async) {
                    callback(data);
                }
                else {
                    result = data;
                }
            }
        });
        
        if (async) {
            return promise;
        }
        return result;
    }
}
