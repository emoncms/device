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
    'generatekey':function(id) {
        var result = {};
        $.ajax({ url: path+"device/generatekey.json", async: false, success: function(data) {result = data;} });
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

    'create':function(nodeid, name, description, type, devicekey) {
        var result = {};
        $.ajax({ url: path+"device/create.json", data: "nodeid="+nodeid+"&name="+name+"&description="+description+"&type="+type+"&dkey="+devicekey, async: false, success: function(data) {result = data;} });
        return result;
    },

    'init':function(id, template) {
        var result = {};
        $.ajax({ url: path+"device/init.json?id="+id, type: 'POST', data: "template="+JSON.stringify(template), dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'initCustom':function(id, template) {
        var result = {};
        $.ajax({ url: path+"device/template/init_custom.json?id="+id, type: 'POST', data: "template="+JSON.stringify(template), dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'prepareTemplate':function(id) {
        var result = {};
        $.ajax({ url: path+"device/template/prepare.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'prepareCustomTemplate':function(id, template) {
        var result = {};
        $.ajax({ url: path+"device/template/prepare_custom.json?id="+id, type: 'POST', data: "template="+JSON.stringify(template), dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'generateTemplate':function(id) {
        var result = {};
        $.ajax({ url: path+"device/template/generate.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    }
}
