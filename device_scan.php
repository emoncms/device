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

class DeviceScan
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

    public function start($userid, $type, $options) {
        return array('success'=>true,
            'info'=>array('finished'=>false, 'interrupted'=>false, 'progress'=>0),
            'devices'=>array(),
        );
    }

    public function progress($userid, $type) {
        $devices = array();
        
        return array('success'=>true,
            'info'=>array('finished'=>true, 'interrupted'=>false, 'progress'=>100),
            'devices'=>$devices,
        );
    }

    public function cancel($userid, $type) {
        $devices = array();
        
        return array('success'=>true,
            'info'=>array('finished'=>true, 'interrupted'=>true, 'progress'=>100),
            'devices'=>$devices,
        );
    }

}
