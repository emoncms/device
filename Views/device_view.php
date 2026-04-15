<?php
    defined('EMONCMS_EXEC') or die('Restricted access');
    global $path, $settings, $session;
    
    $version = 3;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device.js?v=<?php echo $version; ?>"></script>
<script src="<?php echo $path; ?>Lib/vue.min.js"></script>

<style>
#device-app input[type="text"] { width: 88%; }
#device-app td:nth-of-type(1) { width: 5%; }
#device-app th:nth-of-type(6), #device-app td:nth-of-type(6) { text-align: right; }
#device-app th:nth-of-type(7), #device-app td:nth-of-type(7) { text-align: right; }
#device-app .group-updated { font-weight: normal; text-align: right; }
#device-app td.action-col { width: 14px; text-align: center; }
#device-app .device-action { cursor: pointer; }
</style>

<div id="device-app">
    <div v-show="deviceList.length" style="float:right;"><a href="api"><?php echo tr('Devices Help'); ?></a></div>
    <div v-show="deviceList.length"><h2><?php echo tr('Devices Location'); ?></h2></div>

    <div v-if="!deviceList.length && !loading" class="alert alert-block">
        <h4 class="alert-heading"><?php echo tr('No devices'); ?></h4><br>
        <p>
            <?php echo tr('Devices are used to configure and prepare the communication with different physical devices. Devices are grouped by Location for easy tracking when deploying at scale.'); ?>
            <br><br>
            <?php echo tr('A device configures and prepares inputs, feeds and other possible settings. e.g. representing different registers of defined metering units.'); ?>
            <br>
            <?php echo tr('Follow the next link as a guide for generating your request: '); ?><a href="api"><?php echo tr('Device API helper'); ?></a>
        </p>
    </div>

    <table v-if="deviceList.length" class="table table-hover">
        <template v-for="(group, groupName) in groupedDevices">
            <!-- Group header row -->
            <tbody :key="'hdr-' + groupName">
                <tr>
                    <th colspan="3">
                        <i class="device-action"
                           :class="collapsed[groupName] ? 'icon-plus-sign' : 'icon-minus-sign'"
                           @click="toggleGroup(groupName)"></i>
                        <a class="device-action" @click="toggleGroup(groupName)">{{ groupName }}</a>
                    </th>
                    <th></th>
                    <th></th>
                    <th class="group-updated" v-html="formatUpdated(groupMaxTime(group))"></th>
                    <th></th>
                    <th></th>
                </tr>
                <!-- Column headers -->
                <tr v-show="!collapsed[groupName]">
                    <th><?php echo tr('Node'); ?></th>
                    <th><?php echo tr('Name'); ?></th>
                    <th><?php echo tr('Location'); ?></th>
                    <th><?php echo tr('Type'); ?></th>
                    <th><?php echo tr('IP'); ?></th>
                    <th><?php echo tr('Device access key'); ?></th>
                    <th><?php echo tr('Updated'); ?></th>
                    <th></th>
                    <th></th>
                </tr>
            </tbody>
            <!-- Data rows -->
            <tbody :key="'rows-' + groupName" v-show="!collapsed[groupName]">
                <tr v-for="d in group" :key="d.id">
                    <td>{{ d.nodeid }}</td>
                    <td>{{ d.name }}</td>
                    <td>{{ d.description }}</td>
                    <td>{{ d.typename }}</td>
                    <td>{{ d.ip }}</td>
                    <td>{{ d.devicekey }}</td>
                    <td v-html="formatUpdated(d.time)"></td>
                    <td class="action-col">
                        <a class="device-action" @click="deleteDevice(d)"><i class="icon-trash"></i></a>
                    </td>
                    <td class="action-col">
                        <i v-if="!d['#NO_CONFIG#']" class="icon-wrench device-action" @click="configDevice(d)"></i>
                    </td>
                </tr>
            </tbody>
        </template>
    </table>

    <div class="ajax-loader" v-show="loading"></div>

    <div id="toolbar_bottom"><hr>
        <button class="btn btn-small" @click="newDevice">&nbsp;<i class="icon-plus-sign"></i>&nbsp;<?php echo tr('New device'); ?></button>
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
