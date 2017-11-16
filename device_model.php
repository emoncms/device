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
        $devicekey = $this->mysqli->real_escape_string($devicekey);
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
            $result = $this->mysqli->query("SELECT id, userid, nodeid FROM device WHERE devicekey='$devicekey'");
            if ($result->num_rows == 1)
            {
                $row = $result->fetch_array();
                if ($row['id'] != 0)
                {
                    $session['userid'] = $row['userid'];
                    $session['read'] = 0;
                    $session['write'] = 1;
                    $session['admin'] = 0;
                    $session['lang'] = "en"; // API access is always in english
                    $session['username'] = "API";
                    $session['deviceid'] = $row['id'];
                    $session['nodeid'] = $row['nodeid'];
                    
                    if ($this->redis) {
                        $this->redis->set("device:key:$devicekey:user",$row['userid']);
                        $this->redis->set("device:key:$devicekey:device",$row['id']);
                        $this->redis->set("device:key:$devicekey:node",$row['nodeid']);
                        $this->redis->hMset("device:lastvalue:".$row['id'], array('time' => $time));
                    } else {
                        //$time = date("Y-n-j H:i:s", $time);
                        $this->mysqli->query("UPDATE device SET time='$time' WHERE id = '".$row['id']."'");
                    }
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
                $id = intval($id);
                $result = $this->mysqli->query("SELECT id FROM device WHERE id = '$id'");
                if ($result->num_rows > 0) $device_exist = true;
            }
            $device_exists_cache[$id] = $device_exist; // Cache it
        }
        return $device_exist;
    }

    public function exists_name($userid, $name)
    {
        $userid = intval($userid);
        $name = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$name);
        $result = $this->mysqli->query("SELECT id FROM device WHERE userid = '$userid' AND name = '$name'");
        if ($result->num_rows>0) { $row = $result->fetch_array(); return $row['id']; } else return false;
    }

    public function exists_nodeid($userid,$nodeid)
    {
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$nodeid);
        $result = $this->mysqli->query("SELECT id FROM device WHERE userid = '$userid' AND nodeid = '$nodeid'");
        
        if (isset($result->num_rows) && $result->num_rows > 0) { 
            $row = $result->fetch_array(); 
            return $row['id']; 
        } else {
            return false;
        }
    }

    public function get($id)
    {
        $id = intval($id);
        if (!$this->exist($id)) $this->load_device_to_redis($id);
        
        if ($this->redis) {
            // Get from redis cache
            $device = $this->redis->hGetAll("device:$id");
        } else {
            // Get from mysql db
            $result = $this->mysqli->query("SELECT `id`, `userid`, `nodeid`, `name`, `description`, `type`, `devicekey`, `time` FROM device WHERE id = '$id'");
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
        $userid = intval($userid);
        
        if (!$this->redis->exists("user:device:$userid")) {
            $this->log->info("Load devices to redis in get_list_redis");
            $this->load_list_to_redis($userid);
        }

        $devices = array();
        $deviceids = $this->redis->sMembers("user:device:$userid");
        foreach ($deviceids as $id)
        {
            $row = $this->redis->hGetAll("device:$id");
            $lastvalue = $this->redis->hMget("device:lastvalue:".$id,array('time'));
            $row['time'] = $lastvalue['time'];
            $devices[] = $row;
        }
        return $devices;
    }

    private function get_list_mysql($userid)
    {
        $userid = intval($userid);
        $devices = array();
        
        $result = $this->mysqli->query("SELECT `id`, `userid`, `nodeid`, `name`, `description`, `type`, `devicekey`, `time` FROM device WHERE userid = '$userid' ORDER BY nodeid, name asc");
        while ($row = (array)$result->fetch_object())
        {
            $devices[] = $row;
        }
        return $devices;
    }

    private function load_list_to_redis($userid)
    {
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
        $this->redis->delete("user:device:$userid");
        $result = $this->mysqli->query("SELECT `id`, `userid`, `nodeid`, `name`, `description`, `type`, `devicekey` FROM device WHERE id = '$id' ORDER BY nodeid, name asc");
        if ($result->num_rows>0) {
            $row = $result->fetch_array();
            $userid = $row->userid;
            
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
            return true;
        }
        return false;
    }

    public function autocreate($userid, $_nodeid, $_type)
    {
        $userid = intval($userid);
        
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
        $userid = intval($userid);
        $nodeid = preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $nodeid);
        if (isset($name)) {
            $name = preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $name);
        }
        else $name = $nodeid;
        
        if (isset($description)) {
            $description= preg_replace('/[^\p{L}_\p{N}\s-:]/u', '', $description);
        }
        else $description = '';
        
        if (!$this->exists_nodeid($userid, $nodeid)) {
            $devicekey = md5(uniqid(mt_rand(), true));
            
            $result = $this->mysqli->query("INSERT INTO device (`userid`, `nodeid`, `name`, `description`, `type`, `devicekey`) VALUES ('$userid','$nodeid','$name','$description','$type','$devicekey')");
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
        else return array('success'=>false, 'message'=>'Device for the node "'.$nodeid.'" already exists');
    }

    public function delete($id)
    {
        $id = intval($id);
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

    public function set_fields($id, $fields)
    {
        $id = intval($id);
        if (!$this->exist($id)) return array('success'=>false, 'message'=>'Device does not exist');
        
        $fields = json_decode(stripslashes($fields));
        
        $array = array();
        
        // Repeat this line changing the field name to add fields that can be updated:
        if (isset($fields->name)) $array[] = "`name` = '".preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$fields->name)."'";
        if (isset($fields->description)) $array[] = "`description` = '".preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$fields->description)."'";
        if (isset($fields->nodeid)) $array[] = "`nodeid` = '".preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$fields->nodeid)."'";
        if (isset($fields->devicekey)) {
            $devicekey = preg_replace('/[^\p{L}_\p{N}\s-:]/u','',$fields->devicekey);
            $result = $this->mysqli->query("SELECT devicekey FROM device WHERE devicekey='$devicekey'");
            if ($result->num_rows > 0)
            {
                return array('success'=>false, 'message'=>'Field devicekey is invalid'); // is duplicate
            }
            $array[] = "`devicekey` = '".$devicekey."'";
        }
        if (isset($fields->type)) $array[] = "`type` = '".preg_replace('/[^\/\|\,\w\s-:]/','',$fields->type)."'";
        
        // Convert to a comma seperated string for the mysql query
        $fieldstr = implode(",",$array);
        $this->mysqli->query("UPDATE device SET ".$fieldstr." WHERE `id` = '$id'");
        
        if ($this->mysqli->affected_rows>0){
            if ($this->redis) {
                $result = $this->mysqli->query("SELECT userid FROM device WHERE id='$id'");
                $row = (array) $result->fetch_object();
                if (isset($row['userid']) && $row['userid']) {
                    $this->load_list_to_redis($row['userid']);
                }
            }
            return array('success'=>true, 'message'=>'Field updated');
        } else {
            return array('success'=>false, 'message'=>'Field could not be updated');
        }
    }

    public function get_template_list()
    {
        return $this->load_modules();
    }

    public function get_template_list_meta()
    {
        $templates = array();
        
        if ($this->redis) {
            if (!$this->redis->exists("device:template:keys")) $this->load_modules();
            
            $keys = $this->redis->sMembers("device:template:keys");
            foreach ($keys as $key)    {
                $template = $this->redis->hGetAll("device:template:$key");
                $template["control"] = (bool) $template["control"];
                $templates[$key] = $template;
            }
        }
        else {
            if (empty($this->templates)) { // Cache it now
                $this->load_modules();
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
                $this->load_modules();
            }
            if ($this->redis->exists("device:template:$key")) {
                $template = $this->redis->hGetAll("device:template:$key");
            }
        }
        else {
            if (empty($this->templates)) { // Cache it now
                $this->load_modules();
            }
            if (isset($this->templates[$key])) {
                $template = $this->templates[$key];
            }
        }
        return $template;
    }

    public function init_template($id, $options)
    {
        $id = intval($id);
        
        if (isset($options)) $options = json_decode($options);
        
        $device = $this->get($id);
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            $template = $this->get_template_meta($device['type']);
            if (isset($template)) {
                $module = $template['module'];
                $class = $this->get_module_class($module);
                if ($class != null) {
                	$result = $class->init_template($device['userid'], $device['nodeid'], $device['name'], $device['type'], $options);
                	if ($template['control'] && isset($result['success']) && $result['success']) {
                		$items = $class->get_control($device['userid'], $device['nodeid'], $device['name'], $device['type'], $options);
                		$this->cache_control($id, $items);
                	}
                	return $result;
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

    public function get_control_list($userid)
    {
        $controls = array();
        
        $devices = $this->get_list($userid);
        foreach ($devices as $device) {
            if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
                $template = $this->get_template_meta($device['type']);
                if (isset($template) && $template['control']) {
                    $controls[] = $this->get_control_values($device);
                }
            }
        }
        return $controls;
    }

    public function get_control($id)
    {
        $id = intval($id);
        $device = $this->get($id);
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            return $this->get_control_values($device);
        }
        else {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        
        return array('success'=>false, 'message'=>'Unknown error while getting device control value');
    }

    private function get_control_values($device)
    {
        $id = intval($device['id']);
        
        $control = array(
                'id' => $device['id'],
                'userid' => $device['userid'],
                'nodeid' => $device['nodeid'],
                'name' => $device['name'],
                'description' => $device['description'],
                'type' => $device['type']
        );
        
        $items = $this->get_control_items($device['id'], $device['userid'], $device['nodeid'], $device['name'], $device['type']);
        if (isset($items)) {
            $control['control'] = array();
            foreach ($items as $item) {
                $control['control'][] = $this->get_control_value($item);
            }
        }
        return $control;
    }

    private function get_control_value($item)
    {
        $control = array(
            'id' => $item['id'],
            'type' => $item['type'],
            'label' => $item['label']
        );
        if (isset($item['format'])) $control['format'] = $item['format'];
        if (isset($item['max'])) $control['max'] = $item['max'];
        if (isset($item['min'])) $control['min'] = $item['min'];
        
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
        $control['value'] = $value;
        
        return $control;
    }

    private function get_control_items($id, $userid, $nodeid, $name, $type)
    {
        $items = null;
        if ($this->redis) {
            if ($this->redis->exists("device:control:$id")) {
                $items = array();
                
                $itemids = $this->redis->sMembers("device:control:$id");
                foreach ($itemids as $i) {
                    $item = (array) $this->redis->hGetAll("device:".$id.":control:".$i);
                    if (isset($item['mapping'])) {
                        $item['mapping'] = json_decode($item['mapping']);
                    }
                    $items[] = $item;
                }
            }
        }
        else if (!empty($this->controls) && isset($this->controls[$id])) {
            $items = array();
            foreach ($this->controls[$id] as $item) {
                $items[] = $item;
            }
        }
        if (empty($items)) {
            $template = $this->get_template_meta($type);
            if (isset($template) && $template['control']) {
                $module = $template['module'];
                $class = $this->get_module_class($module);
                if ($class != null) {
                    $items = $class->get_control($userid, $nodeid, $name, $type, null);
                    $this->cache_control($id, $items);
                }
            }
            else {
                return array('success'=>false, 'message'=>'Device control does not exist');
            }
        }
        return $items;
    }

    public function get_control_item($id, $itemid)
    {
        $id = intval($id);
        
        $item = null;
        if ($this->redis) {
            if ($this->redis->exists("device:control:$id")) {
                $item = (array) $this->redis->hGetAll("device:".$id.":control:".$itemid);
                if (isset($item['mapping'])) {
                    $item['mapping'] = json_decode($item['mapping']);
                }
            }
        }
        else if (!empty($this->controls) && isset($this->controls[$id])) {
            $item = $this->controls[$id][$itemid];
        }
        if (empty($item)) {
            $device = $this->get($id);
            if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
                $template = $this->get_template_meta($device['type']);
                if (isset($template) && $template['control']) {
                    $module = $template['module'];
                    $class = $this->get_module_class($module);
                    if ($class != null) {
                        $items = $class->get_control($device['userid'], $device['nodeid'], $device['name'], $device['type'], null);
                        return array('success'=>false, 'message'=>$items);
                        $this->cache_control($id, $items);
                        foreach ($items as $i) {
                            if ($i['id'] == $itemid) $item = $i;
                        }
                    }
                }
                else {
                    return array('success'=>false, 'message'=>'Device control does not exist');
                }
            }
            else {
                return array('success'=>false, 'message'=>'Device type not specified');
            }
        }
        if (empty($item)) {
            return array('success'=>false, 'message'=>'Unknown error retrieving device item');
        }
        return $item;
    }

    public function set_control_on($id, $itemid)
    {
        $id = intval($id);
        $item = $this->get_control_item($id, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $map = (array) $item['mapping'];
            if (isset($map['ON'])) {
                $options = (array) $map['ON'];
                
                $channelid = $options['channelid'];
                unset($options['channelid']);
                $value = $options['value'];
                unset($options['value']);
                
                return $this->set_control_value($id, $channelid, $options, $value);
            }
        }
        return array('success'=>false, 'message'=>'Unknown device control item or incomplete device control template mappings "ON"');
    }

    public function set_control_off($id, $itemid)
    {
        $id = intval($id);
        $item = $this->get_control_item($id, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $map = (array) $item['mapping'];
            if (isset($map['OFF'])) {
                $options = (array) $map['OFF'];
                
                $channelid = $options['channelid'];
                unset($options['channelid']);
                $value = $options['value'];
                unset($options['value']);
                
                return $this->set_control_value($id, $channelid, $options, $value);
            }
        }
        return array('success'=>false, 'message'=>'Unknown device control item or incomplete device control template mappings "OFF"');
    }

    public function toggle_control_value($id, $itemid)
    {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Device control "toggle" not implemented yet');
    }

    public function increase_control_value($id, $itemid)
    {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Device control "increase" not implemented yet');
    }

    public function decrease_control_value($id, $itemid)
    {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Device control "decrease" not implemented yet');
    }

    public function set_control_percent($id, $itemid, $value)
    {
        $id = intval($id);
        
        return array('success'=>false, 'message'=>'Device control "percent" not implemented yet');
    }

    public function set_control_value($id, $channelid, $options, $value)
    {
        $id = intval($id);
        $device = $this->get($id);
        if (isset($device['type']) && $device['type'] != 'null' && $device['type']) {
            $template = $this->get_template_meta($device['type']);
            if (isset($template) && $template['control']) {
                $module = $template['module'];
                $class = $this->get_module_class($module);
                if ($class != null) {
                    return $class->set_control($channelid, $options, $value);
                }
            }
            else {
                return array('success'=>false, 'message'=>'Device control does not exist');
            }
        }
        else {
            return array('success'=>false, 'message'=>'Device type not specified');
        }
        
        return array('success'=>false, 'message'=>'Unknown error while setting device value');
    }

    private function load_modules()
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
        $module_file = "Modules/".$module."/".$module."_control.php";
        $module_class = null;
        if(file_exists($module_file)){
            require_once($module_file);
            
            $module_class_name = ucfirst(strtolower($module)."Control");
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

    private function cache_control($id, $control)
    {
        if ($this->redis) {
            $this->redis->delete("device:control:$id");
            
            foreach ($control as $value) {
                if (isset($value['mapping'])) $value['mapping'] = json_encode($value['mapping']);
                $itemid = $value['id'];
                $this->redis->sAdd("device:control:$id", $itemid);
                $this->redis->hMSet("device:$id:control:$itemid", $value);
            }
        }
        else {
            if (empty($this->controls)) {
                $this->controls = array();
                
            }
            $items = array();
            foreach ($control as $value) {
                $itemid = $value['id'];
                $items[$itemid] = $value;
            }
            
            $this->controls[$id] = $items;
        }
    }
}
