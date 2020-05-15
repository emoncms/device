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
    public $mysqli;
    public $redis;
    private $log;

    public function __construct($mysqli, $redis) {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
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
            $session['read'] = 1;
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
                $session['read'] = 1;
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
        $nodeid = preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $nodeid);

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

    public function autocreate($userid, $nodeid, $type) {
        $userid = intval($userid);
        
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (isset($type) && $type != 'null') {
            $type = preg_replace('/[^\/\|\,\w\s\-\:]/','',$type);
        } else {
            $type = '';
        }
        $name = "$nodeid:$type";
        
        $deviceid = $this->exists_nodeid($userid, $nodeid);
        if (!$deviceid) {
            $this->log->info("Automatically create device for user=$userid, nodeid=$nodeid");
            
            $result = $this->create($userid, $nodeid, $name, '', $type);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $deviceid = $result;
        }
        else {
            $result = $this->set_fields($deviceid,json_encode(array("nodeid"=>$nodeid,"name"=>$name,"type"=>$type)));
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
        }
        return $this->init($deviceid);
    }

    public function create($userid, $nodeid, $name='', $description='', $type=null, $options=null) {
        $userid = intval($userid);
        
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (!isset($name)) $name = '';
        else if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $name) != $name) {
            return array('success'=>false, 'message'=>"Device name must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (!isset($description)) $description = '';
        else if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $description) != $description) {
            return array('success'=>false, 'message'=>"Device description must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (isset($type) && $type != 'null') {
            $type = preg_replace('/[^\/\|\,\w\s\-\:]/','', $type);
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

    public function init($id, $template) {
        $id = intval($id);
        
        $device = $this->get($id);
        $result = $this->init_template($device, $template);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        return array('success'=>true, 'message'=>'Device initialized');
    }

    public function delete($id) {
        $id = intval($id);
        if (!$this->exist($id)) {
            if (!$this->redis || !$this->load_device_to_redis($id)) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
        }
        $result = $this->delete_template($id);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
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
        $device = $this->get($id);
        $success = true;
        
        $fields = json_decode(stripslashes($fields));
        if (json_last_error() != 0) {
            return array('success'=>false, 'message'=>"Fields error: ".json_last_error_msg());
        }
        
        if (isset($fields->nodeid)) {
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $fields->nodeid) != $fields->nodeid) {
                return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : and space characters");
            }
            $stmt = $this->mysqli->prepare("UPDATE device SET nodeid = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->nodeid,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"nodeid",$fields->nodeid);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->name)) {
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $fields->name) != $fields->name) {
                return array('success'=>false, 'message'=>"Device name must only contain A-Z a-z 0-9 - _ . : and space characters");
            }
            $stmt = $this->mysqli->prepare("UPDATE device SET name = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->name,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"name",$fields->name);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->description)) {
            if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $fields->description) != $fields->description) {
                return array('success'=>false, 'message'=>"Device description must only contain A-Z a-z 0-9 - _ . : and space characters");
            }
            $stmt = $this->mysqli->prepare("UPDATE device SET description = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->description,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"description",$fields->description);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->type)) {
            if (preg_replace('/[^\/\|\,\w\s\-:]/','',$fields->type)!=$fields->type) return array('success'=>false, 'message'=>'invalid characters in device type');
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
            $result = $this->update_template($device, $fields);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            return array('success'=>true, 'message'=>'Fields updated');
        }
        return array('success'=>false, 'message'=>'Fields could not be updated');
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

    public function get_template_class($id) {
        return $this->get_device_class($id, 'template');
    }

    public function get_template($id) {
        return $this->get_template_class($id)->get($id);
    }

    public function get_template_options($id) {
        return $this->get_template_class($id)->get_options($id);
    }

    public function get_template_list() {
        $templates = array();
        
        $dir = scandir("Modules");
        for ($i=2; $i<count($dir); $i++) {
            if (filetype("Modules/".$dir[$i])=='dir' || filetype("Modules/".$dir[$i])=='link') {
                $class = $this->get_module_class($dir[$i], 'template');
                if ($class != null) {
                    $result = $class->get_list();
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
                $templates[$id] = $template;
            }
        }
        else {
            foreach ($this->load_template_list() as $id => $template) {
                $templates[$id] = $this->parse_template_meta($template);
            }
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
        $templates = array();
        
        $dir = scandir("Modules");
        for ($i=2; $i<count($dir); $i++) {
            if (filetype("Modules/".$dir[$i])=='dir' || filetype("Modules/".$dir[$i])=='link') {
                $class = $this->get_module_class($dir[$i], 'template');
                if ($class != null) {
                    $result = $class->get_list();
                    if (isset($result['success']) && $result['success'] == false) {
                        return $result;
                    }
                    foreach ($result as $id => $template) {
                        $template = (object) array_merge(array('module'=>$dir[$i]), (array) $template);
                        $templates[$id] = $template;
                        
                        if ($this->redis) {
                            $this->redis->sAdd("device:templates:meta", $id);
                            $this->redis->hMSet("device:template:$id", $this->parse_template_meta($template));
                        }
                    }
                }
            }
        }
        return $templates;
    }

    private function get_template_meta($id) {
        if ($this->redis) {
            if ($this->redis->exists("device:template:$id")) {
                return $this->redis->hGetAll("device:template:$id");
            }
        }
        else {
            if (empty($this->templates) || !isset($this->templates[$id])) {
                $this->templates = $this->get_template_list_meta();
            }
            if (isset($this->templates[$id])) {
                return $this->templates[$id];
            }
        }
        return array('success'=>false, 'message'=>'Device template does not exist');
    }

    private function parse_template_meta($template) {
        return array(
            "module" => $template->module,
            "name" => !isset($template->name) || $template->name =="" ? $id : $template->name,
            "group" => !isset($template->group) || $template->group=="" ? "Miscellaneous" : $template->group,
            "category" => !isset($template->category) || $template->category=="" ? "General" : $template->category,
            "description" => !isset($template->description) ? "" : $template->description,
            "options" => isset($template->options) ? true : false,
            "control" => isset($template->control) ? true : false,
            "thing" => isset($template->thing) || isset($template->items) ? true : false,
            "scan" => isset($template->scan) ? true : false
        );
    }

    public function prepare_template($device) {
        if (empty($device['type'])) {
            return array('success'=>true, 'message'=>'Device type not specified');
        }
        return $this->get_template_class($device['type'])->prepare($device);
    }

    public function init_template($device, $template) {
        if (empty($device['type'])) {
            return array('success'=>true, 'message'=>'Device type not specified');
        }
        return $this->get_template_class($device['type'])->init($device, $template);
    }

    public function update_template($device, $fields) {
        if (isset($fields->type)) {
            $device['type'] = $fields->type;
        }
        if (empty($device['type'])) {
            return array('success'=>true, 'message'=>'Device type not specified');
        }
        return $this->get_template_class($device['type'])->set_fields($device, $fields);
    }

    public function delete_template($id) {
        $id = intval($id);
        
        $device = $this->get($id);
        if (empty($device['type'])) {
            return array('success'=>true, 'message'=>'Device type not specified');
        }
        return $this->get_template_class($device['type'])->delete($device);
    }

    public function get_thing_class($id) {
        return $this->get_device_class($id, 'thing');
    }

    public function get_thing($device) {
        if (empty($device['type']) || $device['type'] == 'null') {
            throw new DeviceException('Device type not specified');
        }
        $template = $this->get_template_meta($device['type']);
        if (empty($template['thing']) || !$template['thing']) {
            throw new DeviceException('Device thing not specified');
        }
        return $this->get_thing_class($device['type'])->get($device);
    }

    public function get_thing_list($userid) {
        $userid = intval($userid);
        
        $things = array();
        $devices = $this->get_list($userid);
        foreach ($devices as $device) {
            try {
                $things[] = $this->get_thing($device);
            }
            catch(DeviceException $e) {
                // Do nothing and skip device thing
            }
        }
        return $things;
    }

    private function load_thing_list() {
        $devices = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type` FROM device");
        while ($device = (array) $devices->fetch_object()) {
            try {
                $this->get_thing_class($device['type'])->load($device);
            }
            catch(Exception $e) {
                // Do nothing and skip device thing
            }
        }
    }

    private function get_thing_item($device) {
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            $template = $this->get_template_meta($device['type']);
            if (isset($template['thing']) && $template['thing'] == true) {
                $result = $this->cache_thing($device);
                if (isset($result['success']) && $result['success'] == false) {
                    return $result;
                }
            }
        }
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

    private function get_device_class($id, $type, $check=false) {
        if (empty($id) || $id === 'null') {
            throw new DeviceException("Device type not specified: $id");
        }
        $result = $this->get_template_meta($id);
        if (isset($result['success']) && $result['success'] == false) {
            throw new DeviceException($result['message']);
        }
        if ($check && (empty($result[$type]) || !$result[$type])) {
            throw new DeviceException("Device $type not specified for type: $id");
        }
        
        $class = $this->get_module_class($result['module'], $type);
        if (empty($class)) {
            throw new DeviceException("Device $type class is not defined for type: $id");
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

class DeviceException extends Exception {
    public function getResult() {
        return array(
            'success'=>false,
            'message'=>$this->getMessage(),
            'trace'=>$this->getTrace());
    }
}
