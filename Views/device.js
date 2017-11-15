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

    'remove':function(id)
    {
        var result = {};
        $.ajax({ url: path+"device/delete.json", data: "id="+id, async: false, success: function(data) {result = data;} });
        return result;
    },

    'create':function(nodeid, name, description, type)
    {
        var result = {};
        $.ajax({ url: path+"device/create.json", data: "nodeid="+nodeid+"&name="+name+"&description="+description+"&type="+type, async: false, success: function(data) {result = data;} });
        return result;
    },

    'listTemplates':function()
    {
        var result = {};
        $.ajax({ url: path+"device/template/list.json", dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'initTemplate':function(id, options)
    {
        var result = {};
        $.ajax({ url: path+"device/template/init.json", data: "id="+id+"&options="+JSON.stringify(options), dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'listControls':function()
    {
        var result = {};
        $.ajax({ url: path+"device/control/list.json", dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'getControl':function(id)
    {
        var result = {};
        $.ajax({ url: path+"device/control/get.json", data: "id="+id, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setControlOn':function(id, itemid)
    {
        var result = {};
        $.ajax({ url: path+"device/control/on.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setControlOff':function(id, itemid)
    {
        var result = {};
        $.ajax({ url: path+"device/control/off.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'toggleControlValue':function(id, itemid)
    {
        var result = {};
        $.ajax({ url: path+"device/control/toggle.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'increaseControlValue':function(id, itemid)
    {
        var result = {};
        $.ajax({ url: path+"device/control/increase.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'decreaseControlValue':function(id, itemid)
    {
        var result = {};
        $.ajax({ url: path+"device/control/decrease.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    },

    'setControlPercent':function(id, itemid)
    {
        var result = {};
        $.ajax({ url: path+"device/control/percent.json", data: "id="+id+"&itemid="+itemid, dataType: 'json', async: false, success: function(data) {result = data;} });
        return result;
    }
}
