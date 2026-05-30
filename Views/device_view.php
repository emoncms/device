<?php
    defined('EMONCMS_EXEC') or die('Restricted access');
    global $path, $settings, $session;

    $version = 3;

    load_js("Lib/js/vue.global.prod-3.5.22.min.js");
    load_js("Modules/device/Views/device.js");
?>

<style>
/* ── Sticky controls toolbar ─────────────────────────────────────── */
.device-controls-sentinel { height: 0; visibility: hidden; }
.device-controls {
    position: sticky;
    top: var(--feed-top, 46px);
    z-index: 100;
    background: #fff;
    padding: 6px 0;
    margin-bottom: 10px;
    transition: background-color 0.2s ease, box-shadow 0.2s ease, top 0.3s ease-out;
}
.device-controls.is-sticky { background: transparent; box-shadow: none; }
.device-controls.is-sticky::before {
    content: '';
    position: fixed;
    top: var(--feed-top, 46px);
    left: 0;
    width: 100vw;
    height: 44px;
    background: #209ed3;
    z-index: -1;
    transition: top 0.3s ease-out;
}
.device-controls .btn { margin-right: 4px; }

/* ── Device List Grid: 9-column subgrid layout ───────────────────────
 * Col 1 : 40px        — select / chevron
 * Col 2 : max-content — nodeid
 * Col 3 : max-content — name
 * Col 4 : max-content — type
 * Col 5 : max-content — ip
 * Col 6 : max-content — devicekey (truncated)
 * Col 7 : 1fr         — spacer
 * Col 8 : max-content — updated
 * Col 9 : 40px        — configure
 * ────────────────────────────────────────────────────────────────── */
.device-list-grid {
    grid-template-columns: 40px max-content max-content max-content max-content max-content 1fr max-content 40px;
}

.device-list-grid[data-hide-typename]  [data-col="typename"]  { display: none; }
.device-list-grid[data-hide-ip]        [data-col="ip"]        { display: none; }
.device-list-grid[data-hide-devicekey] [data-col="devicekey"] { display: none; }
.device-list-grid[data-hide-updated]   [data-col="updated"]   { display: none; }

.device-list-grid [data-col="select"]    { grid-column: 1; }
.device-list-grid [data-col="nodeid"]    { grid-column: 2; }
.device-list-grid [data-col="name"]      { grid-column: 3; }
.device-list-grid [data-col="typename"]  { grid-column: 4; }
.device-list-grid [data-col="ip"]        { grid-column: 5; }
.device-list-grid [data-col="devicekey"] { grid-column: 6; }
.device-list-grid [data-col="spacer"]    { grid-column: 7; }
.device-list-grid [data-col="updated"]   { grid-column: 8; }
.device-list-grid [data-col="configure"] { grid-column: 9; text-align: center; }

.device-list-cols {
    display: grid;
    grid-column: 1 / -1;
    grid-template-columns: subgrid;
    padding: 4px;
    margin-bottom: 10px;
    font-size: 11px;
    color: #888;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background-color: #f0f0f0;
}
.device-list-cols > div { padding: 0 10px; }

.device-devicekey {
    font-family: monospace;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>

<div style="display:flex; align-items:center; justify-content:space-between;">
    <h3><?php echo tr('Devices'); ?></h3>
    <a href="api"><?php echo tr('Devices Help'); ?></a>
</div>
<div class="device-controls-sentinel"></div>
<div id="device-app">
    <div v-cloak>
        <template v-if="!loaded">
            <div class="ajax-loader"></div>
        </template>
        <template v-else>
            <div class="controls device-controls">
                <button v-if="groups.length > 0" @click="toggleAll" class="btn" :title="allCollapsed ? '<?php echo tr('Expand all'); ?>' : '<?php echo tr('Collapse all'); ?>'">
                    <i :class="allCollapsed ? 'icon-resize-full' : 'icon-resize-small'"></i>
                </button>
                <button v-if="groups.length > 0" @click="toggleSelectAll" class="btn" :title="selected.length > 0 ? '<?php echo tr('Unselect all'); ?>' : '<?php echo tr('Select all'); ?>'">
                    <i :class="selected.length > 0 ? 'icon-ban-circle' : 'icon-check'"></i>
                    <span v-if="selected.length > 0">{{ selected.length }}</span>
                </button>
                <button @click="deleteSelected" v-if="selected.length > 0" class="btn" title="<?php echo tr('Delete'); ?>">
                    <i class="icon-trash"></i>
                </button>
                <button @click="configureSelected" v-if="selected.length === 1" class="btn" title="<?php echo tr('Configure'); ?>">
                    <i class="icon-wrench"></i>
                </button>
                <button @click="newDevice" class="btn btn-warning" style="float:right;" title="<?php echo tr('New device'); ?>">
                    <i class="icon-plus-sign icon-white"></i>&nbsp;<?php echo tr('New device'); ?>
                </button>
            </div>

            <template v-if="groups.length > 0">
                <div class="group-list device-list-grid" ref="container">
                    <div class="device-list-cols">
                        <div data-col="select"></div>
                        <div data-col="nodeid"><?php echo tr('Node'); ?></div>
                        <div data-col="name"><?php echo tr('Name'); ?></div>
                        <div data-col="typename"><?php echo tr('Type'); ?></div>
                        <div data-col="ip"><?php echo tr('IP'); ?></div>
                        <div data-col="devicekey"><?php echo tr('Device key'); ?></div>
                        <div data-col="spacer"></div>
                        <div data-col="updated"><?php echo tr('Updated'); ?></div>
                        <div data-col="configure"></div>
                    </div>
                    <template v-for="group in groups" :key="group.description">
                        <div class="group-list-group">
                            <div
                                class="group-list-header"
                                :class="{ collapsed: !group.open }"
                                :style="{ '--status-color': group.updatedColor }"
                                @click="group.open = !group.open"
                            >
                                <div data-col="select" class="group-list-cell" @click.stop>
                                    <span v-if="selected.length === 0" class="group-list-chevron" @click="group.open = !group.open"></span>
                                    <input v-else type="checkbox"
                                           :checked="isGroupFullySelected(group)"
                                           @change="toggleGroupSelection(group)">
                                </div>
                                <div data-col="nodeid" class="group-list-cell group-list-name">{{ group.description }}</div>
                                <div data-col="name" class="group-list-cell"></div>
                                <div data-col="typename" class="group-list-cell"></div>
                                <div data-col="ip" class="group-list-cell"></div>
                                <div data-col="devicekey" class="group-list-cell"></div>
                                <div data-col="spacer" class="group-list-cell"></div>
                                <div data-col="updated" class="group-list-cell text-center" :style="{ color: group.updatedColor }">{{ group.updatedText }}</div>
                                <div data-col="configure" class="group-list-cell"></div>
                            </div>
                            <div class="group-list-rows" :class="{ 'is-expanded': group.open }">
                                <div class="group-list-rows-inner">
                                    <div
                                        v-for="d in group.devices"
                                        :key="d.id"
                                        class="group-list-row"
                                        :class="{ selected: selected.indexOf(d.id) !== -1 }"
                                        :style="{ '--status-color': d.updatedColor }"
                                    >
                                        <div data-col="select" class="group-list-cell text-center" @click.stop>
                                            <input type="checkbox" :value="d.id" v-model="selected">
                                        </div>
                                        <div data-col="nodeid" class="group-list-cell">{{ d.nodeid }}</div>
                                        <div data-col="name" class="group-list-cell">{{ d.name }}</div>
                                        <div data-col="typename" class="group-list-cell">{{ d.typename }}</div>
                                        <div data-col="ip" class="group-list-cell">{{ d.ip }}</div>
                                        <div data-col="devicekey" class="group-list-cell device-devicekey" :title="d.devicekey">{{ d.devicekey }}</div>
                                        <div data-col="spacer" class="group-list-cell"></div>
                                        <div data-col="updated" class="group-list-cell text-center" :style="{ color: d.updatedColor }">{{ d.updatedText }}</div>
                                        <div data-col="configure" class="group-list-cell">
                                            <a @click.prevent.stop="configureDevice(d)" href="#" title="<?php echo tr('Configure'); ?>">
                                                <i class="icon-wrench"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div style="height:10px; grid-column: 1 / -1;"></div>
                    </template>
                </div>
            </template>

            <div v-else class="alert alert-block">
                <h4 class="alert-heading"><?php echo tr('No devices'); ?></h4><br>
                <p>
                    <?php echo tr('Devices are used to configure and prepare the communication with different physical devices. Devices are grouped by Location for easy tracking when deploying at scale.'); ?>
                    <br><br>
                    <?php echo tr('A device configures and prepares inputs, feeds and other possible settings. e.g. representing different registers of defined metering units.'); ?>
                    <br>
                    <?php echo tr('Follow the next link as a guide for generating your request: '); ?><a href="api"><?php echo tr('Device API helper'); ?></a>
                </p>
            </div>

        </template>
    </div>
</div>

<?php require "Modules/device/Views/device_dialog.php"; ?>

<script>
// device_dialog.js calls the global update() after save and delete — exposed below
var update;

(function () {
    var templates = <?php echo json_encode($templates); ?>;
    var { createApp, ref, computed, watch, onMounted, onUnmounted } = Vue;

    // ── Time formatting ──────────────────────────────────────────────────
    var STATUS_COLORS = [
        'rgb(60,135,170)',   // 0 blue   — ahead of server time
        'rgb(50,200,50)',    // 1 green
        'rgb(240,180,20)',   // 2 yellow
        'rgb(255,125,20)',   // 3 orange
        'rgb(255,0,0)',      // 4 red
        'rgb(150,150,150)',  // 5 grey   — inactive / n/a
    ];

    function formatUpdated(time, serverOffset) {
        if (!time) return { text: 'n/a', color: STATUS_COLORS[5] };
        var now   = Date.now() - serverOffset;
        var delta = now - time * 1000;
        var secs  = Math.abs(delta) / 1000;
        var date  = new Date(time * 1000);

        var text;
        if (!isFinite(secs))         text = 'n/a';
        else if (secs < 0.5)         text = 'now';
        else if (delta < 0)          text = 'ahead';
        else if (secs / 86400 > 365) text = date.toLocaleDateString('en-GB', { year: 'numeric', month: 'short' });
        else if (secs / 86400 > 31)  text = date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' });
        else if (secs / 86400 > 2)   text = (secs / 86400).toFixed(0) + ' days';
        else if (secs / 3600 > 2)    text = (secs / 3600).toFixed(0) + ' hrs';
        else if (secs > 180)         text = (secs / 60).toFixed(0) + ' mins';
        else                         text = secs.toFixed(0) + 's';

        var code = 5;
        if (isFinite(secs)) {
            if (delta < 0)               code = 0;
            else if (secs < 30)          code = 1;
            else if (secs < 60)          code = 2;
            else if (secs < 3600)        code = 3;
            else if (secs < 3600*24*31)  code = 4;
        }
        return { text: text, color: STATUS_COLORS[code] };
    }

    // ── Group devices by description (Location), preserving open state ────
    function buildGroups(rawData, serverOffset, existingGroups) {
        var openState = {};
        if (existingGroups) {
            existingGroups.forEach(function (g) { openState[g.description] = g.open; });
        }

        var groupMap = new Map();
        rawData.forEach(function (d) {
            var loc = d.description || '(no location)';
            if (!groupMap.has(loc)) groupMap.set(loc, []);
            groupMap.get(loc).push(d);
        });

        return Array.from(groupMap.entries()).map(function (entry) {
            var description = entry[0];
            var devList     = entry[1];
            var bestTime    = null;

            var deviceRows = devList.map(function (d) {
                var typename = '';
                if (d.type && templates[d.type]) typename = templates[d.type].name;
                var fv = formatUpdated(d.time, serverOffset);
                if (d.time && (!bestTime || d.time > bestTime)) bestTime = d.time;
                return {
                    id:           d.id,
                    nodeid:       d.nodeid    || '',
                    name:         d.name      || '',
                    typename:     typename,
                    ip:           d.ip        || '',
                    devicekey:    d.devicekey || '',
                    updatedText:  fv.text,
                    updatedColor: fv.color,
                    raw:          d,
                };
            });

            var gfv    = formatUpdated(bestTime, serverOffset);
            var wasOpen = openState[description];
            return {
                description:  description,
                updatedText:  gfv.text,
                updatedColor: gfv.color,
                devices:      deviceRows,
                open:         wasOpen !== undefined ? wasOpen : true,
            };
        });
    }

    // ── Vue app ──────────────────────────────────────────────────────────
    createApp({
        setup() {
            var loaded       = ref(false);
            var groups       = ref([]);
            var selected     = ref([]);
            var container    = ref(null);
            var serverOffset = 0;
            var timer        = null;

            var allCollapsed = computed(function () {
                return groups.value.length > 0 && groups.value.every(function (g) { return !g.open; });
            });

            function fetchDevices() {
                var requestTime = Date.now();
                $.ajax({
                    url: path + 'device/list.json',
                    dataType: 'json',
                    async: true,
                    success: function (data, _status, xhr) {
                        serverOffset = requestTime - new Date(xhr.getResponseHeader('Date')).getTime();
                        groups.value = buildGroups(data, serverOffset, groups.value);
                        loaded.value = true;
                        // Drop any selections for devices that no longer exist
                        var ids = data.map(function (d) { return d.id; });
                        selected.value = selected.value.filter(function (id) { return ids.indexOf(id) !== -1; });
                    }
                });
            }

            // Exposed globally — device_dialog.js calls update() after config save
            update = fetchDevices;

            function startTimer() { stopTimer(); timer = setInterval(fetchDevices, 10000); }
            function stopTimer()  { if (timer) { clearInterval(timer); timer = null; } }

            // ── Expand / collapse ──────────────────────────────────────────
            function toggleAll() {
                var collapse = !allCollapsed.value;
                groups.value.forEach(function (g) { g.open = !collapse; });
            }

            // ── Selection helpers ──────────────────────────────────────────
            function toggleSelectAll() {
                if (selected.value.length > 0) {
                    selected.value = [];
                } else {
                    var all = [];
                    groups.value.forEach(function (g) {
                        g.devices.forEach(function (d) { all.push(d.id); });
                    });
                    selected.value = all;
                }
            }

            function isGroupFullySelected(group) {
                return group.devices.length > 0 && group.devices.every(function (d) {
                    return selected.value.indexOf(d.id) !== -1;
                });
            }

            function toggleGroupSelection(group) {
                if (isGroupFullySelected(group)) {
                    selected.value = selected.value.filter(function (id) {
                        return !group.devices.some(function (d) { return d.id === id; });
                    });
                } else {
                    group.devices.forEach(function (d) {
                        if (selected.value.indexOf(d.id) === -1) selected.value.push(d.id);
                    });
                }
            }

            function findDevice(id) {
                for (var i = 0; i < groups.value.length; i++) {
                    var devs = groups.value[i].devices;
                    for (var j = 0; j < devs.length; j++) {
                        if (devs[j].id === id) return devs[j];
                    }
                }
                return null;
            }

            // ── Delete (top controls) ──────────────────────────────────────
            function deleteSelected() {
                if (selected.value.length === 1) {
                    var d = findDevice(selected.value[0]);
                    if (d) device_dialog.loadDelete(d.raw, null);
                } else {
                    var ids = selected.value.slice();
                    $('#device-delete-modal-label').html(
                        '<?php echo tr('Delete'); ?> <b>' + ids.length + '</b> <?php echo tr('devices'); ?>'
                    );
                    $('#device-delete-confirm').off('click').on('click', function () {
                        ids.forEach(function (id) { device.remove(id); });
                        selected.value = [];
                        $('#device-delete-modal').modal('hide');
                        fetchDevices();
                    });
                    $('#device-delete-modal').modal('show');
                }
            }

            // ── Configure (top controls, single selection) ─────────────────
            function configureSelected() {
                var d = findDevice(selected.value[0]);
                if (d) device_dialog.loadConfig(templates, d.raw);
            }

            // ── Per-row configure wrench ───────────────────────────────────
            function configureDevice(d) { device_dialog.loadConfig(templates, d.raw); }
            function newDevice()        { device_dialog.loadConfig(templates, null); }

            onMounted(function () {
                fetchDevices();
                startTimer();

                // After delete with row=null, device_dialog fires device-delete on #wrap
                $('#wrap').on('device-delete', fetchDevices);

                // Sticky controls: sync --feed-top then toggle is-sticky via IntersectionObserver
                var sentinel = document.querySelector('.device-controls-sentinel');
                if (sentinel && 'IntersectionObserver' in window) {
                    var nav = document.querySelector('.menu-top');
                    function updateTop() {
                        var h = (nav && !nav.classList.contains('menu-top-hide')) ? nav.offsetHeight : 0;
                        document.documentElement.style.setProperty('--feed-top', h + 'px');
                    }
                    if (nav) new MutationObserver(updateTop).observe(nav, { attributes: true, attributeFilter: ['class'] });
                    updateTop();
                    new IntersectionObserver(function (entries) {
                        var ctrl = document.querySelector('.device-controls');
                        if (ctrl) ctrl.classList.toggle('is-sticky', !entries[0].isIntersecting);
                    }, { rootMargin: '-46px 0px 0px 0px', threshold: 0 }).observe(sentinel);
                }

                // Responsive column hiding — watch for container to appear (inside v-if)
                var hideCols = ['devicekey', 'ip', 'typename', 'updated'];
                watch(container, function (el) {
                    if (!el || !window.ResizeObserver) return;
                    var raf = null;
                    new ResizeObserver(function () {
                        if (raf) cancelAnimationFrame(raf);
                        raf = requestAnimationFrame(function () {
                            raf = null;
                            hideCols.forEach(function (c) { el.removeAttribute('data-hide-' + c); });
                            var header = el.querySelector('.group-list-header');
                            if (!header) return;
                            for (var i = 0; i < hideCols.length; i++) {
                                if (header.scrollWidth > el.clientWidth) {
                                    el.setAttribute('data-hide-' + hideCols[i], '');
                                } else {
                                    break;
                                }
                            }
                        });
                    }).observe(el);
                });
            });

            onUnmounted(function () {
                stopTimer();
                $('#wrap').off('device-delete', fetchDevices);
            });

            return {
                loaded, groups, selected, container, allCollapsed,
                toggleAll, toggleSelectAll,
                isGroupFullySelected, toggleGroupSelection,
                deleteSelected, configureSelected,
                configureDevice, newDevice,
            };
        }
    }).mount('#device-app');
})();
</script>
