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

class DeviceTemplate
{
    protected $mysqli;
    protected $redis;
    protected $log;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        $this->mysqli = &$parent->mysqli;
        $this->redis = &$parent->redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function get_template_list($userid) {
        return $this->load_template_list($userid);
    }

    protected function load_template_list($userid) {
        $list = array();        
        
        $iti = new RecursiveDirectoryIterator("Modules/device/data");
        foreach(new RecursiveIteratorIterator($iti) as $file){
            if(strpos($file ,".json") !== false){
                $content = json_decode(file_get_contents($file));
                $list[basename($file, ".json")] = $content;
            }
        }
        return $list;
    }

    public function get_template($userid, $type) {
        $type = preg_replace('/[^\p{L}_\p{N}\s-:]/u','', $type);
        $list = $this->load_template_list();
        if (!isset($list[$type])) {
            return array('success'=>false, 'message'=>'Device template "'.$type.'" not found');
        }
        return $list[$type];
    }

    public function get_template_options($userid, $type) {
        $result = $this->get_template($userid, $type);
        if (!is_object($result)) {
            return $result;
        }
        
        if (isset($result->options)) {
            return (array) $result->options;
        }
        return array();
    }

    public function prepare_template($device) {
        $userid = intval($device['userid']);
        
        $result = $this->get_template($userid, $device['type']);
        if (!is_object($result)) {
            return $result;
        }
        $prefix = $this->parse_prefix($device['nodeid'], $device['name'], $result->prefix);
        
        if (isset($result->feeds)) {
            $feeds = $result->feeds;
            $this->prepare_feeds($userid, $device['nodeid'], $prefix, $feeds);
        }
        else {
            $feeds = [];
        }
        
        if (isset($result->inputs)) {
            $inputs = $result->inputs;
            $this->prepare_inputs($userid, $device['nodeid'], $prefix, $inputs);
        }
        else {
            $inputs = [];
        }
        
        if (!empty($feeds)) {
            $this->prepare_input_processes($userid, $prefix, $feeds, $inputs);
        }
        if (!empty($inputs)) {
            $this->prepare_feed_processes($userid, $prefix, $feeds, $inputs);
        }
        
        return array('success'=>true, 'feeds'=>$feeds, 'inputs'=>$inputs);
    }

    public function init_template($device, $template) {
        $userid = intval($device['userid']);
        
        if (!is_object($template)) {
            return array('success'=>false, 'message'=>'Invalid device template');
        }
        
        if (isset($template->feeds)) {
            $feeds = $template->feeds;
            $this->create_feeds($userid, $feeds);
        }
        else {
            $feeds = [];
        }
        
        if (isset($template->inputs)) {
            $inputs = $template->inputs;
            $this->create_inputs($userid, $inputs);
        }
        else {
            $inputs = [];
        }
        
        if (!empty($inputs)) {
            $this->create_input_processes($userid, $feeds, $inputs);
        }
        if (!empty($feeds)) {
            $this->create_feed_processes($userid, $feeds, $inputs);
        }
        
        return array('success'=>true, 'message'=>'Device initialized');
    }

    protected function prepare_feeds($userid, $nodeid, $prefix, &$feeds) {
        global $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        foreach($feeds as $f) {
            $f->name = $prefix.$f->name;
            if (!isset($f->tag)) {
                $f->tag = $nodeid;
            }
            
            $feedid = $feed->exists_tag_name($userid, $f->tag, $f->name);
            if ($feedid == false) {
                $f->action = 'create';
                $f->id = -1;
            }
            else {
                $f->action = 'none';
                $f->id = $feedid;
            }
        }
    }

    protected function prepare_inputs($userid, $nodeid, $prefix, &$inputs) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);
        
        foreach($inputs as $i) {
            $i->name = $prefix.$i->name;
            if(!isset($i->node)) {
                $i->node = $nodeid;
            }
            
            $inputid = $input->exists_nodeid_name($userid, $i->node, $i->name);
            if ($inputid == false) {
                $i->action = 'create';
                $i->id = -1;
            }
            else {
                $i->action = 'none';
                $i->id = $inputid;
            }
        }
    }

    // Prepare the input process lists
    protected function prepare_input_processes($userid, $prefix, $feeds, &$inputs) {
        global $user, $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, $feed);
        
        require_once "Modules/process/process_model.php";
        $process = new Process($this->mysqli, $input, $feed, $user->get_timezone($userid));
        $process_list = $process->get_process_list(); // emoncms supported processes
        
        foreach($inputs as $i) {
            // for each input
            if (isset($i->id) && (isset($i->processList) || isset($i->processlist))) {
                $processes = isset($i->processList) ? $i->processList : $i->processlist;
                if (!empty($processes)) {
                    $processes = $this->prepare_processes($prefix, $feeds, $inputs, $processes, $process_list);
                    if (isset($i->action) && $i->action != 'create') {
                        $processes_input = $input->get_processlist($i->id);
                        if (!isset($processes['success'])) {
                            if ($processes_input == '' && $processes != '') {
                                $i->action = 'set';
                            }
                            else if ($processes_input != $processes) {
                                $i->action = 'override';
                            }
                        }
                        else {
                            if ($processes_input == '') {
                                $i->action = 'set';
                            }
                            else {
                                $i->action = 'override';
                            }
                        }
                    }
                }
            }
        }
    }

    // Prepare the feed process lists
    protected function prepare_feed_processes($userid, $prefix, &$feeds, $inputs) {
        global $user, $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, $feed);
        
        require_once "Modules/process/process_model.php";
        $process = new Process($this->mysqli, $input, $feed, $user->get_timezone($userid));
        $process_list = $process->get_process_list(); // emoncms supported processes
        
        foreach($feeds as $f) {
            // for each feed
            if ($f->engine == Engine::VIRTUALFEED && isset($f->id) && (isset($f->processList) || isset($f->processlist))) {
                $processes = isset($f->processList) ? $f->processList : $f->processlist;
                if (!empty($processes)) {
                    $processes = $this->prepare_processes($prefix, $feeds, $inputs, $processes, $process_list);
                    if (isset($f->action) && $f->action != 'create') {
                        $processes_input = $feed->get_processlist($f->id);
                        if (!isset($processes['success'])) {
                            if ($processes_input == '' && $processes != '') {
                                $f->action = 'set';
                            }
                            else if ($processes_input != $processes) {
                                $f->action = 'override';
                            }
                        }
                        else {
                            if ($processes_input == '') {
                                $f->action = 'set';
                            }
                            else {
                                $f->action = 'override';
                            }
                        }
                    }
                }
            }
        }
    }

    // Prepare template processes
    protected function prepare_processes($prefix, $feeds, $inputs, &$processes, $process_list) {
        $process_list_by_name = array();
        foreach ($process_list as $process_id => $process_item) {
            $name = $process_item[2];
            $process_list_by_name[$name] = $process_id;
        }
        $processes_converted = array();
        
        $failed = false;
        foreach($processes as &$process) {
            // If process names are used map to process id
            if (isset($process_list_by_name[$process->process])) $process->process = $process_list_by_name[$process->process];
            
            $process_id = $process->process;
            if (!isset($process_list[$process_id])) {
                $this->log->error("prepare_processes() Process '$process_id' not supported. Module missing?");
                return array('success'=>false, 'message'=>"Process '$process_id' not supported. Module missing?");
            }
            
            $process->name = $process_list[$process->process][0];
            
            // Arguments
            if(isset($process->arguments)) {
                if(isset($process->arguments->type)) {
                    $process->arguments->type = @constant($process->arguments->type); // ProcessArg::
                    $process_type = $process_list[$process_id][1]; // get emoncms process ProcessArg
                    
                    if ($process_type != $process->arguments->type) {
                        $this->log->error("prepare_processes() Bad device template. Missmatch ProcessArg type. Got '$process->arguments->type' expected '$process_type'. process='$process_id'");
                        return array('success'=>false, 'message'=>"Bad device template. Missmatch ProcessArg type. Got '$process->arguments->type' expected '$process_type'. process='$process_id'");
                    }
                    else if ($process->arguments->type === ProcessArg::INPUTID || $process->arguments->type === ProcessArg::FEEDID) {
                        $process->arguments->value = $prefix.$process->arguments->value;
                    }
                    
                    $result = $this->convert_process($feeds, $inputs, $process);
                    if (isset($result["success"])) {
                        $failed = true;
                    }
                    else {
                        $processes_converted[] = $result;
                    }
                }
                else {
                    $this->log->error("prepare_processes() Bad device template. Argument type is missing, set to NONE if not required. process='$process->process' type='".$process->arguments->type."'");
                    return array('success'=>false, 'message'=>"Bad device template. Argument type is missing, set to NONE if not required. process='$process->process' type='".$process->arguments->type."'");
                }
            }
            else {
                $this->log->error("prepare_processes() Bad device template. Missing processList arguments. process='$process->process'");
                return array('success'=>false, 'message'=>"Bad device template. Missing processList arguments. process='$process->process'");
            }
        }
        if (!$failed) {
            return implode(",", $processes_converted);
        }
        return array('success'=>false, 'message'=>"Unable to convert all prepared processes");
    }

    // Create the feeds
    protected function create_feeds($userid, &$feeds) {
        global $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        foreach($feeds as $f) {
            $datatype = constant($f->type); // DataType::
            $engine = constant($f->engine); // Engine::
            $options = new stdClass();
            if (property_exists($f, "interval")) {
                $options->interval = $f->interval;
            }
            
            if ($f->action === 'create') {
                $this->log->info("create_feeds() userid=$userid tag=$f->tag name=$f->name datatype=$datatype engine=$engine");
                
                $result = $feed->create($userid, $f->tag, $f->name, $datatype, $engine, $options);
                if($result["success"] !== true) {
                    $this->log->error("create_feeds() failed for userid=$userid tag=$f->tag name=$f->name datatype=$datatype engine=$engine");
                }
                else {
                    $f->id = $result["feedid"]; // Assign the created feed id to the feeds array
                }
            }
        }
    }

    // Create the inputs
    protected function create_inputs($userid, &$inputs) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);
        
        foreach($inputs as $i) {
            if ($i->action === 'create') {
                $this->log->info("create_inputs() userid=$userid nodeid=$i->node name=$i->name description=$i->description");
                
                $inputid = $input->create_input($userid, $i->node, $i->name);
                if(!$input->exists($inputid)) {
                    $this->log->error("create_inputs() failed for userid=$userid nodeid=$i->node name=$i->name description=$i->description");
                }
                else {
                    $input->set_fields($inputid, '{"description":"'.$i->description.'"}');
                    $i->id = $inputid; // Assign the created input id to the inputs array
                }
            }
        }
    }

    // Create the input process lists
    protected function create_input_processes($userid, $feeds, $inputs) {
        global $user, $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, $feed);
        
        require_once "Modules/process/process_model.php";
        $process = new Process($this->mysqli, $input, $feed, $user->get_timezone($userid));
        $process_list = $process->get_process_list(); // emoncms supported processes
        
        foreach($inputs as $i) {
            if ($i->action !== 'none') {
                if (isset($i->id) && (isset($i->processList) || isset($i->processlist))) {
                    $processes = isset($i->processList) ? $i->processList : $i->processlist;
                    $inputid = $i->id;
                    
                    if (is_array($processes)) {
                        $processes_converted = array();
                        
                        $failed = false;
                        foreach($processes as $process) {
                            $result = $this->convert_process($feeds, $inputs, $process);
                            if (isset($result["success"])) {
                                $failed = true;
                                break;
                            }
                            $processes_converted[] = $result;
                        }
                        $processes = implode(",", $processes_converted);
                        if (!$failed && $processes != "") {
                            $this->log->info("create_inputs_processes() calling input->set_processlist inputid=$inputid processes=$processes");
                            $input->set_processlist($userid, $inputid, $processes, $process_list);
                        }
                    }
                }
            }
        }
    }

    // Create the feed process lists
    protected function create_feed_processes($userid, $feeds, $inputs) {
        global $user, $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, $feed);
        
        require_once "Modules/process/process_model.php";
        $process = new Process($this->mysqli, $input, $feed, $user->get_timezone($userid));
        $process_list = $process->get_process_list(); // emoncms supported processes
        
        foreach($feeds as $f) {
            if ($f->action !== 'none') {
                if ($f->engine == Engine::VIRTUALFEED && isset($f->id) && (isset($f->processList) || isset($f->processlist))) {
                    $processes = isset($f->processList) ? $f->processList : $f->processlist;
                    $feedid = $f->id;
                    
                    if (is_array($processes)) {
                        $processes_converted = array();
                        
                        $failed = false;
                        foreach($processes as $process) {
                            $result = $this->convert_process($feeds, $inputs, $process);
                            if (isset($result["success"])) {
                                $failed = true;
                                break;
                            }
                            $processes_converted[] = $result;
                        }
                        $processes = implode(",", $processes_converted);
                        if (!$failed && $processes != "") {
                            $this->log->info("create_feeds_processes() calling feed->set_processlist feedId=$feedid processes=$processes");
                            $feed->set_processlist($userid, $feedid, $processes, $process_list);
                        }
                    }
                }
            }
        }
    }

    // Converts template process
    protected function convert_process($feeds, $inputs, $process) {
        if (isset($process->arguments->value)) {
            $value = $process->arguments->value;
        }
        else if ($process->arguments->type === ProcessArg::NONE) {
            $value = 0;
        }
        else {
            $this->log->error("convertProcess() Bad device template. Undefined argument value. process='$process->process' type='".$process->arguments->type."'");
            return array('success'=>false, 'message'=>"Bad device template. Undefined argument value. process='$process->process' type='".$process->arguments->type."'");
        }
        
        if ($process->arguments->type === ProcessArg::VALUE) {
        }
        else if ($process->arguments->type === ProcessArg::INPUTID) {
            $temp = $this->search_array($inputs, 'name', $value); // return input array that matches $inputArray[]['name']=$value
            if (isset($temp->id) && $temp->id > 0) {
                $value = $temp->id;
            }
            else {
                $this->log->info("convertProcess() Input name '$value' was not found. process='$process->process' type='".$process->arguments->type."'");
                return array('success'=>false, 'message'=>"Input name '$value' was not found. process='$process->process' type='".$process->arguments->type."'");
            }
        }
        else if ($process->arguments->type === ProcessArg::FEEDID) {
            $temp = $this->search_array($feeds, 'name', $value); // return feed array that matches $feedArray[]['name']=$value
            if (isset($temp->id) && $temp->id > 0) {
                $value = $temp->id;
            }
            else {
                $this->log->info("convertProcess() Feed name '$value' was not found. process='$process->process' type='".$process->arguments->type."'");
                return array('success'=>false, 'message'=>"Feed name '$value' was not found. process='$process->process' type='".$process->arguments->type."'");
            }
        }
        else if ($process->arguments->type === ProcessArg::NONE) {
            $value = "";
        }
        else if ($process->arguments->type === ProcessArg::TEXT) {
        }
        else if ($process->arguments->type === ProcessArg::SCHEDULEID) {
            //not supporte for now
        }
        else {
            $this->log->error("convertProcess() Bad device template. Unsuported argument type. process='$process->process' type='".$process->arguments->type."'");
            return array('success'=>false, 'message'=>"Bad device template. Unsuported argument type. process='$process->process' type='".$process->arguments->type."'");
        }
        
        $this->log->info("convertProcess() process process='$process->process' type='".$process->arguments->type."' value='" . $value . "'");
        return $process->process.":".$value;
    }

    protected function parse_prefix($nodeid, $name, $prefix) {
        if ($prefix === "node") {
            return strtolower($nodeid)."_";
        }
        else if ($prefix === "name") {
            return strtolower($name)."_";
        }
        else return "";
    }

    protected function search_array($array, $key, $val) {
        foreach ($array as $item) {
            if (isset($item->$key) && $item->$key == $val) {
                return $item;
            }
        }
        return null;
    }
}
