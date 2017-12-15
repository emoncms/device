<?php
global $path, $session;
$v = 6;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/device/Views/device.js?v=<?php echo $v; ?>"></script>

<link href="<?php echo $path; ?>Modules/app/css/titatoggle-dist-min.css?v=<?php echo $v; ?>" rel="stylesheet">

<style>
    .checkbox-slider--b {
        width: 20px;
        border-radius: 25px;
        background-color: gainsboro;
        height: 20px;
    }
    
    *::before, *::after {
        box-sizing: border-box;
    }
    
    .electric-title {
        font-weight: bold;
        font-size: 22px;
        color: #44b3e2;
    }
    
    .block-title {
        font-weight: bold;
        padding: 10px;
    }
    
    .power-value {
        font-weight: bold; 
        font-size: 52px; 
        color: #44b3e2;
        line-height: 1.1;
    }
    
    .units {
        font-size: 75%;
    }
    
    .block-bound {
      background-color: rgb(68,179,226);
    }
    
    .block-content {
        background-color:#fff;
        color:#333;
        padding:10px;
    }
    
    .list-item {
        margin-bottom: 12px;
    }
    
    .collapse-graph {
       width: 100%; 
       text-align: right; 
       border-bottom: 1px solid lightgray; 
       line-height: 0.1em;
       margin: 10px 0 5px; 
       color: lightgray;
    } 
    
    .collapse-graph a { 
        background:#fff; 
        padding:0 10px; 
    }
    
/*     .icon-chevron-down, .icon-chevron-up {  */
/*         opacity: 0.35;  */
/*     } */
    
    .control-label {
        color: grey;
        font-weight: bold;
        padding-top: 5px;
    }
    
    .name {
        text-align: right;
        width: 75%;
    }
    
    .left {
        text-align: right;
        width: 15%;
        padding-right: 0.7%;
    }
    
    .control {
        text-align: right;
        width: 5%;
    }
    
    .right {
        text-align: left;
        width: 10%;
    }
    
    .switch {
    
    }
    
    input.number {
        width: 27px;
        color: grey;
        background-color: white;
        margin-bottom: 5px;
        margin-right: 5px;
    }
    
     input.number[disabled] {
         background-color: #eee;
     }
   
   .device {
        margin-bottom:10px;
        border: 1px solid #aaa;
    }
    
    .device-info {
        background-color:#ddd;
        cursor:pointer;
    }
 
    .device-controls {
        padding: 0px 5px 5px 5px;
        background-color:#ddd;
    }
    
    .device-control {
        background-color:#f0f0f0;
        border-bottom:1px solid #fff;
        border-left:2px solid #f0f0f0;
        height:41px;
    }
    
</style>

<div>
  <div id="api-help-header" style="float:right;"><a href="api"><?php echo _('Devices Help'); ?></a></div>
  <div id="local-header"><h2><?php echo _('Devices'); ?></h2></div>
  
  <div id="device-list" style="width:100%"></div>
  
  <div id="device-none" class="hide">
    <div class="alert alert-block">
        <h4 class="alert-heading"><?php echo _('No devices'); ?></h4><br>
        <p>
            <?php echo _('Devices are used to configure and prepare the communication with different physical devices.'); ?>
            <br><br>
            <?php echo _('A device configures and prepares inputs, feeds possible other settings, representing e.g. different registers of defined metering units.'); ?>
            <br>
            <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('Device API helper'); ?></a>
        </p>
    </div>
  </div>
</div>

<div class="ajax-loader"><img src="<?php echo $path; ?>Modules/app/images/ajax-loader.gif"/></div>

<script>

// ----------------------------------------------------------------------
// Globals
// ----------------------------------------------------------------------
var path = "<?php print $path; ?>";
var sessionWrite = <?php echo $session['write']; ?>;

// ----------------------------------------------------------------------
// Display
// ----------------------------------------------------------------------
$("body").css('background-color', '#222');
$(window).ready(function() {
    $("#footer").css('background-color', '#181818');
    $("#footer").css('color', '#999');
    init();
    show();
});
if (!sessionWrite) $(".openconfig").hide();

var config = {};
config.devices = device.listControls();

function init() {
	config.devices.forEach(function(d) {
		config[d.id] = {};
		$("#device-list").append(
	          	"<div class='block-bound list-item device'>" +
    	          	"<div class='device-info'>" +
        	          	"<div class='device-controls'>" +
            	          	"<table id='device" + d.id + "' style='width:100%'><tr>" +
            	              "<td>" +
            	                "<div class='block-title' style='color: grey;'>" + d.name + "</div>" +
            	              "</td>" +
            	         	"</tr>" +
            	         	"</table>" +
        	         	"</div>" +
    	         	"</div>" +
	          "</div>"
		);

		config[d.id]["consumption" + d.id] = {
			     "type": "checkbox",
			     "default": false,
		         "name": d.name+": Consumption",
		         "description": "Show device energy consumption"
		};
		
		d.control.forEach(function(c) {
			if(c.type === "Switch") {
        		$("#device" + d.id).append(
        	    		"<table class='device-control' id='" + d.id + "_" + c.id + "' style='width:100%'><tr>" +
    	                "<td class='control-label name'>" +
    	              	  "<span>" + c.label + "</span>" +
    	                "</td>" +
    	                "<td class='control-label left'>" +
    	              	  "<span>Off</span>" +
    	                "</td>" +
    	        		"<td class='control-label control'>" +
    	              	"<div class='checkbox checkbox-slider--b checkbox-slider-info'>" +
    	        			"<label>" +
    	        				"<input id='device" + d.id + "_output" + c.id + "' type='checkbox'><span></span>" +
    	        			"</label>" +
    	        		"</div>" +
    	              "</td>" +
                      "<td class='control-label right'>" +
                  	    "<span>On</span>" +
                      "</td>" +
    	              "</tr></table>"
              	);
			} else if(c.type === "Text") {
				$("#device" + d.id).append(
    	    		  "<table class='device-control' id='" + d.id + "_" + c.id + "' style='width:100%'><tr>" +
	                  "<td class='control-label name'>" +
	              	    "<span>" + c.label + "</span>" +
	                  "</td>" +
  	                "<td class='control-label left'>" +
	                "</td>" +
	        		  "<td class='control-label control'>" +
	              	    "<input class='number' id='device" + d.id + "_output" + c.id + "' type='text' value=" + (c.value ? c.value : 0).toFixed(c.format[2]) + " />" +
    	              "</td>" +
                      "<td class='control-label right'>" +
                  	    "<span>" + c.format.split(" ")[1] + "</span>" +
                      "</td>" +
    	              "</tr></table>"
              	);
			}
			config[d.id]["device" + d.id + "_output" + c.id] = {
				     "type": "checkbox",
				     "default": true,
			         "name": d.name + ": " + c.label,
			         "description": "Control power state"
			};

			var outputTag = $("#device" + d.id + "_output" + c.id);
			if(c.mode === "read") {
				outputTag.prop('disabled', true);
			}
			outputTag.prop('checked', c.value === "1");
			
			//-------------------------------------------------------------------------------
			//EVENTS
			//-------------------------------------------------------------------------------
			outputTag.click(function() {
				if(c.type === "Switch") {
    				var checked = !outputTag.is(':checked');
    				if(checked) {
    					device.setControlOff(d.id, c.id, function(result) {
        					if(!result.success) {
            					console.log(result.message);
        						outputTag.prop('checked', true);
        					}
        				});
    				} else {
    					device.setControlOn(d.id, c.id, function(result) {
        					if(!result.success) {
        						console.log(result.message);
        						outputTag.prop('checked', false);
        					}
        				});
    				}
				} else if(c.type === "Number") {
					device.setControl(d.id, c.id, outputTag.value, function(result) {
    					if(!result.success) {
    						console.log(result.message);
    						outputTag.value = c.value;
    					}
    				});
				}
			});
		});
	});
}
    
function show() {
	config.devices.forEach(function(d) {
		d.control.forEach(function(c) {
    		var conf = config[d.id]["device" + d.id + "_output" + c.id];
    	    if (typeof conf.value === 'undefined' ? conf.default : conf.value) {
    	        $("#"+d.id+"_"+c.id).show();
    	    }
    	    else {
    	        $("#"+d.id+"_"+c.id).hide();
    	    }
    	});
	});

	if (config.devices.length != 0) {
        $("#device-none").hide();
        $("#local-header").show();
    } else {
        $("#device-none").show();
        $("#local-header").hide();
    }
    
	$("body").css('background-color','WhiteSmoke');
    $(".ajax-loader").hide();
}

// ----------------------------------------------------------------------
// App log
// ----------------------------------------------------------------------
function appLog(level, message) {
    if (level == "ERROR") {
        alert(level + ": " + message);
    }
    console.log(level + ": " + message);
}

</script>