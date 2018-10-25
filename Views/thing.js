var thing = {

    'list':function(callback) {
        return thing.request(callback, "device/thing/list.json");
    },

    'get':function(id, callback) {
        return thing.request(callback, "device/thing/get.json", "id="+id);
    },

    'setItemOn':function(id, itemid, callback) {
        return thing.request(callback, "device/item/on.json", "id="+id+"&itemid="+itemid);
    },

    'setItemOff':function(id, itemid, callback) {
        return thing.request(callback, "device/item/off.json", "id="+id+"&itemid="+itemid);
    },

    'toggleItemValue':function(id, itemid, callback) {
        return thing.request(callback, "device/item/toggle.json", "id="+id+"&itemid="+itemid);
    },

    'increaseItemValue':function(id, itemid, callback) {
        return thing.request(callback, "device/item/increase.json", "id="+id+"&itemid="+itemid);
    },

    'decreaseItemValue':function(id, itemid, callback) {
        return thing.request(callback, "device/item/decrease.json", "id="+id+"&itemid="+itemid);
    },

    'setItemPercent':function(id, itemid, value, callback) {
        return thing.request(callback, "device/item/percent.json", "id="+id+"&itemid="+itemid+"&value="+value);
    },

    'setItemValue':function(id, itemid, value, callback) {
        return thing.request(callback, "device/item/set.json", "id="+id+"&itemid="+itemid+"&value="+value);
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
//	        	return thing.request(callback, action, data);
	        }
	    }
		if (typeof data !== 'undefined') {
			request['data'] = data;
		}
	    return $.ajax(request);
    }
}
