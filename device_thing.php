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
    protected $mysqli;
    protected $redis;
    protected $log;

    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        $this->mysqli = &$parent->mysqli;
        $this->redis = &$parent->redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function get_item($userid, $nodeid, $name, $type, $options) {
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
        
        $items = array();
        for ($i=0; $i<count($template->items); $i++) {
            $item = (array) $template->items[$i];
            
            if (isset($item['mapping'])) {
                foreach($item['mapping'] as &$mapping) {
                    if (isset($mapping->input)) {
                        $inputid = $this->get_input_id($userid, $nodeid, $prefix, $mapping->input, $template->inputs);
                        if ($inputid == false) {
                            continue;
                        }
                        unset($mapping->input);
                        $mapping = array_merge(array('inputid'=>$inputid), (array) $mapping);
                    }
                }
            }
            if (isset($item['input'])) {
                $inputid = $this->get_input_id($userid, $nodeid, $prefix, $item['input'], $template->inputs);
                if ($inputid == false) {
                    continue;
                }
                unset($item['input']);
                $item = array_merge($item, array('inputid'=>$inputid));
            }
            if (isset($item['feed'])) {
                $feedid = $this->get_feed_id($userid, $prefix, $item['feed']);
                if ($feedid == false) {
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
        if (isset($mapping['inputid']) && isset($mapping['value'])) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $input->set_timevalue($mapping['inputid'], time(), $mapping['value']);
            
            return array('success'=>true, 'message'=>"Item value set");
        }
        return array('success'=>false, 'message'=>"Error while seting item value");
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
