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
        $this->device = &$parent;
        $this->mysqli = &$parent->mysqli;
        $this->redis = &$parent->redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function load($device) {
        if ($this->redis) {
            foreach ($this->redis->sMembers("device:thing:".$device['id']) as $key) {
                $this->redis->del("device:item:".$device['id'].":".$key);
                $this->redis->srem("device:thing:".$device['id'], $key);
            }
        }
        return $this->get($device);
    }

    public function get($device) {
        $thing = array(
            'id' => $device['id'],
            'userid' => $device['userid'],
            'nodeid' => $device['nodeid'],
            'name' => $device['name'],
            'description' => $device['description'],
            'type' => $device['type'],
            'items' => array()
        );
        
        $items = $this->get_item_list($thing);
        foreach ($items as &$item) {
            $item['value'] = $this->get_item_value($item);
            
            $keys = array(
                'id',
                'type',
                'label',
                'header',
                'write',
                'left',
                'right',
                'format',
                'scale',
                'min',
                'max',
                'step',
                'select',
                'default'
            );
            foreach (array_keys($item) as $key) {
                if (!in_array($key, $keys)) unset($item[$key]);
            }
            $thing['items'][] = $item;
        }
        return $thing;
    }

    public function get_item($thing, $itemid) {
        if ($this->redis && $this->redis->exists("device:thing:".$thing['id'])) {
            $itemids = $this->redis->sMembers("device:thing:".$thing['id']);
            foreach ($itemids as $i) {
                $item = (array) $this->redis->hGetAll("device:item:".$thing['id'].":$i");
                if ($item['id'] == $itemid) {
                    if (isset($item['select'])) $item['select'] = json_decode($item['select']);
                    if (isset($item['mapping'])) $item['mapping'] = json_decode($item['mapping']);
                    return $item;
                }
            }
        }
        // If nothing can be found in cache, load and cache all items
        $items = $this->get_item_list($thing);
        foreach ($items as $item) {
            if ($item['id'] == $itemid) {
                return $item;
            }
        }
        return array('success'=>false, 'message'=>'Item does not exist');
    }

    public function get_item_list($thing) {
        $items = array();
        if ($this->redis && $this->redis->exists("device:thing:".$thing['id'])) {
            $itemids = $this->redis->sMembers("device:thing:".$thing['id']);
            foreach ($itemids as $i) {
                $item = (array) $this->redis->hGetAll("device:item:".$thing['id'].":$i");
                if (isset($item['select'])) $item['select'] = json_decode($item['select']);
                if (isset($item['mapping'])) $item['mapping'] = json_decode($item['mapping']);
                $items[] = $item;
            }
            return $items;
        }
        // If nothing can be found in cache, load and cache all items
        $template = $this->device->get_template_class($thing['type'])->prepare_template($thing);
        if (!is_object($template)) {
            throw new DeviceException($template['message']);
        }
        if (empty($template->items)) {
            return $items;
        }
        for ($i=0; $i<count($template->items); $i++) {
            $item = (array) $template->items[$i];
            $items[] = $this->parse_item($thing, $item, $template);
        }
        
        if ($this->redis) {
            foreach ((array) $items as $key => $value) {
                if (isset($value['select'])) $value['select'] = json_encode($value['select']);
                if (isset($value['mapping'])) $value['mapping'] = json_encode($value['mapping']);
                $this->redis->sAdd("device:thing:".$thing['id'], $key);
                $this->redis->hMSet("device:item:".$thing['id'].":$key", $value);
            }
        }
        return $items;
    }

    protected function parse_item($thing, &$item, $template) {
        if (isset($item['mapping'])) {
            foreach($item['mapping'] as &$mapping) {
                $this->parse_item_mapping($thing, $item, $mapping, $template);
            }
        }
        if (isset($item['input'])) {
            $this->parse_item_input($thing, $item, $template);
        }
        if (isset($item['feed'])) {
            $this->parse_item_feed($thing, $item, $template);
        }
        return $item;
    }

    protected function parse_item_mapping($thing, &$item, &$mapping, $template) {
        //TODO: Implement MQTT mapping here instead of inputid placeholder
        if (isset($mapping->input)) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $nodeid = isset($item['node']) ? $item['node'] : $thing['nodeid'];
            foreach($template->inputs as $i) {
                if ($i->name == $name) {
                    if(property_exists($i, "node")) {
                        $nodeid = $i->node;
                    }
                }
            }
            $inputid = $input->exists_nodeid_name($userid, $nodeid, $name);
            if ($inputid == false) {
                $this->log->error("get_items() failed to find input of item '".$item['id']."' in template: ".$thing['type']);
                return;
            }
            unset($mapping->input);
            $mapping = array_merge(array('inputid'=>$inputid), (array) $mapping);
        }
    }

    protected function parse_item_input($thing, &$item, $template) {
        require_once "Modules/input/input_model.php";
        $input = new Input($this->mysqli, $this->redis, null);
        
        $name = $item['input'];
        $nodeid = isset($item['node']) ? $item['node'] : $thing['nodeid'];
        foreach($template->inputs as $i) {
            if ($i->name == $name) {
                if(property_exists($i, "node")) {
                    $nodeid = $i->node;
                }
            }
        }
        $inputid = $input->exists_nodeid_name($thing['userid'], $nodeid, $name);
        if ($inputid == false) {
            $this->log->error("get_items() failed to find input of item '".$item['id']."' in template: ".$thing['type']);
            return;
        }
        unset($item['input']);
        $item['inputid'] = $inputid;
    }

    protected function parse_item_feed($userid, $name) {
        require_once "Modules/feed/feed_model.php";
        $feed = new Feed($this->mysqli, $this->redis, null);
        
        // TODO: implement search with optional tag
        //$feedid = $feed->exists_tag_name($thing['userid'], $item['tag'], $item['feed']);
        $feedid = $feed->get_id($thing['userid'], $item['feed']);
        if ($feedid == false) {
            $this->log->error("get_item_list() failed to find feed of item '".$item['id']."' in template: ".$thing['type']);
            return;
        }
        unset($item['feed']);
        $item['feedid'] = $feedid;
    }

    protected function get_item_value($item) {
        $value = null;
        if (isset($item['inputid'])) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $value = $input->get_last_value($item['inputid']);
        }
        if (isset($item['feedid'])) {
            global $settings;
            require_once "Modules/feed/feed_model.php";
            $feed = new Feed($this->mysqli, $this->redis, $settings['feed']);
            
            $value = $feed->get_value($item['feedid']);
        }
        return $value;
    }

    public function set_item_value($thing, $itemid, $value) {
        $item = $this->get_item($thing, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $mapping = (array) $item['mapping'];
            if (isset($mapping['SET'])) {
                $mapping = (array) $mapping['SET'];
                $mapping['value'] = $value;
                
                return $this->set_item($itemid, $mapping);
            }
        }
        return array('success'=>false, 'message'=>'Unknown item or incomplete device template mappings "SET"');
    }

    public function set_item_on($thing, $itemid) {
        $item = $this->get_item($thing, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $mapping = (array) $item['mapping'];
            if (isset($mapping['ON'])) {
                return $this->set_item($itemid, (array) $mapping['ON']);
            }
        }
        return array('success'=>false, 'message'=>'Unknown item or incomplete device template mappings "ON"');
    }

    public function set_item_off($thing, $itemid) {
        $item = $this->get_item($thing, $itemid);
        if (isset($item) && isset($item['mapping'])) {
            $mapping = (array) $item['mapping'];
            if (isset($mapping['OFF'])) {
                return $this->set_item($itemid, (array) $mapping['OFF']);
            }
        }
        return array('success'=>false, 'message'=>'Unknown item or incomplete device template mappings "OFF"');
    }

    public function toggle_item_value($thing, $itemid) {
        return array('success'=>false, 'message'=>'Item "toggle" not implemented yet');
    }

    public function increase_item_value($thing, $itemid) {
        return array('success'=>false, 'message'=>'Item "increase" not implemented yet');
    }

    public function decrease_item_value($thing, $itemid) {
        return array('success'=>false, 'message'=>'Item "decrease" not implemented yet');
    }

    public function set_item_percent($thing, $itemid, $value) {
        return array('success'=>false, 'message'=>'Item "percent" not implemented yet');
    }

    protected function set_item($itemid, $mapping) {
        // TODO: Implement MQTT actions here instead of input writing placeholder
        if (isset($mapping['inputid']) && isset($mapping['value'])) {
            require_once "Modules/input/input_model.php";
            $input = new Input($this->mysqli, $this->redis, null);
            
            $input->set_timevalue($mapping['inputid'], time(), $mapping['value']);
            
            return array('success'=>true, 'message'=>"Item value set");
        }
        return array('success'=>false, 'message'=>"Error while setting item value");
    }

    public function delete() {
        if ($this->redis && $this->redis->exists("device:thing:$id")) {
            foreach ($this->redis->sMembers("device:thing:$id") as $key) {
                $this->redis->del("device:item:$id:$key");
                $this->redis->srem("device:thing:$id", $key);
            }
        }
    }

}
