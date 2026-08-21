<?php

  /*

  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org

  ---------------------------------------------------------------------
  Device module documentation that is not tied to a single endpoint:
  device key authentication and how device templates work.

  Rendered by Lib/api_explorer_view.php via its $extra parameter and styled
  with the shared .api-auth-card classes from Lib/api_auth_view.php.

  */

  defined('EMONCMS_EXEC') or die('Restricted access');
  global $path;
?>
<div class="api-auth-grid">

  <div class="api-auth-card">
    <div class="api-auth-title">
      <span class="api-auth-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"></rect><path d="M10 18h4"></path></svg>
      </span>
      <h3><?php echo tr('Device key authentication'); ?></h3>
    </div>
    <p><?php echo tr('A device key posts data for <strong>one node only</strong>, so a device that is out in the field never carries your account write key.'); ?></p>
    <p><?php echo tr('The input API accepts a device key in place of an apikey, in the URL or the POST body:'); ?></p>
    <div style="display:flex; flex-direction:column; gap:10px">
      <div class="api-auth-row">
        <span class="api-auth-tag recommended"><?php echo tr('RECOMMENDED'); ?></span>
        <span class="api-auth-mono"><?php echo tr('POST body:'); ?> <span class="hl">devicekey=DEVICEKEY</span></span>
      </div>
      <div class="api-auth-row">
        <span class="api-auth-tag"><?php echo tr('URL'); ?></span>
        <span class="api-auth-mono">&amp;devicekey=DEVICEKEY</span>
      </div>
    </div>
    <p class="api-auth-muted"><?php echo tr('The node the data is posted to must match the nodeid configured for that device, otherwise the post is rejected. Generate a key with device/generatekey.json below.'); ?></p>
  </div>

  <div class="api-auth-card">
    <div class="api-auth-title">
      <span class="api-auth-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"></path></svg>
      </span>
      <h3><?php echo tr('Device templates'); ?></h3>
    </div>
    <p><?php echo tr('A template defines a device type and the default inputs, feeds and process lists for it. Template files live in <span class="api-auth-mono">Modules/device/data/*.json</span>.'); ?></p>
    <p><?php echo tr('Setting up a device is two steps: <strong>create</strong> it with a nodeid and a type, then <strong>initialize</strong> it to build the inputs and feeds that type expects.'); ?></p>
    <p class="api-auth-muted"><?php echo tr('Initialize a device once, on installation. Initializing the same device twice duplicates its inputs and feeds. Use device/template/prepare.json first to see exactly what will be created.'); ?></p>
    <p><a href="<?php echo $path; ?>device/view"><?php echo tr('Go to the Devices page'); ?></a></p>
  </div>
</div>
