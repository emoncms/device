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
    
    protected $feed;
    protected $input;
    protected $process; 

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        $this->mysqli = &$parent->mysqli;
        $this->redis = &$parent->redis;
        $this->log = new EmonLogger(__FILE__);

        global $user,$settings;
        
        require_once "Modules/feed/feed_model.php";
        $this->feed = new Feed($this->mysqli, $this->redis, $settings['feed']);
        
        require_once "Modules/input/input_model.php";
        $this->input = new Input($this->mysqli, $this->redis, $this->feed);
        
        require_once "Modules/process/process_model.php";
        $this->process = new Process($this->mysqli, $this->input, $this->feed,"UTC");
    }

    public function get_template_list() {
        return $this->load_template_list();
    }

    protected function load_template_list() {
        $list = array();        
        
        $iti = new RecursiveDirectoryIterator("Modules/device/data");
        foreach(new RecursiveIteratorIterator($iti) as $splinfo ){
            $name = $splinfo->getFilename();
            $file = $splinfo->getPathname();
            if(strlen($name) > 5 && $name[0] != '.' &&
                    substr_compare ( $name, '.json', -5 ) === 0) {
                $content = json_decode(file_get_contents($file));
                if (json_last_error() != 0) {
                    return array('success'=>false, 'message'=>"Error reading file $file: ".json_last_error_msg());
                }
                $list[basename($file, ".json")] = $content;
            }
        }
        return $list;
    }

    public function get_template($type) {
        $type = preg_replace('/[^\p{L}_\p{N}\s\-:]/u','', $type);
        $result = $this->load_template_list();
        if (isset($result['success']) && $result['success'] == false) {
            return $result;
        }
        if (!isset($result[$type])) {
            return array('success'=>false, 'message'=>'Device template "'.$type.'" not found');
        }
        return $result[$type];
    }


    public function prepare_template($device, $template = false) {
        $userid = intval($device['userid']);
        
        if ($template == false) {
            // Returns full template object from data/...
            $result = $this->get_template($device['type']);
            if (!is_object($result)) {
                return $result;
            }
        } else {
            $result = $template;
        }

        // Option not currently in use, $prefix is typically empty ""
        // but can be set to "node" or "name" to prefix feeds and inputs
        $prefix = $this->parse_prefix($device['nodeid'], $device['name'], $result);

        if (isset($result->feeds)) {
            // Get template feed list
            $feeds = $result->feeds;
            // Works out if the feeds already exist or if they need to be created
            // if they do exist feed id's are returned and the action is set to 'none'
            // if they do not exist the action is set to 'create' and id is set to -1
            // alongside original object from the template
            $this->prepare_feeds($userid, $device['nodeid'], $prefix, $feeds);
        }
        else {
            $feeds = array();
        }
        
        if (isset($result->inputs)) {
            $inputs = $result->inputs;
            // As above works out if the inputs already exist or if they need to be created
            // if they do exist input id's are returned and the action is set to 'none'
            // if they do not exist the action is set to 'create' and id is set to -1
            $this->prepare_inputs($userid, $device['nodeid'], $prefix, $inputs);
        }
        else {
            $inputs = array();
        }

        // We now know what if we have inputs and feeds that match the template or if they need to be created
        // Next we assess the processes that need to be created or updated
        
        if (!empty($feeds)) {
            $this->prepare_feed_processes($userid, $prefix, $feeds, $inputs);
        }
        if (!empty($inputs)) {
            $this->prepare_input_processes($userid, $prefix, $feeds, $inputs);
        }
        
        return array('success'=>true, 'feeds'=>$feeds, 'inputs'=>$inputs);
    }

    public function init_template($device, $template) {
        $userid = intval($device['userid']);
        
        // If template is not set, prepare it from the device meta
        if (empty($template)) {
            $result = $this->prepare_template($device);
            if (isset($result['success']) && $result['success'] == false) {
                return $result;
            }
            $template = $result;
        }
        if (!is_object($template)) $template = (object) $template;
        
        if (isset($template->feeds)) {
            $feeds = $template->feeds;
            $this->create_feeds($userid, $feeds);
        }
        else {
            $feeds = array();
        }
        
        if (isset($template->inputs)) {
            $inputs = $template->inputs;
            $this->create_inputs($userid, $inputs);
        }
        else {
            $inputs = array();
        }
        
        if (!empty($feeds)) {
            $this->create_feed_processes($userid, $feeds, $inputs);
        }
        if (!empty($inputs)) {
            $this->create_input_processes($userid, $feeds, $inputs);
        }
        
        return array('success'=>true, 'message'=>'Device initialized');
    }

    protected function prepare_feeds($userid, $nodeid, $prefix, &$feeds) {

        foreach($feeds as $f) {
            $f->name = $prefix.$f->name;
            if (!isset($f->tag)) {
                $f->tag = $nodeid;
            }
            
            $feedid = $this->feed->exists_tag_name($userid, $f->tag, $f->name);
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
        
        foreach($inputs as $i) {
            $i->name = $prefix.$i->name;
            if(!isset($i->node)) {
                $i->node = $nodeid;
            }
            
            $inputid = $this->input->exists_nodeid_name($userid, $i->node, $i->name);
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

        $process_list = $this->process->get_process_list(); // emoncms supported processes
        
        foreach($inputs as $i) {
            // for each input
            if (isset($i->id) && (isset($i->processList) || isset($i->processlist))) {
                // Get the process list from the input (object)
                $processes = isset($i->processList) ? $i->processList : $i->processlist;
                if (!empty($processes)) {
                    $processes = $this->prepare_processes($prefix, $feeds, $inputs, $processes, $process_list);

                    if (isset($i->action) && $i->action != 'create') {
                        $processes_input = $this->input->get_processlist($i->id);
                        $decoded_process_input = $this->process->decode_processlist($processes_input);

                        if (!isset($processes['success'])) {
                            if (count($decoded_process_input) == 0 && count($processes) > 0) {
                                $i->action = 'set';
                            }
                            else if (json_encode($decoded_process_input) != json_encode($processes)) {
                                $i->action = 'override';
                            }
                        }
                        else {
                            if (count($decoded_process_input) == 0) {
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
                
        $process_list = $this->process->get_process_list(); // emoncms supported processes
        
        foreach($feeds as $f) {
            // for each feed
            if ($f->engine == Engine::VIRTUALFEED && isset($f->id) && (isset($f->processList) || isset($f->processlist))) {
                $processes = isset($f->processList) ? $f->processList : $f->processlist;
                if (!empty($processes)) {
                    $processes = $this->prepare_processes($prefix, $feeds, $inputs, $processes, $process_list);
                    if (isset($f->action) && $f->action != 'create') {
                        $processes_feed = $this->feed->get_processlist($f->id);
                        $decoded_process_feed = $this->process->decode_processlist($processes_feed);

                        if (!isset($processes['success'])) {
                            if (count($decoded_process_feed) == 0 && count($processes) > 0) {
                                $f->action = 'set';
                            }
                            else if (json_encode($decoded_process_feed) != json_encode($processes)) {
                                $f->action = 'override';
                            }
                        }
                        else {
                            if (count($decoded_process_feed) == 0) {
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
        $process_list_by_func = array();
        foreach ($process_list as $process_id => $process_item) {
            $func = $process_item['function'];
            $process_list_by_func[$func] = $process_id;
        }
        $processes_converted = array();
        
        $failed = false;
        foreach($processes as &$process) {
            // If process names are used map to process id
            if (isset($process_list_by_func[$process->process])) $process->process = $process_list_by_func[$process->process];
            
            if (!isset($process_list[$process->process])) {
                $this->log->error("prepare_processes() Process '$process->process' not supported. Module missing?");
                return array('success'=>false, 'message'=>"Process '$process->process' not supported. Module missing?");
            }
            $process->name = $process_list[$process->process]['name'];
            $process->short = $process_list[$process->process]['short'];
            
            // Arguments
            if(isset($process->arguments)) {
                // Normalize arguments to always be an array of argument objects
                if (!is_array($process->arguments)) {
                    $process->arguments = [$process->arguments];
                }

                $process_args = isset($process_list[$process->process]['args']) ? $process_list[$process->process]['args'] : array();

                // Check all arguments
                foreach ($process->arguments as $idx => $argument) {
                    if (!isset($argument->type)) {
                        $this->log->error("prepare_processes() Bad device template. Argument type is missing at index $idx, set to NONE if not required. process='$process->process'");
                        return array('success'=>false, 'message'=>"Bad device template. Argument type is missing at index $idx, set to NONE if not required. process='$process->process'");
                    }
                    $argument->type = @constant($argument->type); // ProcessArg::

                    $expected_type = isset($process_args[$idx]['type']) ? $process_args[$idx]['type'] : ProcessArg::NONE;
                    if ($argument->type !== $expected_type) {
                        $this->log->error("prepare_processes() Bad device template. Mismatch ProcessArg type at index $idx. Got '{$argument->type}' expected '$expected_type'. process='$process->process'");
                        return array('success'=>false, 'message'=>"Bad device template. Mismatch ProcessArg type at index $idx. Got '{$argument->type}' expected '$expected_type'. process='$process->process'");
                    }

                    // Add prefix if applicable for INPUTID or FEEDID
                    if ($argument->type === ProcessArg::INPUTID || $argument->type === ProcessArg::FEEDID) {
                        $argument->value = $prefix . $argument->value;
                    }
                }

                $result = $this->convert_process($feeds, $inputs, $process, $process_list);

                if (isset($result['success'])) {
                    $failed = true;
                }
                else {
                    $processes_converted[] = $result;
                }
            }
            else {
                $this->log->error("prepare_processes() Bad device template. Missing processList arguments. process='$process->process'");
                return array('success'=>false, 'message'=>"Bad device template. Missing processList arguments. process='$process->process'");
            }
        }
        if (!$failed) {
            return $processes_converted;
        }
        return array('success'=>false, 'message'=>"Unable to convert all prepared processes");
    }

    // Create the feeds
    protected function create_feeds($userid, &$feeds) {
        
        foreach($feeds as $f) {
            $engine = constant($f->engine); // Engine::
            if (isset($f->unit)) $unit = $f->unit; else $unit = "";
            
            $options = new stdClass();
            if (property_exists($f, "interval")) {
                $options->interval = $f->interval;
            }
            
            if ($f->action === 'create') {
                $this->log->info("create_feeds() userid=$userid tag=$f->tag name=$f->name engine=$engine unit=$unit");
                
                $result = $this->feed->create($userid,$f->tag,$f->name,$engine,$options,$unit);
                if($result['success'] !== true) {
                    $this->log->error("create_feeds() failed for userid=$userid tag=$f->tag name=$f->name engine=$engine unit=$unit");
                }
                else {
                    $f->id = $result["feedid"]; // Assign the created feed id to the feeds array
                }
            }
        }
    }

    // Create the inputs
    protected function create_inputs($userid, &$inputs) {
        
        foreach($inputs as $i) {
            if ($i->action === 'create') {
                $this->log->info("create_inputs() userid=$userid nodeid=$i->node name=$i->name description=$i->description");
                
                $inputid = $this->input->create_input($userid, $i->node, $i->name);
                if(!$this->input->exists($inputid)) {
                    $this->log->error("create_inputs() failed for userid=$userid nodeid=$i->node name=$i->name description=$i->description");
                }
                else {
                    $this->input->set_fields($inputid, '{"description":"'.$i->description.'"}');
                    $i->id = $inputid; // Assign the created input id to the inputs array
                }
            }
        }
    }

    // Create the input process lists
    protected function create_input_processes($userid, $feeds, $inputs) {
        $process_list = $this->process->get_process_list(); // emoncms supported processes

        foreach($inputs as $i) {
            if ($i->action !== 'none') {
                if (isset($i->id) && (isset($i->processList) || isset($i->processlist))) {
                    $processes = isset($i->processList) ? $i->processList : $i->processlist;
                    $inputid = $i->id;

                    if (is_array($processes)) {
                        $processes_converted = array();
                        $failed = false;
                        foreach($processes as $process) {
                            $result = $this->convert_process($feeds, $inputs, $process, $process_list);

                            if (isset($result['success']) && !$result['success']) {
                                $failed = true;
                                break;
                            }
                            $processes_converted[] = $result;
                        }

                        if (!$failed && count($processes_converted) > 0) {
                            $result = $this->input->set_processlist($userid, $inputid, json_encode($processes_converted), $this->process);
                            if (isset($result['encoded_processlist'])) {
                                $this->log->info("create_inputs_processes() set_processlist inputId=$inputid processes=".$result['encoded_processlist']);
                            }
                        }
                    }
                }
            }
        }
    }

    // Create the feed process lists
    protected function create_feed_processes($userid, $feeds, $inputs) {
        
        $process_list = $this->process->get_process_list(); // emoncms supported processes
        
        foreach($feeds as $f) {
            if ($f->action !== 'none') {
                if ($f->engine == Engine::VIRTUALFEED && isset($f->id) && (isset($f->processList) || isset($f->processlist))) {
                    $processes = isset($f->processList) ? $f->processList : $f->processlist;
                    $feedid = $f->id;

                    if (is_array($processes)) {
                        $processes_converted = array();

                        $failed = false;
                        foreach($processes as $process) {
                            $result = $this->convert_process($feeds, $inputs, $process, $process_list);
                            if (isset($result['success']) && !$result['success']) {
                                $failed = true;
                                break;
                            }
                            // $result should be an array: ['fn' => ..., 'args' => [...]]
                            $processes_converted[] = $result;
                        }
                        if (!$failed && count($processes_converted) > 0) {
                            $result = $this->feed->set_processlist($userid, $feedid, json_encode($processes_converted), $this->process);
                            if (isset($result['encoded_processlist'])) {
                                $this->log->info("create_feed_processes() set_processlist inputId=$feedid processes=".$result['encoded_processlist']);
                            }
                        }
                    }
                }
            }
        }
    }

    // Converts template process
    protected function convert_process($feeds, $inputs, $process, $process_list) {

        // Normalize arguments to always be an array of argument objects
        $arguments = $process->arguments;
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        $args = [];
        foreach ($arguments as $argument) {
            if (isset($argument->value)) {
                $value = $argument->value;
            }
            else if ($argument->type === ProcessArg::NONE) {
                $value = 0;
            }
            else {
                $this->log->error("convertProcess() Bad device template. Undefined argument value. process='$process->process' type='".$argument->type."'");
                return array('success'=>false, 'message'=>"Bad device template. Undefined argument value. process='$process->process' type='".$argument->type."'");
            }

            if ($argument->type === ProcessArg::VALUE) {
                // No conversion needed
            }
            else if ($argument->type === ProcessArg::INPUTID) {
                $temp = $this->search_array($inputs, 'name', $value);
                if (isset($temp->id) && $temp->id > 0) {
                    $value = $temp->id;
                }
                else {
                    $this->log->info("convertProcess() Input name '$value' was not found. process='$process->process' type='".$argument->type."'");
                    return array('success'=>false, 'message'=>"Input name '$value' was not found. process='$process->process' type='".$argument->type."'");
                }
            }
            else if ($argument->type === ProcessArg::FEEDID) {
                $temp = $this->search_array($feeds, 'name', $value);
                if (isset($temp->id) && $temp->id > 0) {
                    $fget = $this->feed->get((int)$temp->id);
                    if (isset($fget['engine']) && $fget['engine']!=Engine::VIRTUALFEED) {
                        $value = $temp->id;
                    } else {
                        $this->log->error("convertProcess() Could not link virtual feed '$value'. process='$process->process' type='".$argument->type."'");
                        return array('success'=>false, 'message'=>"Could not link virtual feed '$value'. process='$process->process' type='".$argument->type."'");
                    }
                }
                else {
                    $this->log->info("convertProcess() Feed name '$value' was not found. process='$process->process' type='".$argument->type."'");
                    return array('success'=>false, 'message'=>"Feed name '$value' was not found. process='$process->process' type='".$argument->type."'");
                }
            }
            else if ($argument->type === ProcessArg::NONE) {
                $value = "";
            }
            else if ($argument->type === ProcessArg::TEXT) {
                // No conversion needed
            }
            else if ($argument->type === ProcessArg::SCHEDULEID) {
                // Not supported for now
            }
            else {
                $this->log->error("convertProcess() Bad device template. Unsuported argument type. process='$process->process' type='".$argument->type."'");
                return array('success'=>false, 'message'=>"Bad device template. Unsuported argument type. process='$process->process' type='".$argument->type."'");
            }

            $args[] = $value;
        }

        $process_key = $process->process;
        $this->log->info("convertProcess() process process='$process_key' args='" . json_encode($args) . "'");

        return [
            'fn' => $process_key,
            'args' => $args
        ];
    }

    protected function parse_prefix($nodeid, $name, $template) {
        if (isset($template->prefix)) {
            $prefix = $template->prefix;
            if ($prefix === "node") {
                return strtolower($nodeid)."_";
            }
            else if ($prefix === "name") {
                return strtolower($name)."_";
            }
        }
        return "";
    }

    protected function search_array($array, $key, $val) {
        foreach ($array as $item) {
            if (isset($item->$key) && $item->$key == $val) {
                return $item;
            }
        }
        return null;
    }

    // ------ Template Generation ------


    /**
     * Generates a template based on the device's inputs and processes.
     *
     * @param array $device Device information including userid and nodeid.
     * @return array Template structure containing inputs and feeds.
     */
    public function generate_template($device) {
        $userid = intval($device['userid']);
        $nodeid = $device['nodeid'];

        // Get all inputs associated with $nodeid
        $inputs = $this->input->getlist($userid);
        $inputs_by_id = $this->map_inputs_by_id($inputs, $nodeid);
        $engines = array_flip(Engine::get_all());

        $template_inputs = array();
        $template_feeds = array();

        foreach ($inputs as $input) {
            if ($input['nodeid'] != $nodeid) continue;

            $processes_for_template = array();
            $decoded_processList = $this->process->decode_processlist($input['processList']);

            foreach ($decoded_processList as $process) {
                $process_info = $this->process->get_info($process['fn']);
                if (!$process_info) continue;

                list($arguments, $feeds) = $this->build_process_arguments($process, $process_info, $inputs_by_id, $engines);
                $template_feeds = array_merge($template_feeds, $feeds);

                $processes_for_template[] = array(
                    "process" => $process_info['function'],
                    "arguments" => $arguments,
                );
            }

            $template_inputs[] = array(
                "name" => $input['name'],
                "description" => $input['description'],
                "processList" => $processes_for_template
            );
        }

        $template = array(
            "name" => $nodeid."-template",
            "category" => "Custom",
            "group" => "Custom",
            "description" => "",
            "inputs" => $template_inputs,
            "feeds" => array_values($this->unique_feeds($template_feeds)),
        );

        return $template;
    }

    /**
     * Map inputs by their ID for quick lookup.
     */
    private function map_inputs_by_id($inputs, $nodeid) {
        $inputs_by_id = array();
        foreach ($inputs as $input) {
            if ($input['nodeid'] == $nodeid) {
                $inputs_by_id[$input['id']] = $input;
            }
        }
        return $inputs_by_id;
    }

    /**
     * Build process arguments and collect feeds for the template.
     */
    private function build_process_arguments($process, $process_info, $inputs_by_id, $engines) {
        $arguments = array();
        $feeds = array();

        foreach ($process_info['args'] as $idx => $arg) {
            switch ($arg['type']) {
                case ProcessArg::NONE:
                    $arguments[] = array("type" => "ProcessArg::NONE");
                    break;

                case ProcessArg::VALUE:
                    $arguments[] = array(
                        "type" => "ProcessArg::VALUE",
                        "value" => $process['args'][$idx],
                    );
                    break;

                case ProcessArg::FEEDID:
                    $feedid = $process['args'][$idx];
                    $feed = $this->feed->get($feedid);
                    $arguments[] = array(
                        "type" => "ProcessArg::FEEDID",
                        "value" => $feed['name'],
                    );
                    $feeds[] = $this->build_template_feed($feed, $feedid, $engines);
                    break;

                case ProcessArg::INPUTID:
                    $inputid = $process['args'][$idx];
                    $input_data = isset($inputs_by_id[$inputid]) ? $inputs_by_id[$inputid] : false;
                    if ($input_data) {
                        $arguments[] = array(
                            "type" => "ProcessArg::INPUTID",
                            "value" => $input_data['name'],
                        );
                    }
                    break;
            }
        }

        // If only one argument is present convert it to a single value
        if (count($arguments) == 1) {
            $arguments = $arguments[0];
        }

        return array($arguments, $feeds);
    }

    /**
     * Build a feed entry for the template.
     */
    private function build_template_feed($feed, $feedid, $engines) {
        $template_feed = array(
            "name" => $feed['name'],
            "engine" => "Engine::".$engines[$feed['engine']]
        );
        $meta = $this->feed->get_meta($feedid);
        if (isset($meta->interval)) {
            $template_feed['interval'] = $meta->interval;
        }
        if (isset($feed['unit'])) {
            $template_feed['unit'] = $feed['unit'];
        }
        return $template_feed;
    }

    /**
     * Remove duplicate feeds by name.
     */
    private function unique_feeds($feeds) {
        $unique = array();
        foreach ($feeds as $feed) {
            $unique[$feed['name']] = $feed;
        }
        return $unique;
    }
}
