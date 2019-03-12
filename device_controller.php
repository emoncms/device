<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function device_controller()
{
    global $mysqli, $redis, $session, $route, $device, $enable_UDP_broadcast;

    $result = false;

    require_once "Modules/device/device_model.php";
    $device = new Device($mysqli,$redis);

    if ($route->format == 'html')
    {
        if ($route->action == "view" && $session['write']) {
            $templates = $device->get_template_list_meta();
            $result = view("Modules/device/Views/device_view.php", 
                array('templates'=>$templates));
        }
        else if ($route->action == 'api') {
            $result = view("Modules/device/Views/device_api.php", array());
        }
        else if ($route->action == "thing" && $session['write']) {
            if ($route->subaction == "view") {
                $templates = $device->get_template_list();
                $result = view("Modules/device/Views/thing_view.php", 
                    array('templates'=>$templates));
            }
            else if ($route->subaction == 'api') {
                $result = view("Modules/device/Views/thing_api.php", array());
            }
        }
    }
    else if ($route->format == 'json')
    {
        // ---------------------------------------------------------------
        // Method for sharing authentication details with a node
        // that does not require copying and pasting passwords and apikeys
        // 1. device requests authentication - reply "request registered"
        // 2. notification asks user whether to allow or deny device
        // 3. user clicks on allow
        // 4. device makes follow up request for authentication
        //    - reply authentication details
        // ---------------------------------------------------------------
        if ($route->action == "authcheck") { $route->action = "auth"; $route->subaction = "check"; } 
        if ($route->action == "authallow") { $route->action = "auth"; $route->subaction = "allow"; }         
        
        if ($route->action == "auth") {
            if ($route->subaction=="request") {
                // 1. Register request for authentication details, or provide if allowed
                $result = $device->request_auth($_SERVER['REMOTE_ADDR']);
                if (isset($result['success'])) {
                    $result = $result['message'];
                }
                $route->format = "text";
            }
            else if ($route->subaction=="check" && $session['read']) {
                // 2. User checks for device waiting for authentication
                $result = $device->get_auth_request();
                
                if (isset($enable_UDP_broadcast) && $enable_UDP_broadcast) {
                    $port = 5005;
                    $broadcast_string = "emonpi.local";
                    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                    socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
                    socket_sendto($sock, $broadcast_string, strlen($broadcast_string), 0, '255.255.255.255', $port);
                }
            }
            else if ($route->subaction=="allow" && $session['write']) {
                // 3. User allows device to receive authentication details
                $result = $device->allow_auth_request(get("ip"));
            }
        }
        else if ($route->action == 'list') {
            if ($session['userid']>0 && $session['read']) $result = $device->get_list($session['userid']);
        }
        else if ($route->action == "create") {
            if ($session['userid']>0 && $session['write']) $result = $device->create($session['userid'],get("nodeid"),get("name"),get("description"),get("type"),get("options"));
        }
        // Used in conjunction with input name describe to auto create device
        else if ($route->action == "autocreate") {
            if ($session['userid']>0 && $session['write']) $result = $device->autocreate($session['userid'],get('nodeid'),get('type'));
        }
        else if ($route->action == "template" && $route->subaction != "prepare" && $route->subaction != "init") {
            if ($route->subaction == "listshort") {
                if ($session['userid']>0 && $session['read']) $result = $device->get_template_list_meta();
            }
            else if ($route->subaction == "list") {
                if ($session['userid']>0 && $session['read']) $result = $device->get_template_list();
            }
            else if ($route->subaction == "reload") {
                if ($session['userid']==1 || $session['admin']) $result = $device->reload_template_list();
            }
            else if ($route->subaction == "get") {
                if ($session['userid']>0 && $session['read']) $result = $device->get_template(get('type'));
            }
            else if ($route->subaction == "options") {
                if ($session['userid']>0 && $session['read']) $result = $device->get_template_options(get('type'));
            }
        }
        else if ($route->action == "scan") {
            if ($route->subaction == "start") {
                if ($session['userid']>0 && $session['write']) $result = $device->scan_start($session['userid'],get("type"),get("options"));
            }
            else if ($route->subaction == "progress") {
                if ($session['userid']>0 && $session['write']) $result = $device->scan_progress($session['userid'],get("type"));
            }
            else if ($route->subaction == "cancel") {
                if ($session['userid']>0 && $session['write']) $result = $device->scan_cancel($session['userid'],get("type"));
            }
        }
        else if ($route->action == "thing" && 
                $route->subaction == "list") {
            if ($session['userid']>0 && $session['write']) $result = $device->get_thing_list($session['userid']);
        }
        else {
            $deviceid = (int) get('id');
            if ($device->exist($deviceid)) // if the feed exists
            {
                $deviceget = $device->get($deviceid);
                if (isset($session['write']) && $session['write'] && $session['userid']>0 && $deviceget['userid']==$session['userid']) {
                    if ($route->action == "get") $result = $deviceget;
                    else if ($route->action == 'set') $result = $device->set_fields($deviceid, get('fields'));
                    else if ($route->action == 'init') $result = $device->init($deviceid, prop('template'));
                    else if ($route->action == "delete") $result = $device->delete($deviceid);
                    else if ($route->action == "newdevicekey") $result = $device->set_new_devicekey($deviceid);
                    else if ($route->action == 'template') {
                        if (isset($_GET['type'])) {
                            $device->set_fields($deviceid, json_encode(array("type"=>get('type'))));
                        }
                        if ($route->subaction == 'prepare') $result = $device->prepare_template($deviceid);
                        else if ($route->subaction == 'init') $result = $device->init_template($deviceget, prop('template'));
                    }
                    else if ($route->action == "thing") {
                        if ($route->subaction == "get")  $result = $device->get_thing($deviceid);
                        else if ($route->subaction == 'init') {
                            if (isset($_GET['type'])) {
                                $device->set_fields($deviceid, json_encode(array("type"=>get('type'))));
                            }
                            $result = $device->init_thing($deviceget);
                        }
                    }
                    else if ($route->action == "item") {
                        if ($route->subaction == "get")  $result = $device->get_item($deviceid, get('itemid'));
                        else if ($route->subaction == "on") $result = $device->set_item_on($deviceid, get('itemid'));
                        else if ($route->subaction == "off") $result = $device->set_item_off($deviceid, get('itemid'));
                        else if ($route->subaction == "toggle") $result = $device->toggle_item_value($deviceid, get('itemid'));
                        else if ($route->subaction == "increase") $result = $device->increase_item_value($deviceid, get('itemid'));
                        else if ($route->subaction == "decrease") $result = $device->decrease_item_value($deviceid, get('itemid'));
                        else if ($route->subaction == "percent")  $result = $device->set_item_percent($deviceid, get('itemid'), get('value'));
                        else if ($route->subaction == "set")  $result = $device->set_item_value($deviceid, get('itemid'), get('value'));
                    }
                }
            }
            else {
                $result = array('success'=>false, 'message'=>'Device does not exist');
            }
        }
    }

    return array('content'=>$result);
}
