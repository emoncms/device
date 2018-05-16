<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

function device_controller()
{
    global $mysqli, $redis, $user, $session, $route, $device;

    $result = false;

    if (!$device) {
        require_once "Modules/device/device_model.php";
        $device = new Device($mysqli,$redis);
    }

    if ($route->format == 'html')
    {
        if ($route->action == "view" && $session['write']) {
            $templates = $device->get_template_list($session['userid']);
            $result = view("Modules/device/Views/device_view.php", array('templates'=>$templates));
        }
        else if ($route->action == 'api') $result = view("Modules/device/Views/device_api.php", array());
        else if ($route->action == "thing" && $session['write']) {
            if ($route->subaction == "view") {
                $templates = $device->get_template_list($session['userid']);
                $result = view("Modules/device/Views/thing_view.php", array('templates'=>$templates));
            }
            else if ($route->subaction == 'api') $result = view("Modules/device/Views/thing_api.php", array());
        }
    }

    if ($route->format == 'json')
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
        if ($redis && $route->action == "auth") {
            // 1. Register request for authentication details, or provide if allowed
            if ($route->subaction=="request") {
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $ip_parts = explode(".",$ip);
                for ($i=0; $i<count($ip_parts); $i++) $ip_parts[$i] = (int) $ip_parts[$i];
                $ip = implode(".",$ip_parts);
                
                $allow_ip = $redis->get("device_auth_allow");
                // Only show authentication details to allowed ip address
                if ($allow_ip==$ip) {
                    $redis->del("device_auth_allow");
                    global $mqtt_server;
                    $result = $mqtt_server["user"].":".$mqtt_server["password"].":".$mqtt_server["basetopic"];
                } else {
                    $redis->set("device_auth",json_encode(array("ip"=>$ip)));
                    $result = "request registered";
                }
                $route->format = "text";
            }
            // 2. User checks for device waiting for authentication
            else if ($route->subaction=="check" && $session['write']) {
                if ($device_auth = $redis->get("device_auth")) {
                    $result = json_decode($device_auth);
                } else {
                    $result = "no devices";
                }
            }
            // 3. User allows device to receive authentication details
            else if ($route->subaction=="allow" && $session['write']) {
                 $ip = get("ip");

                 $ip_parts = explode(".",$ip);
                 for ($i=0; $i<count($ip_parts); $i++) $ip_parts[$i] = (int) $ip_parts[$i];
                 $ip = implode(".",$ip_parts);
                 
                 $redis->set("device_auth_allow",$ip);    // Temporary availability of auth for device ip address
                 $redis->expire("device_auth_allow",60);  // Expire after 60 seconds
                 $redis->del("device_auth");
                 $result = true;
            }
        }
        else if ($route->action == 'list') {
            if ($session['userid']>0 && $session['write']) $result = $device->get_list($session['userid']);
        }
        else if ($route->action == "create") {
            if ($session['userid']>0 && $session['write']) $result = $device->create($session['userid'],get("nodeid"),get("name"),get("description"),get("type"),get("options"));
        }
        // Used in conjunction with input name describe to auto create device
        else if ($route->action == "autocreate") {
            if ($session['userid']>0 && $session['write']) $result = $device->autocreate($session['userid'],get('nodeid'),get('type'));
        }
        else if ($route->action == "template" && $route->subaction != "prepare" && $route->subaction != "init") {
            if ($route->subaction == "list") {
                if ($session['userid']>0 && $session['write']) $result = $device->get_template_list_full($session['userid']);
            }
            else if ($route->subaction == "listshort") {
                if ($session['userid']>0 && $session['write']) $result = $device->get_template_list_meta($session['userid']);
            }
            else if ($route->subaction == "get") {
                if ($session['userid']>0 && $session['write']) $result = $device->get_template($session['userid'], get('type'));
            }
            else if ($route->subaction == "options") {
                if ($session['userid']>0 && $session['write']) $result = $device->get_template_options($session['userid'], get('type'));
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
                    else if ($route->action == 'init') $result = $device->init($deviceid, get('template'));
                    else if ($route->action == "delete") $result = $device->delete($deviceid);
                    else if ($route->action == "setnewdevicekey") $result = $device->set_new_devicekey($deviceid);
                    else if ($route->action == 'template') {
                        if (isset($_GET['type'])) {
                            $device->set_fields($deviceid, json_encode(array("type"=>$_GET['type'])));
                        }
                        if ($route->subaction == 'prepare') $result = $device->prepare_template($deviceid);
                        else if ($route->subaction == 'init') $result = $device->init_template($deviceget, $_POST['template']);
                    }
                    else if ($route->action == "thing") {
                        if ($route->subaction == "get")  $result = $device->get_thing($deviceid);
                        else if ($route->subaction == 'init') {
                            if (isset($_GET['type'])) {
                                $device->set_fields($deviceid, json_encode(array("type"=>$_GET['type'])));
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
