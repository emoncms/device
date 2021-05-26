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

    public function exists_name($userid, $name) {
        $userid = intval($userid);
        $name = preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u','',$name);
        
        $stmt = $this->mysqli->prepare("SELECT id,name FROM device WHERE userid=? AND name=?");
        $stmt->bind_param("is", $userid, $name);
        $stmt->execute();
        $stmt->bind_result($id,$_name);
        $result = $stmt->fetch();
        $stmt->close();
        
        // SQL search may not be case sensitive
        if ($_name != $name) return false;
        
        if ($result && $id > 0) return $id; else return false;
    }

    public function exists_nodeid($userid, $nodeid) {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $nodeid);
        
        $stmt = $this->mysqli->prepare("SELECT id,nodeid FROM device WHERE userid=? AND nodeid=?");
        $stmt->bind_param("is", $userid, $nodeid);
        $stmt->execute();
        $stmt->bind_result($id, $_nodeid);
        $result = $stmt->fetch();
        $stmt->close();
        
        // SQL search may not be case sensitive
        if ($_nodeid != $nodeid) return false;
        
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

    public function autocreate($userid, $nodeid, $type) {
        $userid = intval($userid);
        
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (!isset($type) || $type == 'null') $type = '';
        else if (preg_replace('/[^\p{N}\p{L}\-\_]/u', '', $type) != $type) {
            return array('success'=>false, 'message'=>"Device type must only contain A-Z a-z 0-9 - and _ characters");
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

    public function create($userid, $nodeid, $name='', $description='', $type='', $options='') {
        $userid = intval($userid);
        
        if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $nodeid) != $nodeid) {
            return array('success'=>false, 'message'=>"Device key must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (!isset($name)) $name = $nodeid;
        else if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $name) != $name) {
            return array('success'=>false, 'message'=>"Device name must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (!isset($description)) $description = '';
        else if (preg_replace('/[^\p{N}\p{L}\-\_\.\:\s]/u', '', $description) != $description) {
            return array('success'=>false, 'message'=>"Device description must only contain A-Z a-z 0-9 - _ . : and space characters");
        }
        if (!isset($type) || $type == 'null') $type = '';
        else if (preg_replace('/[^\p{N}\p{L}\-\_]/u', '', $type) != $type) {
            return array('success'=>false, 'message'=>"Device type must only contain A-Z a-z 0-9 - and _ characters");
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
                $device = array(
                    'id'=>$deviceid,
                    'userid'=>$userid,
                    'nodeid'=>$nodeid,
                    'name'=>$name,
                    'description'=>$description,
                    'type'=>$type,
                    'devicekey'=>$devicekey
                );
                // Add the device to redis
                if ($this->redis) {
                    // Reload all devices from mysql here to ensure cache is not out of sync 
                    $this->load_list_to_redis($userid);
                    $this->redis->sAdd("user:device:$userid", $deviceid);
                    $this->redis->hMSet("device:".$deviceid, $device);
                }
                
                $options = json_decode($options, true);
                if (!empty($options)) {
                    if ($stmt=$this->mysqli->prepare("INSERT INTO device_config (deviceid,optionid,value) VALUES (?,?,?)")) {
                        foreach ($options as $key => $value) {
                            $stmt->bind_param('iss',$deviceid,$key,$value);
                            $stmt->execute();
                            
                            if ($this->redis) {
                                $this->redis->sAdd("device:configs:$deviceid", $key);
                                $this->redis->set("device:config:$deviceid:$key", $value);
                            }
                        }
                    }
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
        $device = $this->get($id);
        try {
            $this->get_template_class($device['type'])->delete($device);
            $this->get_thing_class($device['type'])->delete($device);
        }
        catch (DeviceException $e) {
            // TODO: improve logging or error handling
            // Ignore this for now
        }
        
        $this->mysqli->query("DELETE FROM device WHERE `id` = '$id'");
        $this->mysqli->query("DELETE FROM device_config WHERE `deviceid` = '$id'");
        if (isset($device_exists_cache[$id])) { unset($device_exists_cache[$id]); } // Clear static cache
        
        if ($this->redis) {
            $this->redis->del("device:$id");
            $this->redis->srem("user:device:".$device['userid'], $id);
            
            foreach ($this->redis->sMembers("device:configs:$id") as $config) {
                $this->redis->del("device:configs:$id:$config");
                $this->redis->srem("device:config:$id",$config);
            }
        }
        else if (isset($this->things[$id])) {
            unset($this->things[$id]);
        }
        return array('success'=>true, 'message'=>'Device successfully deleted');
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
            if (preg_replace('/[^\p{N}\p{L}\-\_]/u', '', $fields->type) != $fields->type) {
                return array('success'=>false, 'message'=>"Device type must only contain A-Z a-z 0-9 - and _ characters");
            }
            $stmt = $this->mysqli->prepare("UPDATE device SET type = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->type,$id);
            if ($stmt->execute()) {
                if ($this->redis) $this->redis->hSet("device:".$id,"type",$fields->type);
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
        
        if (isset($fields->options)) {
            $device['configs'] = $this->get_configs($device);
            $this->mysqli->query("DELETE FROM device_config WHERE `deviceid` = '$id'");
            if ($this->redis) {
                foreach ($this->redis->sMembers("device:configs:$id") as $config) {
                    $this->redis->del("device:configs:$id:$config");
                    $this->redis->srem("device:config:$id",$config);
                }
            }
            if ($stmt=$this->mysqli->prepare("INSERT INTO device_config (deviceid,optionid,value) VALUES (?,?,?)")) {
                foreach ($fields->options as $config => $value) {
                    $stmt->bind_param('iss',$id,$config,$value);
                    $stmt->execute();
                    
                    if ($this->redis) {
                        $this->redis->sAdd("device:configs:$id", $config);
                        $this->redis->set("device:config:$id:$config", $value);
                    }
                }
            }
        }
        
        if ($success) {
            if (!empty($device['type'])) {
                $result = $this->get_template_class($device['type'])->set_fields($device, $fields);
                if (isset($result['success']) && $result['success'] == false) {
                    return $result;
                }
                $this->get_thing_class($device['type'])->load($device);
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
                $templates[$id] = $this->parse_template_meta((object) $template);
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
                $template = $this->redis->hGetAll("device:template:$id");
                return $this->parse_template_meta((object) $template);
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
            "name" => $template->name,
            "group" => empty($template->group) ? "Miscellaneous" : $template->group,
            "category" => empty($template->category) ? "General" : $template->category,
            "description" => empty($template->description) ? "" : $template->description,
            "options" => empty($template->options) ? false : true,
            "control" => empty($template->control) ? false : true,
            "thing" => empty($template->thing) && empty($template->items) ? false : true,
            "scan" => empty($template->scan) ? false : true
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
            if (!empty($device['type'])) {
                try {
                    $this->get_thing_class($device['type'])->load($device);
                }
                catch(Exception $e) {
                    // Do nothing and skip device thing
                }
            }
        }
    }
    
    public function get_configs($device) {
        $userid = $device['userid'];
        $deviceid = $device['id'];
        $configs = array();
        if ($this->redis) {
            // Get from redis cache
            if (!$this->redis->exists("user:device:$userid")) {
                $this->load_configs_to_redis($userid);
            }
            $configids = $this->redis->sMembers("device:configs:$deviceid");
            foreach ($configids as $config) {
                $configs[$config] = $this->redis->get("device:config:$deviceid:$config");
            }
        }
        if (empty($configs)) {
            // Get from mysql db
            $result = $this->mysqli->query("SELECT `deviceid`,`optionid`,`value` FROM device_config WHERE deviceid = '$deviceid'");
            while ($config = (array) $result->fetch_object()) {
                $configs[$config['optionid']] = $config['value'];
                if ($this->redis) {
                    $this->redis->sAdd("device:configs:$deviceid", $config['optionid']);
                    $this->redis->set("device:config:$deviceid:".$config['optionid'], $config['value']);
                }
            }
        }
        return $configs;
    }

    public function get_options($device) {
        $configs = $this->get_configs($device);
        $options = $this->get_template_class($device['type'])->get_options($device['type']);
        foreach ($options as &$option) {
            if (array_key_exists($option['id'], $configs)) {
                $option['value'] = $configs[$option['id']];
            }
        }
        return $options;
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
