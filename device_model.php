<?php
/*
 Released under the GNU Affero General Public License.
 See COPYRIGHT.txt and LICENSE.txt.
 
 Device module contributed by Nuno Chaveiro nchaveiro(at)gmail.com 2015
 ---------------------------------------------------------------------
 Sponsored by http://archimetrics.co.uk/
 */

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class Device
{
    const TEMPLATE = 'template';
    const THING = 'thing';
    const SCAN = 'scan';

    public $mysqli;
    public $redis;
    private $log;

    private $templates;
    private $things;

    public function __construct($mysqli, $redis) {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->templates = array();
        $this->things = array();
        $this->log = new EmonLogger(__FILE__);
    }

    public function devicekey_session($devicekey) {
        // 1. Only allow alphanumeric characters
        // if (!ctype_alnum($devicekey)) return array();
        
        // 2. Only allow 32 character length
        if (strlen($devicekey)!=32) return array();
        
        $session = array();
        $time = time();
        
        //----------------------------------------------------
        // Check for devicekey login
        //----------------------------------------------------
        if($this->redis && $this->redis->exists("device:key:$devicekey")) {
            $session['userid'] = $this->redis->get("device:key:$devicekey:user");
            $session['read'] = 0;
            $session['write'] = 1;
            $session['admin'] = 0;
            $session['lang'] = "en"; // API access is always in english
            $session['username'] = "API";
            $session['deviceid'] = $this->redis->get("device:key:$devicekey:device");
            $session['nodeid'] = $this->redis->get("device:key:$devicekey:node");
            $this->redis->hMset("device:lastvalue:".$session['device'], array('time' => $time));
        }
        else {
            $stmt = $this->mysqli->prepare("SELECT id, userid, nodeid FROM device WHERE devicekey=?");
            $stmt->bind_param("s",$devicekey);
            $stmt->execute();
            $stmt->bind_result($id,$userid,$nodeid);
            $result = $stmt->fetch();
            $stmt->close();
            
            if ($result && $id>0) {
                $session['userid'] = $userid;
                $session['read'] = 0;
                $session['write'] = 1;
                $session['admin'] = 0;
                $session['lang'] = "en"; // API access is always in english
                $session['username'] = "API";
                $session['deviceid'] = $id;
                $session['nodeid'] = $nodeid;
                    
                if ($this->redis) {
                    $this->redis->set("device:key:$devicekey:user",$userid);
                    $this->redis->set("device:key:$devicekey:device",$id);
                    $this->redis->set("device:key:$devicekey:node",$nodeid);
                    $this->redis->hMset("device:lastvalue:$id", array('time' => $time));
                } else {
                    //$time = date("Y-n-j H:i:s", $time);
                    $this->mysqli->query("UPDATE device SET time='$time' WHERE id = '$id");
                }
            }
        }
        
        return $session;
    }

    public function exist($id) {
        static $device_exists_cache = array(); // Array to hold the cache
        if (isset($device_exists_cache[$id])) {
            $device_exist = $device_exists_cache[$id]; // Retrieve from static cache
        }
        else {
            $device_exist = false;
            if ($this->redis) {
                if (!$this->redis->exists("device:$id")) {
                    if ($this->load_device_to_redis($id)) {
                        $device_exist = true;
                    }
                }
                else {
                    $device_exist = true;
                }
            }
            else {
                $id = (int) $id;
                $result = $this->mysqli->query("SELECT id FROM device WHERE id = '$id'");
                if ($result->num_rows > 0) $device_exist = true;
            }
            $device_exists_cache[$id] = $device_exist; // Cache it
        }
        return $device_exist;
    }

    public function exists_nodeid($userid, $nodeid) {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{N}\p{L}\-\_\.\s]/u', '', $nodeid);

        $stmt = $this->mysqli->prepare("SELECT id FROM device WHERE userid=? AND nodeid=?");
        $stmt->bind_param("is", $userid, $nodeid);
        $stmt->execute();
        $stmt->bind_result($id);
        $result = $stmt->fetch();
        $stmt->close();
        
        if ($result && $id > 0) return $id; else return false;
    }

    public function request_auth($ip) {
        if (!$this->redis) {
            return array("success"=>false, "message"=>"Unable to handle authentication requests without redis");
        }
        $ip_parts = explode(".", $ip);
        for ($i=0; $i<count($ip_parts); $i++) $ip_parts[$i] = (int) $ip_parts[$i];
        $ip = implode(".", $ip_parts);
        
        $allow_ip = $this->redis->get("device:auth:allow");
        // Only show authentication details to allowed ip address
        if ($allow_ip == $ip) {
            $this->redis->del("device:auth:allow");
            global $mqtt_server;
            return $mqtt_server["user"].":".$mqtt_server["password"].":".$mqtt_server["basetopic"];
        } else {
            $this->redis->set("device:auth:request", json_encode(array("ip"=>$ip)));
            return array("success"=>true, "message"=>"Authentication request registered for IP $ip");
        }
    }

    public function get_auth_request() {
        if (!$this->redis) {
            return array("success"=>false, "message"=>"Unable to handle authentication requests without redis");
        }
        if ($device_auth = $this->redis->get("device:auth:request")) {
            $device_auth = json_decode($device_auth);
            return array_merge(array("success"=>true, "ip"=>$device_auth->ip));
        } else {
            return array("success"=>true, "message"=>"No authentication request registered");
        }
    }

    public function allow_auth_request($ip) {
        if (!$this->redis) {
            return array("success"=>false, "message"=>"Unable to handle authentication requests without redis");
        }
        $ip_parts = explode(".", $ip);
        for ($i=0; $i<count($ip_parts); $i++) $ip_parts[$i] = (int) $ip_parts[$i];
        $ip = implode(".", $ip_parts);
        
        $this->redis->set("device:auth:allow", $ip);    // Temporary availability of auth for device ip address
        $this->redis->expire("device:auth:allow", 60);  // Expire after 60 seconds
        $this->redis->del("device:auth:request");
        
        return array("success"=>true, "message"=>"Authentication request allowed for IP $ip");
    }

    public function get($id) {
        $id = intval($id);
        if (!$this->exist($id)) {
            if (!$this->redis || !$this->load_device_to_redis($id)) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
        }
        
        if ($this->redis) {
            // Get from redis cache
            $device = (array) $this->redis->hGetAll("device:$id");
            // Verify, if the cached device contains the userid and options, to avoid 
            // compatibility issues with former versions where the userid was not cached.
            if (!isset($device['userid']) || !isset($device['options'])) {
                    $this->load_device_to_redis($id);
                    $device = $this->get($id);
                }
            $device['options'] = (array) json_decode($device['options']);
            $device['time'] = $this->redis->hget("device:lastvalue:".$id, 'time');
        }
        else {
            // Get from mysql db
            $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`options`,`devicekey`,`time` FROM device WHERE id = '$id'");
            $device = (array) $result->fetch_object();
            $device['options'] = (array) json_decode($device['options']);
        }
        return $device;
    }

    public function get_list($userid) {
        if ($this->redis) {
            return $this->get_list_redis($userid);
        } else {
            return $this->get_list_mysql($userid);
        }
    }

    private function get_list_redis($userid) {
        $userid = intval($userid);
        
        if (!$this->redis->exists("user:device:$userid")) {
            $this->load_list_to_redis($userid);
        }
        
        $devices = array();
        $deviceids = $this->redis->sMembers("user:device:$userid");
        foreach ($deviceids as $id) {
            $device = $this->redis->hGetAll("device:$id");
            // Verify, if the cached device contains the userid and options, to avoid
            // compatibility issues with former versions where the userid was not cached.
            if (!isset($device['userid']) || !isset($device['options'])) {
                $this->load_device_to_redis($id);
                $device = $this->get($id);
            }
            $device['options'] = (array) json_decode($device['options']);
            $device['time'] = $this->redis->hget("device:lastvalue:".$id, 'time');
            $devices[] = $device;
        }
        usort($devices, function($d1, $d2) {
            if($d1['nodeid'] == $d2['nodeid'])
                return strcmp($d1['name'], $d2['name']);
            return strcmp($d1['nodeid'], $d2['nodeid']);
        });
        return $devices;
    }

    private function get_list_mysql($userid) {
        $userid = intval($userid);
        
        $devices = array();
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`options`,`devicekey`,`time` FROM device WHERE userid = '$userid' ORDER BY nodeid, name asc");
        while ($device = (array) $result->fetch_object()) {
            $device['options'] = (array) json_decode($device['options']);
            $devices[] = $device;
        }
        return $devices;
    }

    private function load_list_to_redis($userid) {
        $userid = intval($userid);
        
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`options`,`devicekey` FROM device WHERE userid = '$userid'");
        while ($row = $result->fetch_object()) {
            $this->redis->sAdd("user:device:$userid", $row->id);
            $this->redis->hMSet("device:".$row->id, array(
                'id'=>$row->id,
                'userid'=>$row->userid,
                'nodeid'=>$row->nodeid,
                'name'=>$row->name,
                'description'=>$row->description,
                'type'=>$row->type,
                'options'=>$row->options,
                'devicekey'=>$row->devicekey
            ));
        }
    }

    private function load_device_to_redis($id) {
        $id = intval($id);
        
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`options`,`devicekey` FROM device WHERE id = '$id'");
        $row = $result->fetch_object();
        if (!$row) {
            $this->log->warn("Device model: Requested device does not exist for id=$id");
            return false;
        }
        $this->redis->hMSet("device:".$row->id, array(
            'id'=>$row->id,
            'userid'=>$row->userid,
            'nodeid'=>$row->nodeid,
            'name'=>$row->name,
            'description'=>$row->description,
            'type'=>$row->type,
            'options'=>$row->options,
            'devicekey'=>$row->devicekey
        ));
        return true;
    }

    public function autocreate($userid, $_nodeid, $_type) {
        $userid = intval($userid);
        
        $nodeid = preg_replace('/[^\p{N}\p{L}\-\_\.\s]/u', '', $nodeid);
        if ($_nodeid != $nodeid) return array("success"=>false, "message"=>"Invalid nodeid");
        $type = preg_replace('/[^\/\|\,\w\s-:]/','',$_type);
        if ($_type != $type) return array("success"=>false, "message"=>"Invalid type");
        
        $name = "$nodeid:$type";
        
        $deviceid = $this->exists_nodeid($userid, $nodeid);
        
        if (!$deviceid) {
            $this->log->info("Automatically create device for user=$userid, nodeid=$nodeid");
            $deviceid = $this->create($userid, $nodeid, null, null, null);
            if (!$deviceid) return array("success"=>false, "message"=>"Device creation failed");
        }
        
        $result = $this->set_fields($deviceid,json_encode(array("name"=>$name,"nodeid"=>$nodeid,"type"=>$type)));
        if ($result['success']==true) {
            return $this->init_template($deviceid);
        } else {
            return $result;
        }
    }

    public function create($userid, $nodeid, $name, $description, $type, $options) {
        $userid = intval($userid);
        
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\s]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : / and space characters");
        }
        if (!isset($name)) $name = '';
        if (!isset($description)) $description = '';
        if (isset($type) && $type != 'null') {
            $type = preg_replace('/[^\/\|\,\w\s-:]/','', $type);
        } else {
            $type = '';
        }
        
        if (isset($options)) {
            if (is_object($options)) $options = json_encode($type);
        }
        else {
            $options = '';
        }
        
        if (!$this->exists_nodeid($userid, $nodeid)) {
            // device key disabled by default
            $devicekey = ""; // md5(uniqid(mt_rand(), true));
            
            $stmt = $this->mysqli->prepare("INSERT INTO device (userid,nodeid,name,description,type,options,devicekey) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("issssss",$userid,$nodeid,$name,$description,$type,$options,$devicekey);
            $result = $stmt->execute();
            $stmt->close();
            if (!$result) return array('success'=>false, 'message'=>_("Error creating device"));
            
            $deviceid = $this->mysqli->insert_id;
            
            if ($deviceid > 0) {
                // Add the device to redis
                if ($this->redis) {
                    $device = array(
                        'id'=>$deviceid,
                        'userid'=>$userid,
                        'nodeid'=>$nodeid,
                        'name'=>$name,
                        'description'=>$description,
                        'type'=>$type,
                        'options'=>$options,
                        'devicekey'=>$devicekey
                    );
                    $this->redis->sAdd("user:device:$userid", $deviceid);
                    $this->redis->hMSet("device:".$deviceid, $device);
                    
                    $this->cache_thing($device);
                }
                return $deviceid;
            }
            return array('success'=>false, 'result'=>"SQL returned invalid insert feed id");
        }
        return array('success'=>false, 'message'=>'Device already exists');
    }

    public function delete($id) {
        $id = intval($id);
        if (!$this->exist($id)) {
            if (!$this->redis || !$this->load_device_to_redis($id)) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
        }
        
        $this->mysqli->query("DELETE FROM device WHERE `id` = '$id'");
        if (isset($device_exists_cache[$id])) { unset($device_exists_cache[$id]); } // Clear static cache
        
        if ($this->redis) {
            $userid = $this->redis->hget("device:$id",'userid');
            if (isset($userid)) {
                foreach ($this->redis->sMembers("device:thing:$id") as $key) {
                    $this->redis->del("device:item:$id:$key");
                    $this->redis->srem("device:thing:$id", $key);
                }
                $this->redis->del("device:$id");
                $this->redis->srem("user:device:$userid", $id);
            }
        }
        else if (isset($this->things[$id])) {
            unset($this->things[$id]);
        }
        return array('success'=>true, 'message'=>'Device successfully deleted');
    }

    public function set_fields($id, $fields) {
        $id = intval($id);
        if (!$this->exist($id)) {
            if (!$this->redis || !$this->load_device_to_redis($id)) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
        }
        $success = true;
        
        $fields = json_decode(stripslashes($fields));
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Fields error: ".json_last_error_msg());
        }
        
        if (isset($fields->nodeid)) {
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\s]/u', '', $fields->nodeid) != $fields->nodeid) {
                return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : / and space characters");
            }
            $stmt = $this->mysqli->prepare("UPDATE device SET nodeid = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->nodeid,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"nodeid",$fields->nodeid);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->name)) {
            $stmt = $this->mysqli->prepare("UPDATE device SET name = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->name,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"name",$fields->name);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->description)) {
            $stmt = $this->mysqli->prepare("UPDATE device SET description = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->description,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"description",$fields->description);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->type)) {
            if (preg_replace('/[^\/\|\,\w\s-:]/','',$fields->type)!=$fields->type) return array('success'=>false, 'message'=>'invalid characters in device type');
            $stmt = $this->mysqli->prepare("UPDATE device SET type = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->type,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"type",$fields->type);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->options)) {
            $options = json_encode($fields->options);
            $stmt = $this->mysqli->prepare("UPDATE device SET options = ? WHERE id = ?");
            $stmt->bind_param("si",$options,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"options",$options);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->devicekey)) {
            // 1. Only allow alphanumeric characters
            if (!ctype_alnum($fields->devicekey)) return array('success'=>false, 'message'=>'invalid characters in device key');
            
            // 2. Only allow 32 character length
            if (strlen($fields->devicekey)!=32) return array('success'=>false, 'message'=>'device key must be 32 characters long');
        
            $stmt = $this->mysqli->prepare("UPDATE device SET devicekey = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->devicekey,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"devicekey",$fields->devicekey);
            } else $success = false;
            $stmt->close();
        }

        if ($success) {
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }
    
    public function set_new_devicekey($id) {
        $id = intval($id);
        if (!$this->exist($id)) {
            if (!$this->redis || !$this->load_device_to_redis($id)) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
        }
        
        $devicekey = md5(uniqid(mt_rand(), true));
        
        $stmt = $this->mysqli->prepare("UPDATE device SET devicekey = ? WHERE id = ?");
        $stmt->bind_param("si",$devicekey,$id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            $this->redis->hSet("device:".$id,"devicekey",$devicekey);
            return $devicekey; 
        } else {
            return false;
        }
    }

    public function get_template_list() {
        $templates = array();
        
        $dir = scandir("Modules");
        for ($i=2; $i<count($dir); $i++) {
            if (filetype("Modules/".$dir[$i])=='dir' || filetype("Modules/".$dir[$i])=='link') {
                $class = $this->get_module_class($dir[$i], self::TEMPLATE);
                if ($class != null) {
                    $result = $class->get_template_list();
                    if (isset($result['success']) && $result['success'] == false) {
                        return $result;
                    }
                    $templates = array_merge($templates, $result);
                }
            }
        }
        ksort($templates);
        return $templates;
    }

    public function get_template_list_meta() {
        $templates = array();
        
        if ($this->redis) {
            if (!$this->redis->exists("device:templates:meta")) $this->load_template_list();
            
            $ids = $this->redis->sMembers("device:templates:meta");
            foreach ($ids as $id) {
                $template = $this->redis->hGetAll("device:template:$id");
                $template["options"] = isset($template["options"]) ? true : false;
                $template["control"] = isset($template["control"]) ? true : false;
                $template["thing"] = isset($template["thing"]) ? true : false;
                $template["scan"] = isset($template["scan"]) ? true : false;
                $templates[$id] = $template;
            }
        }
        else {
            if (empty($this->templates)) { // Cache it now
                $this->load_template_list();
            }
            $templates = $this->templates;
        }
        ksort($templates);
        return $templates;
    }

    public function reload_template_list() {
        $result = $this->load_template_list();
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        if (isset($result) && count($result) > 0) {
            $result = $this->load_thing_list();
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
        }
        return array('success'=>true, 'message'=>'Templates successfully reloaded');
    }

    private function load_template_list() {
        if ($this->redis) {
            foreach ($this->redis->sMembers("device:templates:meta") as $id) {
                $this->redis->del("device:template:$id");
            }
            $this->redis->del("device:templates:meta");
        }
        else {
            $this->templates = array();
        }
        $templates = array();
        
        $dir = scandir("Modules");
        for ($i=2; $i<count($dir); $i++) {
            if (filetype("Modules/".$dir[$i])=='dir' || filetype("Modules/".$dir[$i])=='link') {
                $class = $this->get_module_class($dir[$i], self::TEMPLATE);
                if ($class != null) {
                    $result = $class->get_template_list();
                    if (isset($result['success']) && $result['success'] == false) {
                        return $result;
                    }
                    foreach($result as $key => $value) {
                        $this->cache_template($dir[$i], $key, $value);
                        $templates[$key] = $value;
                    }
                }
            }
        }
        return $templates;
    }

    public function get_template($id) {
        $class = $this->get_device_class($id, self::TEMPLATE);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->get_template($id);
    }

    public function get_template_options($id) {
        $class = $this->get_device_class($id, self::TEMPLATE);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->get_template_options($id);
    }

    private function get_template_meta($id) {
        if ($this->redis) {
            if ($this->redis->exists("device:template:$id")) {
                $template = $this->redis->hGetAll("device:template:$id");
                $template["options"] = isset($template["options"]) ? true : false;
                $template["control"] = isset($template["control"]) ? true : false;
                $template["thing"] = isset($template["thing"]) ? true : false;
                $template["scan"] = isset($template["scan"]) ? true : false;
                return $template;
            }
        }
        else {
            if (empty($this->templates)) { // Cache it now
                $this->load_template_list();
            }
            if(isset($this->templates[$id])) {
                return $this->templates[$id];
            }
        }
        return array('success'=>false, 'message'=>'Device template does not exist');
    }

    private function cache_template($module, $id, $template) {
        $meta = array(
            "module"=>$module
        );
        $meta["name"] = ((!isset($template->name) || $template->name == "" ) ? $id : $template->name);
        $meta["category"] = ((!isset($template->category) || $template->category== "" ) ? "General" : $template->category);
        $meta["group"] = ((!isset($template->group) || $template->group== "" ) ? "Miscellaneous" : $template->group);
        $meta["description"] = (!isset($template->description) ? "" : $template->description);
        
        if ($this->redis) {
            if (isset($template->options)) $meta["options"] = true;
            if (isset($template->control)) $meta["control"] = true;
            if (isset($template->items)) $meta["thing"] = true;
            if (isset($template->scan)) $meta["scan"] = true;
            
            $this->redis->sAdd("device:templates:meta", $id);
            $this->redis->hMSet("device:template:$id", $meta);
        }
        else {
            $meta["options"] = isset($template->options) ? true : false;
            $meta["control"] = isset($template->control) ? true : false;
            $meta["thing"] = isset($template->items) ? true : false;
            $meta["scan"] = isset($template->scan) ? true : false;
            
            $this->templates[$id] = $meta;
        }
    }

    public function prepare_template($id) {
        $id = intval($id);
        
        $device = $this->get($id);
        if (empty($device['type'])) {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        $class = $this->get_device_class($device['type'], self::TEMPLATE);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->prepare_template($device);
    }

    public function init($id, $template) {
        $id = intval($id);
        
        $device = $this->get($id);
        $result = $this->init_template($device, $template);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        return array('success'=>true, 'message'=>'Device initialized');
    }

    public function init_template($device, $template) {
        if (isset($template)) $template = json_decode($template);
        
        if (empty($device['type'])) {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        $class = $this->get_device_class($device['type'], self::TEMPLATE);
        if (is_array($class) && isset($class['success']) && $class['success'] == false) {
            return $class;
        }
        return $class->init_template($device, $template);
    }

    public function get_thing_list($userid) {
        $userid = intval($userid);
        
        $things = array();
        $devices = $this->get_list($userid);
        foreach ($devices as $device) {
            if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
                $template = $this->get_template_meta($device['type']);
                if (isset($template['thing']) && $template['thing'] == true) {
                    $result = $this->get_thing_values($device);
                    if (isset($result['success']) && $result['success'] == false) {
                        continue;
                    }
                    $things[] = $result;
                }
            }
        }
        return $things;
    }

    private function load_thing_list() {
        $devices = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`options` FROM device");
        while ($device = (array) $devices->fetch_object()) {
            $device['options'] = (array) json_decode($device['options']);
            
            if ($this->redis) {
                foreach ($this->redis->sMembers("device:thing:".$device['id']) as $key) {
                    $this->redis->del("device:item:".$device['id'].":".$key);
                    $this->redis->srem("device:thing:".$device['id'], $key);
                }
            }
            if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
                $result = $this->cache_thing($device);
                if (isset($result['success']) && $result['success'] == false) {
                    return $result;
                }
            }
        }
    }

    private function get_thing_values($device) {
        $thing = array(
                'id' => $device['id'],
                'userid' => $device['userid'],
                'nodeid' => $device['nodeid'],
                'name' => $device['name'],
                'description' => $device['description'],
                'type' => $device['type']
        );
        
        $result = $this->get_item_list($device);
        if (isset($result)) {
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $thing['items'] = array();
            foreach ($result as $item) {
                if (!empty($item)) {
                    $thing['items'][] = $this->get_item_value($item);
                }
            }
        }
        return $thing;
    }

    private function get_item_list($device) {
        $items = null;
        if ($this->redis) {
            if ($this->redis->exists("device:thing:".$device['id'])) {
                $items = array();
                
                $itemids = $this->redis->sMembers("device:thing:".$device['id']);
                foreach ($itemids as $i) {
                    $item = (array) $this->redis->hGetAll("device:item:".$device['id'].":$i");
                    if (isset($item['select'])) $item['select'] = json_decode($item['select']);
                    if (isset($item['mapping'])) $item['mapping'] = json_decode($item['mapping']);
                    $items[] = $item;
                }
            }
        }
        else {
            if (empty($this->things)) { // Cache it now
                $this->cache_thing($device);
            }
            if (isset($this->things[$device['id']])) {
                $items = $this->things[$device['id']];
            }
        }
        
        // If nothing can be found in cache, load and cache all items
        if ($items == null) {
            if (empty($device['type'])) {
                return array('success'=>false, 'message'=>'Device type not specified');
            }
            $class = $this->get_device_class($device['type'], self::THING, true);
            if (is_array($class) && isset($class['success'])) {
                return $class;
            }
            
            $result = $class->get_item_list($device);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            return $this->cache_items($device['id'], $result);
        }
        return $items;
    }

    private function get_item_value($item) {
        $itemval = array();
        $keys = array('id', 'type', 'label', 'header', 'write',
            'left', 'right', 'format', 'scale', 'min', 'max', 'step',
            'select', 'default');
        foreach ($item as $key=>$val) {
            if (in_array($key, $keys)) $itemval[$key] = $val;
        }
        
        $value = null;
        if (isset($item['inputid'])) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $value = $input->get_last_value($item['inputid']);
        }
        if (isset($item['feedid'])) {
            global $feed_settings;
            require_once "Modules/feed/feed_model.php";
            $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
            
            $value = $feed->get_value($item['feedid']);
        }
        $itemval['value'] = $value;
        
        return $itemval;
    }

    private function cache_thing($device) {
        $class = $this->get_device_class($device['type'], self::THING, true);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        
        $result = $class->get_item_list($device);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        return $this->cache_items($device['id'], $result);
    }

    private function cache_items($id, $items) {
        if ($this->redis) {
            foreach ((array) $items as $key => $value) {
                if (isset($value['select'])) $value['select'] = json_encode($value['select']);
                if (isset($value['mapping'])) $value['mapping'] = json_encode($value['mapping']);
                $this->redis->sAdd("device:thing:$id", $key);
                $this->redis->hMSet("device:item:$id:$key", $value);
            }
        }
        else {
            if (empty($this->things[$id])) {
                $this->things[$id] = array();
            }
            foreach ($items as $value) {
                $this->things[$id][] = $value;
            }
        }
        return $items;
    }

    public function get_thing($id) {
        $id = intval($id);
        
        $device = $this->get($id);
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            return $this->get_thing_values($device);
        }
        else {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        return array('success'=>false, 'message'=>'Unknown error while getting device thing value');
    }

    public function get_item($id, $itemid) {
        $id = intval($id);
        $device = $this->get($id);
        
        if ($this->redis) {
            if ($this->redis->exists("device:thing:$id")) {
                $itemids = $this->redis->sMembers("device:thing:".$id);
                foreach ($itemids as $i) {
                    $item = (array) $this->redis->hGetAll("device:item:$id:$i");
                    if ($item['id'] == $itemid) {
                        if (isset($item['select'])) $item['select'] = json_decode($item['select']);
                        if (isset($item['mapping'])) $item['mapping'] = json_decode($item['mapping']);
                        return $item;
                    }
                }
            }
        }
        else if (isset($this->things) && isset($this->things[$id])) {
            $items = $this->things[$id];
            foreach ($items as $item) {
                if ($item['id'] == $itemid) {
                    return $item;
                }
            }
        }
        
        // If nothing can be found in cache, load and cache all items 
        if (empty($device['type'])) {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        $class = $this->get_device_class($device['type'], self::THING, true);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        
        $result = $class->get_item_list($device);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        $this->cache_items($device['id'], $result);
        
        foreach ($result as $item) {
            if ($item['id'] == $itemid) {
                return $item;
            }
        }
        return array('success'=>false, 'message'=>'Item does not exist');
    }

    public function set_item_on($id, $itemid) {
        $id = intval($id);
        $item = $this->get_item($id, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $mapping = (array) $item['mapping'];
            if (isset($mapping['ON'])) {
                return $this->set_item($id, $itemid, (array) $mapping['ON']);
            }
        }
        return array('success'=>false, 'message'=>'Unknown item or incomplete device template mappings "ON"');
    }

    public function set_item_off($id, $itemid) {
        $id = intval($id);
        $item = $this->get_item($id, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $mapping = (array) $item['mapping'];
            if (isset($mapping['OFF'])) {
                return $this->set_item($id, $itemid, (array) $mapping['OFF']);
            }
        }
        return array('success'=>false, 'message'=>'Unknown item or incomplete device template mappings "OFF"');
    }

    public function toggle_item_value($id, $itemid) {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Item "toggle" not implemented yet');
    }

    public function increase_item_value($id, $itemid) {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Item "increase" not implemented yet');
    }

    public function decrease_item_value($id, $itemid) {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Item "decrease" not implemented yet');
    }

    public function set_item_percent($id, $itemid, $value) {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Item "percent" not implemented yet');
    }

    public function set_item_value($id, $itemid, $value) {
        $id = intval($id);
        $item = $this->get_item($id, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $mapping = (array) $item['mapping'];
            if (isset($mapping['SET'])) {
                $mapping = (array) $mapping['SET'];
                $mapping['value'] = $value;
                
                return $this->set_item($id, $itemid, $mapping);
            }
        }
        return array('success'=>false, 'message'=>'Unknown item or incomplete device template mappings "SET"');
    }

    public function set_item($id, $itemid, $mapping) {
        $id = intval($id);
        
        $device = $this->get($id);
        if (empty($device['type'])) {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        $class = $this->get_device_class($device['type'], self::THING, true);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->set_item($itemid, $mapping);
    }

    public function scan_start($userid, $type, $options) {
        $userid = intval($userid);
        if (empty($options)) {
            $options = '{}';
        }
        $options = json_decode($options, true);
        
        $class = $this->get_device_class($type, self::SCAN, true);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->start($userid, $type, $options);
    }

    public function scan_progress($userid, $type) {
        $userid = intval($userid);
        
        $class = $this->get_device_class($type, self::SCAN, true);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->progress($userid, $type);
    }

    public function scan_cancel($userid, $type) {
        $userid = intval($userid);
        
        $class = $this->get_device_class($type, self::SCAN, true);
        if (is_array($class) && isset($class['success'])) {
            return $class;
        }
        return $class->cancel($userid, $type);
    }

    private function get_device_class($id, $type, $check=false) {
        if (empty($id) || $id === 'null') {
            return array('success'=>false, 'message'=>"Device type not specified");
        }
        $result = $this->get_template_meta($id);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        if ($check && (empty($result[$type]) || !$result[$type])) {
            return array('success'=>false, 'message'=>"Device $type not specified");
        }
        
        $module = $result['module'];
        $class = $this->get_module_class($module, $type);
        if (empty($class)) {
            return array('success'=>false, 'message'=>"Device $type class is not defined");
        }
        return $class;
    }

    private function get_module_class($module, $type) {
        /*
         magic function __call (above) MUST BE USED with this.
         Load additional template module files.
         Looks in the folder Modules/modulename/ for a file modulename_template.php
         (module_name all lowercase but class ModulenameTemplate in php file that is CamelCase)
         */
        $module_file = "Modules/".$module."/".$module."_".$type.".php";
        $module_class = null;
        if(file_exists($module_file)){
            require_once($module_file);
            
            $module_class_name = ucfirst(strtolower($module)).ucfirst($type);
            $module_class = new $module_class_name($this);
        }
        return $module_class;
    }
}
