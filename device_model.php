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
                    $this->mysqli->query("UPDATE device SET time='$time' WHERE id = '$id'");
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
        $name = preg_replace('/[^\p{L}_\p{N}\s\-:.]/u','',$name);
        
        $stmt = $this->mysqli->prepare("SELECT id,name FROM device WHERE userid=? AND name=?");
        $stmt->bind_param("is", $userid, $name);
        $stmt->execute();
        $stmt->bind_result($id,$_name);
        $result = $stmt->fetch();
        $stmt->close();

        // SQL search may not be case sensitive
        if ($_name!=$name) return false;
        
        if ($result && $id > 0) return $id; else return false;
    }

    public function exists_nodeid($userid, $nodeid) {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s\-:.]/u','',$nodeid);

        $stmt = $this->mysqli->prepare("SELECT id,nodeid FROM device WHERE userid=? AND nodeid=?");
        $stmt->bind_param("is", $userid, $nodeid);
        $stmt->execute();
        $stmt->bind_result($id,$_nodeid);
        $result = $stmt->fetch();
        $stmt->close();
        
        // SQL search may not be case sensitive
        if ($_nodeid!=$nodeid) return false;
        
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
            $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey`,`time`, `ip` FROM device WHERE id = '$id'");
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
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey`,`time`,`ip` FROM device WHERE userid = '$userid' ORDER BY nodeid, name asc");
        while ($device = (array) $result->fetch_object()) {
            $devices[] = $device;
        }
        return $devices;
    }

    private function load_list_to_redis($userid) {
        $userid = intval($userid);
        
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey`,`ip` FROM device WHERE userid = '$userid'");
        while ($row = $result->fetch_object()) {
            $this->redis->sAdd("user:device:$userid", $row->id);
            $this->redis->hMSet("device:".$row->id, array(
                'id'=>$row->id,
                'userid'=>$row->userid,
                'nodeid'=>$row->nodeid,
                'name'=>$row->name,
                'description'=>$row->description,
                'type'=>$row->type,
                'devicekey'=>$row->devicekey,
                'ip'=>$row->ip
            ));
        }
    }

    private function load_device_to_redis($id) {
        $id = intval($id);
        
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey`,`ip` FROM device WHERE id = '$id'");
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
            'devicekey'=>$row->devicekey,
            'ip'=>$row->ip
        ));
        return true;
    }

    public function autocreate($userid, $_nodeid, $_type) {
        $userid = intval($userid);
        
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s\-:.]/u','',$_nodeid);
        if ($_nodeid != $nodeid) return array("success"=>false, "message"=>"Invalid nodeid");
        $type = preg_replace('/[^\/\|\,\w\s\-:]/','',$_type);
        if ($_type != $type) return array("success"=>false, "message"=>"Invalid type");
        
        $name = "$nodeid:$type";
        
        $deviceid = $this->exists_nodeid($userid, $nodeid);
        
        if (!$deviceid) {
            $this->log->info("Automatically create device for user=$userid, nodeid=$nodeid");
            $deviceid = $this->create($userid, $nodeid, null, null, null, null);
            if (!$deviceid) return array("success"=>false, "message"=>"Device creation failed");
        }
        
        $result = $this->set_fields($deviceid,json_encode(array("name"=>$name,"nodeid"=>$nodeid,"type"=>$type)));
        if ($result['success']==true) {
            return $this->init($deviceid,false);
        } else {
            return $result;
        }
    }

    public function create($userid, $nodeid, $name, $description, $type, $devicekey = "") {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s\-:.]/u', '', $nodeid);
        
        if (isset($name)) {
            $name = preg_replace('/[^\p{L}_\p{N}\s\-:.]/u', '', $name);
        } else {
            $name = $nodeid;
        }
        
        if (isset($description)) {
            $description = preg_replace('/[^\p{L}_\p{N}\s\-:.]/u', '', $description);
        } else {
            $description = '';
        }
        
        if (isset($type) && $type != 'null') {
            $type = preg_replace('/[^\/\|\,\w\s\-:.]/','', $type);
        } else {
            $type = '';
        }
        
        if ($this->redis) {
            // Reload all devices from mysql here to ensure cache is not out of sync 
            $this->load_list_to_redis($userid);
        }
        
        if (!$this->exists_nodeid($userid, $nodeid)) {
            $stmt = $this->mysqli->prepare("INSERT INTO device (userid,nodeid,name,description,type,devicekey) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssss",$userid,$nodeid,$name,$description,$type,$devicekey);
            $result = $stmt->execute();
            $stmt->close();
            if (!$result) return array('success'=>false, 'message'=>tr("Error creating device"));
            
            $deviceid = $this->mysqli->insert_id;
            
            if ($deviceid > 0) {
                // Add the device to redis
                if ($this->redis) {
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
        return array('success'=>false, 'message'=>'Device already exists, cache reloaded, try reloading the page');
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
    
    /**
     * Clean inactive and unconfigured inputs from devices
     * 
     * This method determines which inputs can be safely removed by:
     * 1. For devices with configured inputs: removes inputs that are unconfigured AND inactive
     *    relative to the most recent configured input activity
     * 2. For devices with NO configured inputs: removes inputs that are inactive relative to current time
     * 3. Removes entire devices if all their inputs are removed
     * 
     * @param int $userid User ID
     * @param int $inactive_timeout Timeout in seconds for considering inputs inactive (default 60)
     * @param int $dryrun If 1, only simulate the cleanup without making changes
     * @return string Result message indicating what was cleaned/would be cleaned
     */
    public function clean($userid, $inactive_timeout = 3600, $dryrun = 0) {
        $userid = (int) $userid;
        $inactive_timeout = (int) $inactive_timeout;
        $dryrun = (int) $dryrun;
        
        $now = time();
        $deleted_inputs = 0;
        $deleted_devices = 0;

        // Get all devices for the user
        $result = $this->mysqli->query("SELECT `id`,`userid`,`nodeid`,`name`,`description`,`type`,`devicekey` FROM device WHERE userid = '$userid'");
        
        while ($row = $result->fetch_object()) {
            $device_id = $row->id;
            $nodeid = $row->nodeid;
            
            // Fetch all inputs for this device and load their times once
            $inputs = array();
            $configured_inputs = array();
            $unconfigured_inputs = array();
            
            if ($result2 = $this->mysqli->query("SELECT * FROM input WHERE `userid` = '$userid' AND `nodeid` = '$nodeid'")) {
                while ($row2 = $result2->fetch_object()) {
                    // Get input time once and store it with the input object
                    $input_time = 0;
                    if ($this->redis) {
                        $input_time = $this->redis->hget("input:lastvalue:".$row2->id, 'time');
                        if (!$input_time) $input_time = 0;
                    } else {
                        // Fallback to database time if no Redis
                        $input_time = $row2->time ? $row2->time : 0;
                    }
                    $row2->input_time = $input_time; // Store time with the input object
                    
                    $inputs[] = $row2;
                    
                    // Separate configured from unconfigured inputs
                    if ($row2->processList != NULL && $row2->processList != '') {
                        $configured_inputs[] = $row2;
                    } else {
                        $unconfigured_inputs[] = $row2;
                    }
                }
            }
            
            $inputs_to_delete = array();
            
            if (count($configured_inputs) > 0) {
                // Strategy for devices WITH configured inputs:
                // Find the most recent activity time among configured inputs
                $most_recent_time = 0;
                foreach ($configured_inputs as $input) {
                    $most_recent_time = max($most_recent_time, $input->input_time);
                }
                
                // Check unconfigured inputs against configured input activity
                foreach ($unconfigured_inputs as $input) {
                    // Mark for deletion if inactive relative to configured input activity
                    if (($most_recent_time - $input->input_time) > $inactive_timeout) {
                        $inputs_to_delete[] = $input;
                    }
                }
            } else {
                // Strategy for devices with NO configured inputs:
                // Check all inputs against current time
                foreach ($inputs as $input) {
                    // Mark for deletion if inactive relative to current time
                    if (($now - $input->input_time) > $inactive_timeout) {
                        $inputs_to_delete[] = $input;
                    }
                }
            }
            
            // Delete the identified inputs
            foreach ($inputs_to_delete as $input) {
                if (!$dryrun) {
                    $this->mysqli->query("DELETE FROM input WHERE userid = '$userid' AND id = '".$input->id."'");
                    if ($this->redis) {
                        $this->redis->del("input:".$input->id);
                        $this->redis->srem("user:inputs:$userid", $input->id);
                    }
                }
                $deleted_inputs++;
            }
            
            // If we deleted all inputs for this device, delete the device too
            if (count($inputs_to_delete) === count($inputs) && count($inputs) > 0) {
                if (!$dryrun) {
                    $this->delete($device_id);
                }
                $deleted_devices++;
            }
        }
        
        // Return appropriate message
        $input_text = ($deleted_inputs === 1) ? "input" : "inputs";
        $device_text = ($deleted_devices === 1) ? "device" : "devices";
        
        if ($dryrun) {
            if ($deleted_devices > 0) {
                return "DRYRUN: $deleted_inputs inactive $input_text would be deleted and $deleted_devices $device_text";
            } else {
                return "DRYRUN: $deleted_inputs inactive $input_text would be deleted";
            }
        } else {
            if ($deleted_devices > 0) {
                return "Deleted $deleted_inputs inactive $input_text and $deleted_devices $device_text";
            } else {
                return "Deleted $deleted_inputs inactive $input_text";
            }
        }
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
            if (preg_replace('/[^\p{N}\p{L}_\s\-:.]/u','',$fields->name)!=$fields->name) return array('success'=>false, 'message'=>'invalid characters in device name');
            $stmt = $this->mysqli->prepare("UPDATE device SET name = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->name,$id);
            if ($stmt->execute()) {
                if ($this->redis) {
                    $this->redis->hSet("device:".$id,"name",$fields->name);
                }
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->description)) {
            if (preg_replace('/[^\p{N}\p{L}_\s\-:.]/u','',$fields->description)!=$fields->description) return array('success'=>false, 'message'=>'invalid characters in device description');
            $stmt = $this->mysqli->prepare("UPDATE device SET description = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->description,$id);
            if ($stmt->execute()) {
                if ($this->redis) {
                    $this->redis->hSet("device:".$id,"description",$fields->description);
                }
            } else $success = false;
            $stmt->close();
        }

        if (isset($fields->nodeid)) {
            if (preg_replace('/[^\p{N}\p{L}_\s\-:.]/u','',$fields->nodeid)!=$fields->nodeid) return array('success'=>false, 'message'=>'invalid characters in device nodeid');
            $stmt = $this->mysqli->prepare("UPDATE device SET nodeid = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->nodeid,$id);
            if ($stmt->execute()) {
                if ($this->redis) {
                    $this->redis->hSet("device:".$id,"nodeid",$fields->nodeid);
                }
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->type)) {
            if (preg_replace('/[^\/\|\,\w\s\-:.]/','',$fields->type)!=$fields->type) return array('success'=>false, 'message'=>'invalid characters in device type');
            $stmt = $this->mysqli->prepare("UPDATE device SET type = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->type,$id);
            if ($stmt->execute()) {
                if ($this->redis) {
                    $this->redis->hSet("device:".$id,"type",$fields->type);
                }
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
                if ($this->redis) {
                    $this->redis->hSet("device:".$id,"devicekey",$fields->devicekey);
                }
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->ip)) {
            if (preg_replace('/[^a-zA-Z0-9\.\-:]/', '', $fields->ip)!=$fields->ip) return array('success'=>false, 'message'=>'invalid characters in device ip');
            $stmt = $this->mysqli->prepare("UPDATE device SET ip = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->ip,$id);
            if ($stmt->execute()) {
                if ($this->redis) {
                    $this->redis->hSet("device:".$id,"ip",$fields->ip);
                }
            } else $success = false;
            $stmt->close();
        }

        if ($success) {
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }
    
    public function generate_devicekey() {
        return generate_secure_key(16);
    }

    public function set_new_devicekey($id) {
        $id = intval($id);
        if (!$this->exist($id)) {
            if (!$this->redis || !$this->load_device_to_redis($id)) {
                return array('success'=>false, 'message'=>'Device does not exist');
            }
        }
        
        $devicekey = $this->generate_devicekey();
        
        $stmt = $this->mysqli->prepare("UPDATE device SET devicekey = ? WHERE id = ?");
        $stmt->bind_param("si",$devicekey,$id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            if ($this->redis) {
                $this->redis->hSet("device:".$id,"devicekey",$devicekey);
            }
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
        if (empty($this->templates)) { // Cache it now
            $this->load_template_list();
        }
        $templates = $this->templates;
        ksort($templates);
        return $templates;
    }

    private function get_template_meta($id) {
        if (empty($this->templates)) { // Cache it now
            $this->load_template_list();
        }
        if(isset($this->templates[$id])) {
            return $this->templates[$id];
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
        // Device ID
        $id = intval($id);

        // Fetch device info: userid, nodeid, name, description, type, devicekey
        $device = $this->get($id);
        
        // If device type is present fetch the associated template
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {

            // Returns template meta information
            // e.g. module, name, category, group, description, control
            $result = $this->get_template_meta($device['type']);
            if (isset($result["success"]) && $result["success"] == false) {
                return $result;
            }
            $module = $result['module'];

            // This is typically called here as get_module_class('device', 'template')
            // returning the device_template.php class
            // implementation supports greater modularity but is not in use?
            $class = $this->get_module_class($module, self::TEMPLATE);
            if ($class != null) {
                return $class->prepare_template($device);
            }
            return array('success'=>false, 'message'=>'Device template class is not defined');
        }
        return array('success'=>false, 'message'=>'Device type not specified');
    }

    public function prepare_custom_template($id, $template = false) {
        // Device ID
        $id = intval($id);

        if ($template === false) {
            return array('success'=>false, 'message'=>'No template provided');
        }
        
        $template = json_decode($template);
        if ($template === null) {
            return array('success'=>false, 'message'=>'Invalid template JSON provided');
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('success'=>false, 'message'=>'JSON error: '.json_last_error_msg());
        }

        // Fetch device info: userid, nodeid, name, description, type, devicekey
        $device = $this->get($id);
        
        // This is typically called here as get_module_class('device', 'template')
        // returning the device_template.php class
        // implementation supports greater modularity but is not in use?
        $class = $this->get_module_class('device', self::TEMPLATE);
        if ($class != null) {
            return $class->prepare_template($device, $template);
        }
        return array('success'=>false, 'message'=>'Device template class is not defined');
    }

    /**
     * Initialize a device with a template
     * @param int $id Device ID
     * @param string|false $template JSON encoded template with what actions to perform
     * @return array Result of the initialization
     */
    public function init($id, $template) {
        // Device ID
        $id = intval($id);
        
        // Fetch device info: userid, nodeid, name, description, type, devicekey
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
            // Returns template meta information
            // e.g. module, name, category, group, description, control
            $result = $this->get_template_meta($device['type']);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $module = $result['module'];
            // This is typically called here as get_module_class('device', 'template')
            // returning the device_template.php class
            // implementation supports greater modularity but is not in use?
            $class = $this->get_module_class($module, self::TEMPLATE);
            if ($class != null) {
                return $class->init_template($device, $template);
            }
            return array('success'=>false, 'message'=>'Device template class is not defined');
        }
        return array('success'=>false, 'message'=>'Device type not specified');
    }

    public function init_custom_template($device, $template) {
        if (isset($template) && $template!==false) $template = json_decode($template);
        $class = $this->get_module_class('device', self::TEMPLATE);
        return $class->init_template($device, $template);
    }

    public function generate_template($id) {
        // Device ID
        $id = intval($id);
        
        // Fetch device info: userid, nodeid, name, description, type, devicekey
        $device = $this->get($id);
        
        // Only available with device template for now?
        $module = "device"; 

        // This is typically called here as get_module_class('device', 'template')
        // returning the device_template.php class
        // implementation supports greater modularity but is not in use?
        $class = $this->get_module_class($module, self::TEMPLATE);
        return $class->generate_template($device);
    }

    private function load_template_list() {

        $this->templates = array();
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
        
        $this->templates[$id] = $meta;
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
