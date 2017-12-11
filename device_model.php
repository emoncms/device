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

    public function __construct($mysqli,$redis)
    {
        $this->mysqli = $mysqli;
        $this->redis = $redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function devicekey_session($devicekey)
    {
        // 1. Only allow alphanumeric characters
        // if (!ctype_alnum($devicekey)) return array();
        
        // 2. Only allow 32 character length
        if (strlen($devicekey)!=32) return array();
        
        $session = array();
        $time = time();
        
        //----------------------------------------------------
        // Check for devicekey login
        //----------------------------------------------------
        if($this->redis && $this->redis->exists("device:key:$devicekey"))
        {
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
        else
        {
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

    public function exist($id)
    {
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

    public function exists_name($userid, $name)
    {
        $userid = (int) $userid;
        $name = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$name);
        
        $stmt = $this->mysqli->prepare("SELECT id FROM device WHERE userid=? AND name=?");
        $stmt->bind_param("is",$userid,$name);
        $stmt->execute();
        $stmt->bind_result($id);
        $result = $stmt->fetch();
        $stmt->close();
        
        if ($result && $id>0) return $id; else return false;
    }

    public function exists_nodeid($userid,$nodeid)
    {
        $userid = (int) $userid;
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$nodeid);

        $stmt = $this->mysqli->prepare("SELECT id FROM device WHERE userid=? AND nodeid=?");
        $stmt->bind_param("is",$userid,$nodeid);
        $stmt->execute();
        $stmt->bind_result($id);
        $result = $stmt->fetch();
        $stmt->close();
        
        if ($result && $id>0) return $id; else return false;
    }

    public function get($id)
    {
        $id = (int) $id;
        if (!$this->exist($id) && !$this->load_device_to_redis($id)) {
            return array('success'=>false, 'message'=>'Device does not exist');
        }
        
        if ($this->redis) {
            // Get from redis cache
            $device = (array) $this->redis->hGetAll("device:$id");
            // Verify, if the cached device contains the userid, to avoid compatibility issues
            // with former versions where the userid was not cached.
            if (empty($device['userid'])) {
                $device = $this->load_device_to_redis($id);
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

    public function get_list($userid)
    {
        if ($this->redis) {
            return $this->get_list_redis($userid);
        } else {
            return $this->get_list_mysql($userid);
        }
    }

    private function get_list_redis($userid)
    {
        $userid = (int) $userid;
        
        if (!$this->redis->exists("user:device:$userid")) {
            $this->log->info("Load devices to redis in get_list_redis");
            $this->load_list_to_redis($userid);
        }

        $devices = array();
        $deviceids = $this->redis->sMembers("user:device:$userid");
        foreach ($deviceids as $id)
        {
            $device = $this->redis->hGetAll("device:$id");
            // Verify, if the cached device contains the userid, to avoid compatibility issues
            // with former versions where the userid was not cached.
            if (empty($device['userid'])) {
                $device = $this->load_device_to_redis($id);
            }
            $device['time'] = $this->redis->hget("device:lastvalue:".$id, 'time');
            $devices[] = $device;
        }
        return $devices;
    }

    private function get_list_mysql($userid)
    {
        $userid = (int) $userid;
        
        $devices = array();
        $result = $this->mysqli->query("SELECT `id`, `userid`, `nodeid`, `name`, `description`, `type`, `devicekey`, `time` FROM device WHERE userid = '$userid' ORDER BY nodeid, name asc");
        while ($row = (array) $result->fetch_object()) $devices[] = $row;
        
        return $devices;
    }

    private function load_list_to_redis($userid)
    {
        $userid = (int) $userid;
        $this->redis->delete("user:device:$userid");
        $result = $this->mysqli->query("SELECT `id`, `userid`, `nodeid`, `name`, `description`, `type`, `devicekey` FROM device WHERE userid = '$userid' ORDER BY nodeid, name asc");
        while ($row = $result->fetch_object())
        {
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

    private function load_device_to_redis($id)
    {
        $id = (int) $id;
        $result = $this->mysqli->query("SELECT `id`, `userid`, `nodeid`, `name`, `description`, `type`, `devicekey` FROM device WHERE id = '$id' ORDER BY nodeid, name asc");
        if ($result->num_rows>0) {
            $row = $result->fetch_object();
            $userid = $row->userid;
            
            $device = array(
                'id'=>$row->id,
                'userid'=>$row->userid,
                'nodeid'=>$row->nodeid,
                'name'=>$row->name,
                'description'=>$row->description,
                'type'=>$row->type,
                'devicekey'=>$row->devicekey
            );
            $this->redis->sAdd("user:device:$userid", $row->id);
            $this->redis->hMSet("device:".$row->id, $device);
            return $device;
        }
        return false;
    }

    public function autocreate($userid, $_nodeid, $_type)
    {
        $userid = (int) $userid;
        
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$_nodeid);
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
        if ($result["success"]==true) {
            return $this->init_template($deviceid);
        } else {
            return $result;
        }
    }

    public function create($userid, $nodeid, $name, $description, $type)
    {
        $userid = (int) $userid;
        
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $nodeid);
        
        if (isset($name)) {
            $name = preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $name);
        } else {
            $name = $nodeid;
        }
        
        if (isset($description)) {
            $description= preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $description);
        } else {
            $description = '';
        }
        
        if (isset($type)) {
            $type= preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $type);
        } else {
            $type = '';
        }
        
        if (!$this->exists_nodeid($userid, $nodeid)) {
            $devicekey = md5(uniqid(mt_rand(), true));
            
            $stmt = $this->mysqli->prepare("INSERT INTO device (userid,nodeid,name,description,type,devicekey) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssss",$userid,$nodeid,$name,$description,$type,$devicekey);
            $result = $stmt->execute();
            $stmt->close();
            if (!$result) return array('success'=>false, 'message'=>_("Error creating device"));
            
            $deviceid = $this->mysqli->insert_id;
            
            if ($deviceid > 0) {
                if ($this->redis) {
                    $this->log->info("Load devices to redis in create");
                    $this->load_list_to_redis($userid);
                }
                return $deviceid;
            }
            else return array('success'=>false, 'result'=>"SQL returned invalid insert feed id");
        }
        else return array('success'=>false, 'message'=>'Device already exists');
    }

    public function delete($id)
    {
        $id = (int) $id;
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Device does not exist');
        
        if ($this->redis) {
            $result = $this->mysqli->query("SELECT userid FROM device WHERE `id` = '$id'");
            $row = (array) $result->fetch_object();
        }
        
        $result = $this->mysqli->query("DELETE FROM device WHERE `id` = '$id'");
        if (isset($device_exists_cache[$id])) { unset($device_exists_cache[$id]); } // Clear static cache
        
        if ($this->redis) {
            if (isset($row['userid']) && $row['userid']) {
                $this->redis->delete("device:".$id);
                $this->log->info("Load devices to redis in delete");
                $this->load_list_to_redis($row['userid']);
            }
        }
    }

    public function set_fields($id,$fields)
    {
        $id = (int) $id;
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Device does not exist');
        $fields = json_decode(stripslashes($fields));
        
        $success = true;

        if (isset($fields->name)) {
            if (preg_replace('/[^\p{N}\p{L}_\s-:]/u','',$fields->name)!=$fields->name) return array('success'=>false, 'message'=>'invalid characters in device name');
            $stmt = $this->mysqli->prepare("UPDATE device SET name = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->name,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"name",$fields->name);
            } else $success = false;
            $stmt->close();
        }
        
        if (isset($fields->description)) {
            if (preg_replace('/[^\p{N}\p{L}_\s-:]/u','',$fields->description)!=$fields->description) return array('success'=>false, 'message'=>'invalid characters in device description');
            $stmt = $this->mysqli->prepare("UPDATE device SET description = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->description,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"description",$fields->description);
            } else $success = false;
            $stmt->close();
        }

        if (isset($fields->nodeid)) {
            if (preg_replace('/[^\p{N}\p{L}_\s-:]/u','',$fields->nodeid)!=$fields->nodeid) return array('success'=>false, 'message'=>'invalid characters in device nodeid');
            $stmt = $this->mysqli->prepare("UPDATE device SET nodeid = ? WHERE id = ?");
            $stmt->bind_param("si",$fields->nodeid,$id);
            if ($stmt->execute()) {
                $this->redis->hSet("device:".$id,"nodeid",$fields->nodeid);
            } else $success = false;
            $stmt->close();
        }

        if (isset($fields->type)) {
            if (preg_replace('/[^\/\|\,\w\s-:]/','',$fields->type)!=$fields->type) return array('success'=>false, 'message'=>'invalid characters in device type');
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

        if ($success){
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }
    
    public function set_new_devicekey($id)
    {
        $id = (int) $id;
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Device does not exist');
        
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

    public function get_template_list()
    {
        // This is called when the device view gets reloaded.
        // Always cache and reload all templates here.
        $this->load_template_list();
        
        return $this->get_template_list_meta();
    }
    
    public function get_template_list_full()
    {
        return $this->load_template_list();
    }

    public function get_template_list_meta()
    {
        $templates = array();
        
        if ($this->redis) {
            if (!$this->redis->exists("device:template:keys")) $this->load_template_list();
            
            $keys = $this->redis->sMembers("device:template:keys");
            foreach ($keys as $key)    {
                $template = $this->redis->hGetAll("device:template:$key");
                $template["control"] = (bool) $template["control"];
                $templates[$key] = $template;
            }
        }
        else {
            if (empty($this->templates)) { // Cache it now
                $this->load_template_list();
            }
            $templates = $this->templates;
        }
        return $templates;
    }

    public function get_template($key)
    {
        $template = $this->get_template_meta($key);
        if (isset($template)) {
            $module = $template['module'];
            $class = $this->get_module_class($module);
            if ($class != null) {
                return $class->get_template($key);
            }
        }
        else {
            return array('success'=>false, 'message'=>'Device template does not exist');
        }
        return array('success'=>false, 'message'=>'Unknown error while loading device template details');
    }

    private function get_template_meta($key)
    {
        $template = null;
        
        if ($this->redis) {
            if (!$this->redis->exists("device:template:$key")) {
                $this->load_template_list();
            }
            if ($this->redis->exists("device:template:$key")) {
                $template = $this->redis->hGetAll("device:template:$key");
            }
        }
        else {
            if (empty($this->templates)) { // Cache it now
                $this->load_template_list();
            }
            if (isset($this->templates[$key])) {
                $template = $this->templates[$key];
            }
        }
        return $template;
    }

    public function init_template($id)
    {
        $id = (int) $id;
        
        $device = $this->get($id);
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            $template = $this->get_template_meta($device['type']);
            if (isset($template)) {
                $module = $template['module'];
                $class = $this->get_module_class($module);
                if ($class != null) {
                    return $class->init_template($device['userid'], $device['nodeid'], $device['name'], $device['type']);
                }
            }
            else {
                return array('success'=>false, 'message'=>'Device template does not exist');
            }
        }
        else {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        
        return array('success'=>false, 'message'=>'Unknown error while initializing device');
    }

    private function load_template_list()
    {
        if ($this->redis) {
            $this->redis->delete("device:template:keys");
        }
        else {
            $this->templates = array();
        }
        $templates = array();
        
        $dir = scandir("Modules");
        for ($i=2; $i<count($dir); $i++) {
            if (filetype("Modules/".$dir[$i])=='dir' || filetype("Modules/".$dir[$i])=='link') {
                $class = $this->get_module_class($dir[$i]);
                if ($class != null) {
                    $module_templates = $class->get_template_list();
                    foreach($module_templates as $key => $value) {
                        $this->cache_template($dir[$i], $key, $value);
                        $templates[$key] = $value;
                    }
                }
            }
        }
        
        return $templates;
    }

    private function get_module_class($module)
    {
        /*
         magic function __call (above) MUST BE USED with this.
         Load additional template module files.
         Looks in the folder Modules/modulename/ for a file modulename_template.php
         (module_name all lowercase but class ModulenameTemplate in php file that is CamelCase)
         */
        $module_file = "Modules/".$module."/".$module."_template.php";
        $module_class = null;
        if(file_exists($module_file)){
            require_once($module_file);
            
            $module_class_name = ucfirst(strtolower($module)."Template");
            $module_class = new $module_class_name($this);
        }
        return $module_class;
    }

    private function cache_template($module, $key, $template)
    {
        $meta = array(
            "module"=>$module
        );
        $meta["name"] = ((!isset($template->name) || $template->name == "" ) ? $key : $template->name);
        $meta["category"] = ((!isset($template->category) || $template->category== "" ) ? "General" : $template->category);
        $meta["group"] = ((!isset($template->group) || $template->group== "" ) ? "Miscellaneous" : $template->group);
        $meta["description"] = (!isset($template->description) ? "" : $template->description);
        $meta["control"] = (!isset($template->control) ? false : true);
        
        if ($this->redis) {
            $this->redis->sAdd("device:template:keys", $key);
            $this->redis->hMSet("device:template:$key", $meta);
        }
        else {
            $this->templates[$key] = $meta;
        }
    }
}
