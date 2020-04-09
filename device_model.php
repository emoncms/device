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

    public $mysqli;
    public $redis;
    private $log;

    private $templates;

    public function __construct($mysqli, $redis) {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->templates = array();
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

    public function exists_name($userid, $name) {
        $userid = intval($userid);
        $name = preg_replace('/[^\p{L}_\p{N}\s\-:]/u','',$name);
        
        $stmt = $this->mysqli->prepare("SELECT id FROM device WHERE userid=? AND name=?");
        $stmt->bind_param("is", $userid, $name);
        $stmt->execute();
        $stmt->bind_result($id);
        $result = $stmt->fetch();
        $stmt->close();
        
        if ($result && $id > 0) return $id; else return false;
    }

    public function exists_nodeid($userid, $nodeid) {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s\-:]/u','',$nodeid);

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
            global $settings;
            return $settings['mqtt']['user'].":".$settings['mqtt']['password'].":".$settings['mqtt']['basetopic'];
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
            // Verify, if the cached device contains the userid, to avoid compatibility issues
            // with former versions where the userid was not cached.
            if (!isset($device['userid'])) {
                $this->load_device_to_redis($id);
                $device = $this->get($id);
            }
            $device['time'] = $this->redis->hget("device:lastvalue:".$id, 'time');
        }
        else {
            // Get from mysql db
            $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey`,`time` FROM device WHERE id = '$id'");
            $device = (array) $result->fetch_object();
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
            // Verify, if the cached device contains the userid, to avoid compatibility issues
            // with former versions where the userid was not cached.
            if (!isset($device['userid'])) {
                $this->load_device_to_redis($id);
                $device = $this->get($id);
            }
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
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey`,`time` FROM device WHERE userid = '$userid' ORDER BY nodeid, name asc");
        while ($device = (array) $result->fetch_object()) {
            $devices[] = $device;
        }
        return $devices;
    }

    private function load_list_to_redis($userid) {
        $userid = intval($userid);
        
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey` FROM device WHERE userid = '$userid'");
        while ($row = $result->fetch_object()) {
            $this->redis->sAdd("user:device:$userid", $row->id);
            $this->redis->hMSet("device:".$row->id, array(
                'id'=>$row->id,
                'userid'=>$row->userid,
                'nodeid'=>$row->nodeid,
                'name'=>$row->name,
                'description'=>$row->description,
                'type'=>$row->type,
                'devicekey'=>$row->devicekey
            ));
        }
    }

    private function load_device_to_redis($id) {
        $id = intval($id);
        
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey` FROM device WHERE id = '$id'");
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
            'devicekey'=>$row->devicekey
        ));
        return true;
    }

    public function autocreate($userid, $_nodeid, $_type) {
        $userid = intval($userid);
        
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s\-:]/u','',$_nodeid);
        if ($_nodeid != $nodeid) return array("success"=>false, "message"=>"Invalid nodeid");
        $type = preg_replace('/[^\/\|\,\w\s\-:]/','',$_type);
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
            return $this->init($deviceid,false);
        } else {
            return $result;
        }
    }

    public function create($userid, $nodeid, $name, $description, $type) {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s\-:]/u', '', $nodeid);
        
        if (isset($name)) {
            $name = preg_replace('/[^\p{L}_\p{N}\s\-:]/u', '', $name);
        } else {
            $name = $nodeid;
        }
        
        if (isset($description)) {
            $description = preg_replace('/[^\p{L}_\p{N}\s\-:]/u', '', $description);
        } else {
            $description = '';
        }
        
        if (isset($type) && $type != 'null') {
            $type = preg_replace('/[^\/\|\,\w\s\-:]/','', $type);
        } else {
            $type = '';
        }
        
        if (!$this->exists_nodeid($userid, $nodeid)) {
            // device key disabled by default
            $devicekey = ""; // md5(uniqid(mt_rand(), true));
            
            $stmt = $this->mysqli->prepare("INSERT INTO device (userid,nodeid,name,description,type,devicekey) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssss",$userid,$nodeid,$name,$description,$type,$devicekey);
            $result = $stmt->execute();
            $stmt->close();
            if (!$result) return array('success'=>false, 'message'=>_("Error creating device"));
            
            $deviceid = $this->mysqli->insert_id;
            
            if ($deviceid > 0) {
                // Add the device to redis
                if ($this->redis) {
                    // Reload all devices from mysql here to ensure cache is not out of sync 
                    $this->load_list_to_redis($userid);
                    $this->redis->sAdd("user:device:$userid", $deviceid);
                    $this->redis->hMSet("device:".$deviceid, array(
                        'id'=>$deviceid,
                        'userid'=>$userid,
                        'nodeid'=>$nodeid,
                        'name'=>$name,
                        'description'=>$description,
                        'type'=>$type,
                        'devicekey'=>$devicekey
                    ));
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
                $this->redis->srem("user:device:$userid", $id);
                $this->redis->del("device:$id");
            }
        }
    }
    
    // Clear devices with empty input processLists
    public function clean($userid,$active=0,$dryrun=0) {
        $userid = (int) $userid;
        $active = (int) $active;
        
        $now = time();
        
        $deleted_inputs = 0;
        $deleted_nodes = 0;

        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey` FROM device WHERE userid = '$userid'");
        while ($row = $result->fetch_object()) {
        
            $id = $row->id;
            $nodeid = $row->nodeid;
            
            // Fetch inputs associated with node
            $inputs = array();
            if ($result2 = $this->mysqli->query("SELECT * FROM input WHERE `userid` = '$userid' AND `nodeid` = '$nodeid'")) {
                while ($row2 = $result2->fetch_object()) $inputs[] = $row2;
            }
            
            // Check that all node inputs are empty
            $inputs_empty = true;
            foreach ($inputs as $i) {
                $inputid = $i->id;
                
                if ($i->processList!=NULL && $i->processList!='') {
                    $inputs_empty = false;
                }

                if ($active && $this->redis) {
                   $input_time = $this->redis->hget("input:lastvalue:$inputid",'time');
                   if (($now-$input_time)<$active) {
                       $inputs_empty = false;
                   }
                }
            }
            
            if ($inputs_empty) {
                 // Delete node
                 if (!$dryrun) $this->delete($id);
                 
                 // Delete inputs
                 foreach ($inputs as $i) {
                    $inputid = $i->id;
                    if (!$dryrun) {
                        $this->mysqli->query("DELETE FROM input WHERE userid = '$userid' AND id = '$inputid'");
                        if ($this->redis) {
                            $this->redis->del("input:$inputid");
                            $this->redis->srem("user:inputs:$userid",$inputid);
                        }
                    }
                    $deleted_inputs++;
                }
                $deleted_nodes++;
            }
        }
        if ($dryrun) return "DRYRUN: $deleted_nodes nodes to delete ($deleted_inputs inputs)";
        return "Deleted $deleted_nodes nodes ($deleted_inputs inputs)";
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

        if (isset($fields->name)) {
            if (preg_replace('/[^\p{N}\p{L}_\s\-:]/u','',$fields->name)!=$fields->name) return array('success'=>false, 'message'=>'invalid characters in device name');
            $stmt = $this->mysqli->prepare("UPDATE device SET name = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->name,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"name",$fields->name);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->description)) {
            if (preg_replace('/[^\p{N}\p{L}_\s\-:]/u','',$fields->description)!=$fields->description) return array('success'=>false, 'message'=>'invalid characters in device description');
            $stmt = $this->mysqli->prepare("UPDATE device SET description = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->description,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"description",$fields->description);
            } else $success = false;
            $stmt->close();
        }

        if (isset($fields->nodeid)) {
            if (preg_replace('/[^\p{N}\p{L}_\s\-:]/u','',$fields->nodeid)!=$fields->nodeid) return array('success'=>false, 'message'=>'invalid characters in device nodeid');
            $stmt = $this->mysqli->prepare("UPDATE device SET nodeid = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->nodeid,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"nodeid",$fields->nodeid);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->type)) {
            if (preg_replace('/[^\/\|\,\w\s\-:]/','',$fields->type)!=$fields->type) return array('success'=>false, 'message'=>'invalid characters in device type');
            $stmt = $this->mysqli->prepare("UPDATE device SET type = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->type,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"type",$fields->type);
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
                $this->redis->hSet("device:".$id,"devicekey",$fields->devicekey);
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
        return $this->load_template_list();
    }

    public function get_template_list_meta() {
        $templates = array();
        
        if ($this->redis) {
            if (!$this->redis->exists("device:templates:meta")) $this->load_template_list();
            
            $ids = $this->redis->sMembers("device:templates:meta");
            foreach ($ids as $id) {
                $template = $this->redis->hGetAll("device:template:$id");
                $template["control"] = (bool) $template["control"];
                
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

    private function get_template_meta($id) {
        if ($this->redis) {
            if ($this->redis->exists("device:template:$id")) {
                $template = $this->redis->hGetAll("device:template:$id");
                $template["control"] = (bool) $template["control"];
                
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

    public function get_template($id) {
        
        $result = $this->get_template_meta($id);
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        $module = $result['module'];
        $class = $this->get_module_class($module, self::TEMPLATE);
        if ($class != null) {
            return $class->get_template($id);
        }
        return array('success'=>false, 'message'=>'Device template class is not defined');
    }

    public function prepare_template($id) {
        $id = intval($id);
        
        $device = $this->get($id);
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            $result = $this->get_template_meta($device['type']);
            if (isset($result["success"]) && $result["success"] == false) {
                return $result;
            }
            $module = $result['module'];
            $class = $this->get_module_class($module, self::TEMPLATE);
            if ($class != null) {
                return $class->prepare_template($device);
            }
            return array('success'=>false, 'message'=>'Device template class is not defined');
        }
        return array('success'=>false, 'message'=>'Device type not specified');
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
        if (isset($template) && $template!==false) $template = json_decode($template);
        
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            $result = $this->get_template_meta($device['type']);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $module = $result['module'];
            $class = $this->get_module_class($module, self::TEMPLATE);
            if ($class != null) {
                return $class->init_template($device, $template);
            }
            return array('success'=>false, 'message'=>'Device template class is not defined');
        }
        return array('success'=>false, 'message'=>'Device type not specified');
    }

    public function reload_template_list() {
        $result = $this->load_template_list();
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
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

    private function cache_template($module, $id, $template) {
        $meta = array(
            "module"=>$module
        );
        $meta["name"] = ((!isset($template->name) || $template->name == "" ) ? $id : $template->name);
        $meta["category"] = ((!isset($template->category) || $template->category== "" ) ? "General" : $template->category);
        $meta["group"] = ((!isset($template->group) || $template->group== "" ) ? "Miscellaneous" : $template->group);
        $meta["description"] = (!isset($template->description) ? "" : $template->description);
        $meta["control"] = (!isset($template->control) ? false : true);
        
        if ($this->redis) {
            $this->redis->sAdd("device:templates:meta", $id);
            $this->redis->hMSet("device:template:$id", $meta);
        }
        else {
            $this->templates[$id] = $meta;
        }
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
