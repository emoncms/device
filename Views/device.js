var device = {
    'list':function()
    {
        var result = {};
        $.ajax({ url: path+"device/list.json", dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'get':function(id)
    {
        var result = {};
        $.ajax({ url: path+"device/get.json", data: "id="+id, async: false, success: function(data) {result = data;} });
        return result;
    },

    'set':function(id, fields)
    {
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

    'create':function(nodeid, name, description, type) {
        var result = {};
        $.ajax({ url: path+"device/create.json", data: "nodeid="+nodeid+"&name="+name+"&description="+description+"&type="+type, async: false, success: function(data) {result = data;} });
        return result;
    },

    'init':function(id, template, options) {
        var result = {};
        $.ajax({ url: path+"device/init.json", data: "id="+id+"&template="+JSON.stringify(template)+"&options="+JSON.stringify(options), dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'prepareTemplate':function(id) {
        var result = {};
        $.ajax({ url: path+"device/template/prepare.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'listThings':function() {
        var result = {};
        $.ajax({ url: path+"device/thing/list.json", dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'getThing':function(id) {
        var result = {};
        $.ajax({ url: path+"device/thing/get.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setItemOn':function(id, itemid) {
        var result = {};
        $.ajax({ url: path+"device/item/on.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setItemOff':function(id, itemid) {
        var result = {};
        $.ajax({ url: path+"device/item/off.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'toggleItemValue':function(id, itemid) {
        var result = {};
        $.ajax({ url: path+"device/item/toggle.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'increaseItemValue':function(id, itemid) {
        var result = {};
        $.ajax({ url: path+"device/item/increase.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'decreaseItemValue':function(id, itemid) {
        var result = {};
        $.ajax({ url: path+"device/item/decrease.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setItemPercent':function(id, itemid) {
        var result = {};
        $.ajax({ url: path+"device/item/percent.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    }
}
