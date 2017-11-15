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

class DeviceControl
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

    public function get_template_list() {
        return $this->load_template_list();
    }

    protected function load_template_list() {
        $list = array();
        foreach (glob("Modules/device/data/*.json") as $file) {
            $content = json_decode(file_get_contents($file));
            $list[basename($file, ".json")] = $content;
        }
        return $list;
    }

    public function get_template($type) {
    	$type = preg_replace('/[^\p{L}_\p{N}\s-:]/u','', $type);
        
    	if (file_exists("Modules/device/data/$type.json")) {
    		return json_decode(file_get_contents("Modules/device/data/$type.json"));
        }
    }

    public function get_control($userid, $nodeid, $name, $type, $options) {
    	$file = "Modules/device/data/".$type.".json";
        if (file_exists($file)) {
            $template = json_decode(file_get_contents($file));
        } else {
            return array('success'=>false, 'message'=>"Template file not found '".$file."'");
        }
        if (isset($template->prefix)) {
            $prefix = $this->parse_prefix($nodeid, $name, $template->prefix);
        }
        else $prefix = "";
        
        $controls = array();
        for ($i=0; $i<count($template->control); $i++) {
            $control = (array) $template->control[$i];
            
            if (isset($control['mapping'])) {
                foreach($control['mapping'] as &$options) {
                    if (isset($options->channel)) {
                        $channelid = $this->get_input_id($userid, $nodeid, $prefix, $options->channel, $template->inputs);
                        if ($channelid == false) {
                            continue;
                        }
                        unset($options->channel);
                        $options = array_merge(array('channelid'=>$channelid), (array) $options);
                    }
                }
            }
            if (isset($control['input'])) {
                $inputid = $this->get_input_id($userid, $nodeid, $prefix, $control['input'], $template->inputs);
                if ($inputid == false) {
                    continue;
                }
                unset($control['input']);
                $control = array_merge($control, array('inputid'=>$inputid));
            }
            if (isset($control['feed'])) {
                $feedid = $this->get_feed_id($userid, $prefix, $control['feed']);
                if ($feedid == false) {
                    continue;
                }
                unset($control['feed']);
                $control = array_merge($control, array('feedid'=>$feedid));
            }
            
            $controls[] = $control;
        }
        return $controls;
    }

    public function set_control($channelid, $options, $value) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);
        
        $input->set_timevalue($channelid, time(), $value);
        
        return array('success'=>true, 'message'=>"Value set");
    }

    public function init_template($userid, $nodeid, $name, $type, $options) {
    	$file = "Modules/device/data/".$type.".json";
        if (file_exists($file)) {
            $template = json_decode(file_get_contents($file));
        } else {
            return array('success'=>false, 'message'=>"Template file not found '".$file."'");
        }
        $prefix = $this->parse_prefix($nodeid, $name, $template->prefix);
        
        $feeds = $template->feeds;
        $inputs = $template->inputs;
        
        // Create feeds
        $result = $this->create_feeds($userid, $nodeid, $prefix, $feeds);
        if ($result["success"] !== true) {
            return array('success'=>false, 'message'=>'Error while creating the feeds. ' . $result['message']);
        }
        
        // Create inputs
        $result = $this->create_inputs($userid, $nodeid, $prefix, $inputs);
        if ($result !== true) {
            return array('success'=>false, 'message'=>'Error while creating the inputs.');
        }
        
        // Create inputs processes
        $result = $this->create_input_processes($feeds, $inputs);
        if ($result["success"] !== true) {
            return array('success'=>false, 'message'=>'Error while creating the inputs process list. ' . $result['message']);
        }
        
        // Create feeds processes
        $result = $this->create_feed_processes($feeds, $inputs);
        if ($result["success"] !== true) {
            return array('success'=>false, 'message'=>'Error while creating the feeds process list. ' . $result['message']);
        }
        
        return array('success'=>true, 'message'=>'Device initialized');
    }

    protected function parse_prefix($nodeid, $name, $prefix) {
        if ($prefix === "node") {
            return $nodeid."_";
        }
        else if ($prefix === "name") {
            return $name."_";
        }
        else return "";
    }

    // Create the feeds
    protected function create_feeds($userid, $nodeid, $prefix, &$feeds) {
        global $feed_settings;
        
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);
        
        $result = array("success"=>true);
        
        foreach($feeds as $f) {
            // Create each feed
            $name = $prefix.$f->name;
            if (property_exists($f, "tag")) {
                $tag = $f->tag;
            } else {
                $tag = $nodeid;
            }
            $datatype = constant($f->type); // DataType::
            $engine = constant($f->engine); // Engine::
            $options = new stdClass();
            if (property_exists($f, "interval")) {
                $options->interval = $f->interval;
            }
            
            $feedid = $feed->get_id($userid, $name);
            
            if ($feedid == false) {
                $this->log->info("create_feeds() userid=$userid tag=$tag name=$name datatype=$datatype engine=$engine");
                $result = $feed->create($userid, $tag, $name, $datatype, $engine, $options);
                if($result["success"] !== true) {
                    return $result;
                }
                $feedid = $result["feedid"]; // Assign the created feed id to the feeds array
            }
            
            $f->feedid = $feedid;
        }
        return $result;
    }

    // Create the inputs
    protected function create_inputs($userid, $nodeid, $prefix, &$inputs) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);

        foreach($inputs as $i) {
            // Create each input
            $name = $prefix.$i->name;
            $description = $i->description;
            if(property_exists($i, "node")) {
                $node = $i->node;
            } else {
                $node = $nodeid;
            }
            
            $inputid = $input->exists_nodeid_name($userid, $node, $name);
            
            if ($inputid == false) {
                $this->log->info("create_inputs() userid=$userid nodeid=$node name=$name description=$description");
                $inputid = $input->create_input($userid, $node, $name);
                if(!$input->exists($inputid)) {
                    return false;
                }
                $input->set_fields($inputid, '{"description":"'.$description.'"}');
            }
            $i->inputid = $inputid; // Assign the created input id to the inputs array
        }
        return true;
    }

    // Create the inputs process lists
    protected function create_input_processes($feeds, $inputs) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);

        foreach($inputs as $i) {
            // for each input
            if (isset($i->processList) || isset($i->processlist)) {
        		$processes = isset($i->processList) ? $i->processList : $i->processlist;
                $inputid = $i->inputid;
                $result = $this->convert_processes($feeds, $inputs, $processes);
                if (isset($result["success"])) {
                    return $result; // success is only filled if it was an error
                }

                $processes = implode(",", $result);
                if ($processes != "") {
                    $this->log->info("create_inputs_processes() calling input->set_processlist inputid=$inputid processes=$processes");
                    $input->set_processlist($inputid, $processes);
                }
            }
        }

        return array('success'=>true);
    }

    protected function create_feed_processes($feeds, $inputs) {
        global $feed_settings;

        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, $feed_settings);

        foreach($feeds as $f) {
            // for each feed
        	if (($f->engine == Engine::VIRTUALFEED) && (isset($f->processList) || isset($f->processlist))) {
        		$processes = isset($f->processList) ? $f->processList : $f->processlist;
                $feedid = $f->feedid;
                $result = $this->convert_processes($feeds, $inputs, $processes);
                if (isset($result["success"])) {
                    return $result; // success is only filled if it was an error
                }

                $processes = implode(",", $result);
                if ($processes != "") {
                    $this->log->info("create_feeds_processes() calling feed->set_processlist feedId=$feedid processes=$processes");
                    $feed->set_processlist($feedid, $processes);
                }
            }
        }

        return array('success'=>true);
    }

    // Converts template processList
    protected function convert_processes($feeds, $inputs, $processes){
        $result = array();
        
        if (is_array($processes)) {
            require_once "Modules/process/process_model.php";
            $process = new Process(null, null, null, null);
            $process_list = $process->get_process_list(); // emoncms supported processes

            $process_list_by_name = array();
            foreach ($process_list as $process_id=>$process_item) {
                $name = $process_item[2];
                $process_list_by_name[$name] = $process_id;
            }

            // create each processList
            foreach($processes as $p) {
                $proc_name = $p->process;
                
                // If process names are used map to process id
                if (isset($process_list_by_name[$proc_name])) $proc_name = $process_list_by_name[$proc_name];
                
                if (!isset($process_list[$proc_name])) {
                    $this->log->error("convertProcess() Process '$proc_name' not supported. Module missing?");
                    return array('success'=>false, 'message'=>"Process '$proc_name' not supported. Module missing?");
                }

                // Arguments
                if(isset($p->arguments)) {
                    if(isset($p->arguments->type)) {
                        $type = @constant($p->arguments->type); // ProcessArg::
                        $process_type = $process_list[$proc_name][1]; // get emoncms process ProcessArg

                        if ($process_type != $type) {
                            $this->log->error("convertProcess() Bad device template. Missmatch ProcessArg type. Got '$type' expected '$process_type'. process='$proc_name' type='".$p->arguments->type."'");
                            return array('success'=>false, 'message'=>"Bad device template. Missmatch ProcessArg type. Got '$type' expected '$process_type'. process='$proc_name' type='".$p->arguments->type."'");
                        }

                        if (isset($p->arguments->value)) {
                            $value = $p->arguments->value;
                        } else if ($type === ProcessArg::NONE) {
                            $value = 0;
                        } else {
                            $this->log->error("convertProcess() Bad device template. Undefined argument value. process='$proc_name' type='".$p->arguments->type."'");
                            return array('success'=>false, 'message'=>"Bad device template. Undefined argument value. process='$proc_name' type='".$p->arguments->type."'");
                        }

                        if ($type === ProcessArg::VALUE) {
                        } else if ($type === ProcessArg::INPUTID) {
                            $temp = $this->search_array($inputs, 'name', $value); // return input array that matches $inputArray[]['name']=$value
                            if ($temp->inputid > 0) {
                                $value = $temp->inputid;
                            } else {
                                $this->log->error("convertProcess() Bad device template. Input name '$value' was not found. process='$proc_name' type='".$p->arguments->type."'");
                                return array('success'=>false, 'message'=>"Bad device template. Input name '$value' was not found. process='$proc_name' type='".$p->arguments->type."'");
                            }
                        } else if ($type === ProcessArg::FEEDID) {
                            $temp = $this->search_array($feeds, 'name', $value); // return feed array that matches $feedArray[]['name']=$value
                            if ($temp->feedid > 0) {
                                $value = $temp->feedid;
                            } else {
                                $this->log->error("convertProcess() Bad device template. Feed name '$value' was not found. process='$proc_name' type='".$p->arguments->type."'");
                                return array('success'=>false, 'message'=>"Bad device template. Feed name '$value' was not found. process='$proc_name' type='".$p->arguments->type."'");
                            }
                        } else if ($type === ProcessArg::NONE) {
                            $value = 0;
                        } else if ($type === ProcessArg::TEXT) {
//                      } else if ($type === ProcessArg::SCHEDULEID) { //not supporte for now
                        } else {
                                $this->log->error("convertProcess() Bad device template. Unsuported argument type. process='$proc_name' type='".$p->arguments->type."'");
                                return array('success'=>false, 'message'=>"Bad device template. Unsuported argument type. process='$proc_name' type='".$p->arguments->type."'");
                        }

                    } else {
                        $this->log->error("convertProcess() Bad device template. Argument type is missing, set to NONE if not required. process='$proc_name' type='".$p->arguments->type."'");
                        return array('success'=>false, 'message'=>"Bad device template. Argument type is missing, set to NONE if not required. process='$proc_name' type='".$p->arguments->type."'");
                    }

                    $this->log->info("convertProcess() process process='$proc_name' type='".$p->arguments->type."' value='" . $value . "'");
                    $result[] = $proc_name.":".$value;

                } else {
                    $this->log->error("convertProcess() Bad device template. Missing processList arguments. process='$proc_name'");
                    return array('success'=>false, 'message'=>"Bad device template. Missing processList arguments. process='$proc_name'");
                }
            }
        }
        return $result;
    }

    protected function search_array($array, $key, $val) {
        foreach ($array as $item) {
            if (isset($item->$key) && $item->$key == $val) {
                return $item;
            }
        }
        return null;
    }

    protected function get_input_id($userid, $nodeid, $prefix, $name, $inputs) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);
        
        foreach($inputs as $i) {
            if ($i->name == $name) {
                if(property_exists($i, "node")) {
                    $node = $i->node;
                } else {
                    $node = $nodeid;
                }
                $fullname = $prefix.$name;
                
                return $input->exists_nodeid_name($userid, $nodeid, $fullname);
            }
        }
        return false;
    }

    protected function get_feed_id($userid, $prefix, $name) {
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, null);
        
        return $feed->get_id($userid, $prefix.$name);
    }
}
