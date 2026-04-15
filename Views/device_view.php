<?php
    defined('EMONCMS_EXEC') or die('Restricted access');
    global $path, $settings, $session;
    
    $version = 3;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device.js?v=<?php echo $version; ?>"></script>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<style>

body {
    background-color: #1e1e1e;
    
}

#device-app {
    color: #ccc;
    font-size: 13px;
}

/* ── Page header ─────────────────────────────────────────── */
#device-app .device-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0 0.75rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid #2e2e2e;
}
#device-app .device-page-header h2 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #ddd;
    letter-spacing: 0.01em;
}
#device-app .device-page-header a {
    font-size: 12px;
    color: #44b3e2;
    text-decoration: none;
    opacity: 0.8;
    transition: opacity 0.2s;
}
#device-app .device-page-header a:hover { opacity: 1; text-decoration: none; }

/* ── Empty state ──────────────────────────────────────────── */
#device-app .device-empty {
    background-color: #252525;
    border: 1px solid #2e2e2e;
    border-radius: 0.5rem;
    padding: 1.5rem 1.75rem;
    color: #888;
    line-height: 1.6;
}
#device-app .device-empty h4 {
    margin: 0 0 0.6rem 0;
    font-size: 1rem;
    color: #bbb;
}
#device-app .device-empty a { color: #44b3e2; }

/* ── Location cards ───────────────────────────────────────── */
#device-app .location-card {
    background-color: #252525;
    border: 1px solid #2e2e2e;
    border-radius: 0.5rem;
    margin-bottom: 0.75rem;
    overflow: hidden;
}

/* Card header */
#device-app .location-card-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.6rem 1rem;
    background-color: #1e1e1e;
    border-bottom: 1px solid #2e2e2e;
    cursor: pointer;
    user-select: none;
}
#device-app .location-card-header:hover {
    background-color: #242424;
}
/* Blue left accent */
#device-app .location-card-header::before {
    content: '';
    display: block;
    width: 3px;
    height: 1.1em;
    border-radius: 2px;
    background-color: #44b3e2;
    flex-shrink: 0;
}
#device-app .location-card-header .location-name {
    font-weight: 600;
    font-size: 13px;
    color: #ddd;
    flex: 1;
    letter-spacing: 0.02em;
}
#device-app .location-card-header .location-badge {
    font-size: 11px;
    color: #555;
    background-color: #2a2a2a;
    border: 1px solid #333;
    border-radius: 0.75rem;
    padding: 1px 8px;
}
#device-app .location-card-header .location-updated {
    font-size: 11px;
    color: #555;
    white-space: nowrap;
}
#device-app .location-card-header .location-updated span {
    /* colour injected inline by formatUpdated */
}
#device-app .location-card-header .collapse-icon {
    font-size: 11px;
    color: #444;
    transition: color 0.2s;
    flex-shrink: 0;
}
#device-app .location-card-header:hover .collapse-icon { color: #44b3e2; }

/* Inner table */
#device-app .location-card table {
    width: 100%;
    border-collapse: collapse;
}
#device-app .location-card table th {
    padding: 5px 12px;
    font-size: 11px;
    font-weight: normal;
    color: #555;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-bottom: 1px solid #2a2a2a;
    background-color: #202020;
    white-space: nowrap;
}
#device-app .location-card table td {
    padding: 9px 12px;
    color: #bbb;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
#device-app .location-card table tr:last-child td {
    border-bottom: none;
}
#device-app .location-card table tbody tr:hover td {
    background-color: rgba(68,179,226,0.05);
}

/* Node ID — secondary */
#device-app .location-card table td.col-node {
    color: #555;
    width: 60px;
}
/* Name — primary */
#device-app .location-card table td.col-name {
    color: #ddd;
    font-weight: 500;
}
/* Type chip */
#device-app .location-card table td.col-type span {
    display: inline-block;
    font-size: 11px;
    color: #888;
    background-color: #2a2a2a;
    border: 1px solid #333;
    border-radius: 0.75rem;
    padding: 1px 8px;
}
/* IP */
#device-app .location-card table td.col-ip {
    font-family: monospace;
    font-size: 12px;
    color: #777;
}
/* Device key — truncated monospace */
#device-app .location-card table td.col-key {
    font-family: monospace;
    font-size: 11px;
    color: #555;
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Updated time */
#device-app .location-card table td.col-updated {
    text-align: right;
    white-space: nowrap;
}
/* Action col */
#device-app .location-card table td.col-action {
    width: 24px;
    text-align: center;
    padding: 9px 6px;
}
#device-app .device-action {
    cursor: pointer;
    color: transparent;
    transition: color 0.15s;
}
#device-app .location-card table tbody tr:hover .device-action {
    color: #555;
}
#device-app .device-action:hover {
    color: #44b3e2 !important;
}

/* ── Toolbar ──────────────────────────────────────────────── */
#device-app .device-toolbar {
    display: flex;
    align-items: center;
    padding: 0.75rem 0 0.25rem;
    gap: 0.5rem;
}
#device-app .device-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35em;
    background-color: rgba(68,179,226,0.1);
    color: #44b3e2;
    border: 1px solid rgba(68,179,226,0.3);
    border-radius: 0.375rem;
    padding: 0.3rem 0.85rem;
    font-size: 13px;
    font-family: inherit;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
}
#device-app .device-btn:hover,
#device-app .device-btn:focus {
    background-color: rgba(68,179,226,0.22);
    color: #fff;
    outline: none;
}

/* ── Loader ───────────────────────────────────────────────── */
#device-app .device-loader {
    text-align: center;
    padding: 1.5rem 0;
    color: #444;
    font-size: 12px;
    letter-spacing: 0.05em;
}
</style>

<div id="device-app">

    <div v-show="deviceList.length" class="device-page-header">
        <h2><?php echo tr('Devices'); ?></h2>
        <a href="api"><?php echo tr('API Help'); ?></a>
    </div>

    <div v-if="!deviceList.length && !loading" class="device-empty">
        <h4><?php echo tr('No devices'); ?></h4>
        <p>
            <?php echo tr('Devices are used to configure and prepare the communication with different physical devices. Devices are grouped by Location for easy tracking when deploying at scale.'); ?>
            <br><br>
            <?php echo tr('A device configures and prepares inputs, feeds and other possible settings. e.g. representing different registers of defined metering units.'); ?>
            <br>
            <?php echo tr('Follow the next link as a guide for generating your request: '); ?><a href="api"><?php echo tr('Device API helper'); ?></a>
        </p>
    </div>

    <div v-for="(group, groupName) in groupedDevices" :key="groupName" class="location-card">

        <!-- Card header -->
        <div class="location-card-header" @click="toggleGroup(groupName)">
            <span class="location-name">{{ groupName || '<?php echo tr('Ungrouped'); ?>' }}</span>
            <span class="location-badge">{{ group.length }}</span>
            <span class="location-updated" v-html="formatUpdated(groupMaxTime(group))"></span>
            <i class="collapse-icon" :class="collapsed[groupName] ? 'icon-chevron-right' : 'icon-chevron-down'"></i>
        </div>

        <!-- Device table -->
        <table v-show="!collapsed[groupName]">
            <thead>
                <tr>
                    <th><?php echo tr('Node'); ?></th>
                    <th><?php echo tr('Name'); ?></th>
                    <th><?php echo tr('Type'); ?></th>
                    <th><?php echo tr('IP'); ?></th>
                    <th><?php echo tr('Device key'); ?></th>
                    <th style="text-align:right"><?php echo tr('Updated'); ?></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="d in group" :key="d.id">
                    <td class="col-node">{{ d.nodeid }}</td>
                    <td class="col-name">{{ d.name }}</td>
                    <td class="col-type"><span v-if="d.typename">{{ d.typename }}</span></td>
                    <td class="col-ip">{{ d.ip }}</td>
                    <td class="col-key" :title="d.devicekey">{{ d.devicekey }}</td>
                    <td class="col-updated" v-html="formatUpdated(d.time)"></td>
                    <td class="col-action">
                        <a class="device-action" @click="deleteDevice(d)"><i class="icon-trash"></i></a>
                    </td>
                    <td class="col-action">
                        <i v-if="!d['#NO_CONFIG#']" class="icon-wrench device-action" @click="configDevice(d)"></i>
                    </td>
                </tr>
            </tbody>
        </table>

    </div>

    <div class="device-loader" v-show="loading">Loading…</div>

    <div class="device-toolbar">
        <button class="device-btn" @click="newDevice"><i class="icon-plus-sign"></i> <?php echo tr('New device'); ?></button>
    </div>

</div>

<?php require "Modules/device/Views/device_dialog.php"; ?>

<script>
  var devices = <?php echo json_encode($templates); ?>;

  // Shim so device_dialog.js can call table.remove() without error after delete.
  // The global update() below refreshes the Vue app anyway.
  var table = { remove: function() {}, timeServerLocalOffset: 0 };

  var deviceApp = new Vue({
    el: '#device-app',
    data: {
      deviceList: [],
      collapsed: {},
      loading: false,
      timeServerLocalOffset: 0,
      updater: null
    },
    computed: {
      groupedDevices: function() {
        var groups = {};
        for (var i = 0; i < this.deviceList.length; i++) {
          var d = this.deviceList[i];
          var key = d.description || '';
          if (!groups[key]) groups[key] = [];
          groups[key].push(d);
        }
        return groups;
      }
    },
    methods: {
      update: function() {
        var self = this;
        var requestTime = (new Date()).getTime();
        self.loading = true;
        $.ajax({ url: path + "device/list.json", dataType: 'json', async: true,
          success: function(data, textStatus, xhr) {
            self.timeServerLocalOffset = requestTime - (new Date(xhr.getResponseHeader('Date'))).getTime();
            // keep shim in sync for device_dialog.js
            table.timeServerLocalOffset = self.timeServerLocalOffset;
            for (var i = 0; i < data.length; i++) {
              var d = data[i];
              if (d.type !== null && d.type !== '' && devices[d.type] !== undefined) {
                d.typename = devices[d.type].name;
              } else {
                d.typename = '';
              }
            }
            self.deviceList = data || [];
            self.loading = false;
          }
        });
      },
      startUpdater: function(interval) {
        clearInterval(this.updater);
        this.updater = null;
        if (interval > 0) this.updater = setInterval(this.update.bind(this), interval);
      },
      toggleGroup: function(groupName) {
        Vue.set(this.collapsed, groupName, !this.collapsed[groupName]);
      },
      groupMaxTime: function(rows) {
        var max = 0;
        for (var i = 0; i < rows.length; i++) {
          var t = parseFloat(rows[i].time);
          if (!isNaN(t) && t > max) max = t;
        }
        return max;
      },
      formatUpdated: function(time) {
        var servertime = (new Date()).getTime() - this.timeServerLocalOffset;
        var update = new Date(time * 1000).getTime();
        var delta = servertime - update;
        var secs = Math.abs(delta) / 1000;
        var mins = secs / 60;
        var hour = secs / 3600;
        var day = hour / 24;
        var d = new Date(time * 1000);

        var updated = secs.toFixed(0) + "s";
        if (update === 0 || !isFinite(secs)) updated = "n/a";
        else if (secs.toFixed(0) == 0) updated = "now";
        else if (day > 365 && delta > 0) updated = d.toLocaleDateString("en-GB", {year:"numeric", month:"short"});
        else if (day > 31 && delta > 0) updated = d.toLocaleDateString("en-GB", {month:"short", day:"numeric"});
        else if (day > 2) updated = day.toFixed(1) + " days";
        else if (hour > 2) updated = hour.toFixed(0) + " hrs";
        else if (secs > 180) updated = mins.toFixed(0) + " mins";

        secs = Math.abs(secs);
        var color = "rgb(150,150,150)";
        if (delta < 0) color = "rgb(60,135,170)";
        else if (secs < 25) color = "rgb(50,200,50)";
        else if (secs < 60) color = "rgb(240,180,20)";
        else if (secs < 7200) color = "rgb(255,125,20)";
        else if (secs < 2678400) color = "rgb(255,0,0)";

        return "<span style='color:" + color + ";'>" + updated + "</span>";
      },
      deleteDevice: function(d) {
        device_dialog.loadDelete(d, null);
      },
      configDevice: function(d) {
        device_dialog.loadConfig(devices, d);
      },
      newDevice: function() {
        device_dialog.loadConfig(devices, null);
      }
    },
    mounted: function() {
      this.update();
      this.startUpdater(10000);
    }
  });

  // Global update() so device_dialog.js can call it after delete/save
  function update() { deviceApp.update(); }

  $("#device-reload").click(function() {
    $.ajax({ url: path + "device/template/reload.json", async: true, dataType: "json", success: function(result) {
      alert(result.message);
    }});
  });
</script>
