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

class DeviceThing
{
    const SEPARATOR = '_';

    protected $mysqli;
    protected $redis;
    protected $log;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        $this->mysqli = &$parent->mysqli;
        $this->redis = &$parent->redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function get_item_list($device) {
        $result = $this->get_template($device['userid'], $device['type']);
        if (!is_object($result)) {
            return $result;
        }
        $sep = (isset($device['options']) && isset($device['options']['sep'])) ? $device['options']['sep'] : self::SEPARATOR;
        
        $items = array();
        for ($i=0; $i<count($result->items); $i++) {
            $item = (array) $result->items[$i];
            
            if (isset($item['mapping'])) {
                foreach($item['mapping'] as &$mapping) {
                    // TODO: Implement MQTT mapping here
                    if (isset($mapping->input)) {
                        $inputid = $this->get_input_id($sep, $device['userid'], $device['nodeid'], $mapping->input, $result->inputs);
                        if ($inputid == false) {
                            $this->log->error("get_item_list() failed to find input of item '".$item['id']."' in template: ".$device['type']);
                            continue;
                        }
                        unset($mapping->input);
                        $mapping = array_merge(array('inputid'=>$inputid), (array) $mapping);
                    }
                }
            }
            if (isset($item['input'])) {
                $inputid = $this->get_input_id($sep, $device['userid'], $device['nodeid'], $item['input'], $result->inputs);
                if ($inputid == false) {
                    $this->log->error("get_item_list() failed to find input of item '".$item['id']."' in template: ".$device['type']);
                    continue;
                }
                unset($item['input']);
                $item = array_merge($item, array('inputid'=>$inputid));
            }
            if (isset($item['feed'])) {
                $feedid = $this->get_feed_id($sep, $device['userid'], $device['nodeid'], $item['feed']);
                if ($feedid == false) {
                    $this->log->error("get_item_list() failed to find feed of item '".$item['id']."' in template: ".$device['type']);
                    continue;
                }
                unset($item['feed']);
                $item = array_merge($item, array('feedid'=>$feedid));
            }
            
            $items[] = $item;
        }
        return $items;
    }

    public function set_item($itemid, $mapping) {
        // TODO: Implement MQTT actions here
        if (isset($mapping['inputid']) && isset($mapping['value'])) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $input->set_timevalue($mapping['inputid'], time(), $mapping['value']);
            
            return array('success'=>true, 'message'=>"Item value set");
        }
        return array('success'=>false, 'message'=>"Error while setting item value");
    }

    protected function get_template_list($userid) {
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

    protected function get_template($userid, $type) {
        $type = preg_replace('/[^\p{L}_\p{N}\s-:]/u','', $type);
        $list = $this->get_template_list($userid);
        if (!isset($list[$type])) {
            return array('success'=>false, 'message'=>'Device template "'.$type.'" not found');
        }
        return $list[$type];
    }

    protected function get_input_id($separator, $userid, $nodeid, $name, $inputs) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);
        
        foreach($inputs as $i) {
            if ($i->name == $name) {
                if(property_exists($i, "node")) {
                    $node = $i->node;
                } else {
                    $node = $nodeid;
                }
                
                return $input->exists_nodeid_name($userid, $node, 
                    $this->parse_name($separator, $node, $name));
            }
        }
        return false;
    }

    protected function get_feed_id($separator, $userid, $nodeid, $name) {
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, null);
        
        return $feed->get_id($userid, $this->parse_name($separator, $nodeid, $name));
    }

    protected function parse_name($separator, $nodeid, $name) {
        $name = str_replace("<node>", $nodeid, $name);
        $name = str_replace("*", $separator, $name);
        return $name;
    }

}
