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
    private $redis;
    private $log;
    
    // Module required constructor, receives parent as reference
    public function __construct(&$parent) {
        $this->redis = &$parent->redis;
        $this->log = new EmonLogger(__FILE__);
    }

    public function get_list($devices) {
    	foreach ($devices as &$device) {
    		foreach ($device['output'] as &$output) {
    			$output = (array) $output;
    			$output["value"] = true;
    		}
    	}
    	
    	return $devices;
    }

    public function get($name, $device, $output) {
    	$output = (array) $output;
        $output["value"] = true;
        
        return $output;
    }
}
