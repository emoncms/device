<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function device_controller()
{
    global $mysqli, $redis, $session, $route, $device, $settings;

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
        else if ($route->action == "thing") {
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

                if (isset($settings["device"]["enable_UDP_broadcast"]) && $settings["device"]["enable_UDP_broadcast"]) {
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
        else if ($route->action == "template" || $route->action == "scan") {
            $result = device_template($device);
        }
        else if ($route->action == "thing" || $route->action == "item") {
            $result = device_things($device);
        }
        else {
            $deviceid = (int) get('id');
            if ($device->exist($deviceid)) // if the device exists
            {
                $deviceget = $device->get($deviceid);
                if (isset($session['write']) && $session['write'] && $session['userid']>0 && $deviceget['userid']==$session['userid']) {
                    if ($route->action == "get") $result = $deviceget;
                    else if ($route->action == 'options') $result = $device->get_options($deviceget);
                    else if ($route->action == 'configs') $result = $device->get_configs($deviceget);
                    else if ($route->action == 'set') $result = $device->set_fields($deviceid, get('fields'));
                    else if ($route->action == 'init') $result = $device->init($deviceid, prop('template'));
                    else if ($route->action == "delete") $result = $device->delete($deviceid);
                    else if ($route->action == "newdevicekey") $result = $device->set_new_devicekey($deviceid);
                }
            }
            else {
                $result = array('success'=>false, 'message'=>'Device does not exist');
            }
        }
    }
    if ($route->action == "clean" && $session['write']) {
        $route->format = 'text';
        $active = 0; if (isset($_GET['active'])) $active = (int) $_GET['active'];
        $dryrun = 0; if (isset($_GET['dryrun']) && $_GET['dryrun']==1) $dryrun = 1;
        return $device->clean($session['userid'],$active,$dryrun);
    }
    return array('content'=>$result);
}

function device_template($device)
{
    global $session, $route;
    try {
        if ($route->action == 'template') {
            if ($route->subaction == "listshort") {
                if ($session['userid']>0 && $session['read']) return $device->get_template_list_meta();
            }
            else if ($route->subaction == "list") {
                if ($session['userid']>0 && $session['read']) return $device->get_template_list();
            }
            else if ($route->subaction == "reload") {
                if ($session['userid']==1 || $session['admin']) return $device->reload_template_list();
            }
            else if ($route->subaction == "options") {
                if ($session['userid']>0 && $session['read']) return $device->get_template_options(get('type'));
            }
            else if ($route->subaction == "get") {
                if ($session['userid']>0 && $session['read']) return $device->get_template(get('type'));
            }
            else if (isset($_GET['id'])) {
                $deviceid = (int) get('id');
                if (!$device->exist($deviceid)) {
                    return array('success'=>false, 'message'=>'Device does not exist');
                }
                $deviceget = $device->get($deviceid);
                if (isset($session['write']) && $session['write'] && $session['userid']>0 && $deviceget['userid']==$session['userid']) {
                    if (isset($_GET['type'])) {
                        $type = get('type');
                        $deviceget['type'] = $type;
                        $device->set_fields($deviceid, json_encode(array("type"=>$type)));
                    }
                    if ($route->subaction == 'prepare') {
                        return $device->prepare_template($deviceget);
                    }
                    else if ($route->subaction == 'init') {
                        return $device->init_template($deviceget, prop('template'));
                    }
                }
            }
        }
        else if ($route->action == "scan" && $session['userid']>0 && $session['write']) {
            $type = get('type');
            $template = $device->get_template_class($type);
            
            if ($route->subaction == "start") {
                return $template->scan_start($session['userid'],$type,get("configs"));
            }
            else if ($route->subaction == "progress") {
                return $template->scan_progress($session['userid'],$type);
            }
            else if ($route->subaction == "cancel") {
                return $template->scan_cancel($session['userid'],$type);
            }
        }
    } catch (DeviceException $e) {
        return $e->getResult();
    }
    return false;
}

function device_things($device)
{
    global $session, $route;
    try {
        if ($route->action == 'thing' && 
                $route->subaction == "list" && $session['userid']>0) {
            return $device->get_thing_list($session['userid']);
        }
        else if (isset($_GET['id'])) {
            $deviceid = (int) get('id');
            if (!$device->exist($deviceid)) {
                return array('success'=>false, 'message'=>'Thing does not exist');
            }
            $deviceget = $device->get($deviceid);
            if (isset($session['write']) && $session['write'] && $session['userid']>0 && $deviceget['userid']==$session['userid']) {
                $thingget = $device->get_thing($deviceget);
                if ($route->action == "thing") {
                    if ($route->subaction == "get") return $thingget;
                }
                else if ($route->action == "item") {
                    $thing = $device->get_thing_class($deviceget['type']);
                    if ($route->subaction == "list")  return $thing->get_item_list($thingget);
                    else if ($route->subaction == "get")  return $thing->get_item($thingget, get('itemid'));
                    else if ($route->subaction == "on") return $thing->set_item_on($thingget, get('itemid'));
                    else if ($route->subaction == "off") return $thing->set_item_off($thingget, get('itemid'));
                    else if ($route->subaction == "toggle") return $thing->toggle_item_value($thingget, get('itemid'));
                    else if ($route->subaction == "increase") return $thing->increase_item_value($thingget, get('itemid'));
                    else if ($route->subaction == "decrease") return $thing->decrease_item_value($thingget, get('itemid'));
                    else if ($route->subaction == "percent")  return $thing->set_item_percent($thingget, get('itemid'), get('value'));
                    else if ($route->subaction == "set")  return $thing->set_item_value($thingget, get('itemid'), get('value'));
                }
            }
        }
    } catch (DeviceException $e) {
        return $e->getResult();
    }
    return false;
}
