<?php
defined('EMONCMS_EXEC') or die('Restricted access');

/*
  Structured description of the device module HTTP API.

  Schema per endpoint (consumed by Lib/api_explorer_view.php):
  - description: short human name
  - path:        URL path relative to the emoncms base
  - parameters:  name => array(default, type, options, description)
                 type: "feed" (feed selector), "bool", "select" or text input
  - mode:        "read" or "write" (which apikey is required)
  - group:       section heading used to group endpoints in the docs
  - http:        "post" where one or more parameters must be sent in the POST
                 body (noted in the parameter description)
  - destructive: true where the interactive explorer should warn that the
                 change cannot be undone. Every write endpoint is confirmed
                 before it runs, this escalates the wording
  - notes:       extra documentation: aliases, formats, gotchas
*/

function device_api_obj() {

  return array(
    // ------------------------------------------------------------------
    // Device list & details
    // ------------------------------------------------------------------
    array(
      "description" => tr("List devices"),
      "path" => "device/list.json",
      "parameters" => array(),
      "mode" => "read",
      "group" => tr("Device list & details"),
      "notes" => tr("Returns id, nodeid, name, description, type, devicekey and the time of the last update for every device in the account, ordered by nodeid then name.")
    ),
    array(
      "description" => tr("Get device details"),
      "path" => "device/get.json",
      "parameters" => array(
        "id" => array( "default" => 1, "description" => tr("Device id, as returned by device/list.json") )
      ),
      "mode" => "write",
      "group" => tr("Device list & details"),
      "notes" => tr("Responds {\"success\": false, \"message\": \"Device does not exist\"} for an unknown id. The devicekey is included in the response, so a read only key is not accepted here.")
    ),

    // ------------------------------------------------------------------
    // Managing devices
    // ------------------------------------------------------------------
    array(
      "description" => tr("Create a device"),
      "path" => "device/create.json",
      "parameters" => array(
        "nodeid" => array( "default" => "emontx", "description" => tr("Must be unique within the account, this is the node name inputs are posted to") ),
        "name" => array( "default" => "Test", "description" => tr("Optional, defaults to the nodeid") ),
        "description" => array( "default" => "House", "description" => tr("Optional") ),
        "type" => array( "default" => "", "description" => tr("Optional device template type, e.g. emontx3, see device/template/listshort.json") ),
        "dkey" => array( "default" => "", "description" => tr("Optional device key, 32 hexadecimal characters") )
      ),
      "mode" => "write",
      "group" => tr("Managing devices"),
      "notes" => tr("Returns the new device id. Creating a device does not create its inputs and feeds, call device/init.json afterwards to apply the template. Names, nodeids and descriptions accept letters, numbers, spaces and _ - : . only.")
    ),
    array(
      "description" => tr("Create and initialise a device"),
      "path" => "device/autocreate.json",
      "parameters" => array(
        "nodeid" => array( "default" => "emontx", "description" => tr("Node name the device posts inputs to") ),
        "type" => array( "default" => "emontx3", "description" => tr("Device template type") )
      ),
      "mode" => "write",
      "group" => tr("Managing devices"),
      "destructive" => true,
      "notes" => tr("Used by the input name describe mechanism to register a device in one call. Creates the device if the nodeid is not already in use, names it nodeid:type and then initialises it from the template. Calling it again for an existing nodeid re-runs the template.")
    ),
    array(
      "description" => tr("Update device fields"),
      "path" => "device/set.json",
      "parameters" => array(
        "id" => array( "default" => 1 ),
        "fields" => array( "default" => '{"name":"anewname"}', "description" => tr("name, description, nodeid, type and devicekey can be set") )
      ),
      "mode" => "write",
      "group" => tr("Managing devices"),
      "notes" => tr("Only the fields present in the JSON object are changed. A devicekey must be exactly 32 hexadecimal characters: use device/generatekey.json to create one.")
    ),
    array(
      "description" => tr("Delete a device"),
      "path" => "device/delete.json",
      "parameters" => array(
        "id" => array( "default" => 1 )
      ),
      "mode" => "write",
      "group" => tr("Managing devices"),
      "notes" => tr("Removes the device registration only. The inputs and feeds it created are left in place.")
    ),
    array(
      "description" => tr("Remove inactive devices and inputs"),
      "path" => "device/clean",
      "parameters" => array(
        "active" => array( "default" => 3600, "description" => tr("Seconds, inputs not updated within this time are treated as inactive") ),
        "dryrun" => array( "type" => "bool", "default" => 1, "description" => tr("List what would be removed without changing anything") )
      ),
      "mode" => "write",
      "group" => tr("Managing devices"),
      "notes" => tr("Responds in plain text with a summary of what was, or would be, removed. Inactive inputs that are not logging to a feed are deleted, along with any device left without inputs. Always run with dryrun=1 first.")
    ),

    // ------------------------------------------------------------------
    // Device keys
    // ------------------------------------------------------------------
    array(
      "description" => tr("Generate a random device key"),
      "path" => "device/generatekey.json",
      "parameters" => array(),
      "mode" => "write",
      "group" => tr("Device keys"),
      "notes" => tr("Returns a new random key without saving it. Apply it with device/set.json and fields={\"devicekey\":\"...\"}, or use device/setnewdevicekey.json to generate and save in one call.")
    ),
    array(
      "description" => tr("Set a new random device key"),
      "path" => "device/setnewdevicekey.json",
      "parameters" => array(
        "id" => array( "default" => 1 )
      ),
      "mode" => "write",
      "group" => tr("Device keys"),
      "notes" => tr("Generates a new key and saves it against the device, replacing any existing key. Anything posting with the old key stops working.")
    ),

    // ------------------------------------------------------------------
    // Device templates
    // ------------------------------------------------------------------
    array(
      "description" => tr("List template metadata"),
      "path" => "device/template/listshort.json",
      "parameters" => array(),
      "mode" => "write",
      "group" => tr("Device templates"),
      "notes" => tr("Name, category, group and description for every available template, keyed by the type string used in device/create.json. Use this to pick a device type.")
    ),
    array(
      "description" => tr("List templates in full"),
      "path" => "device/template/list.json",
      "parameters" => array(),
      "mode" => "write",
      "group" => tr("Device templates"),
      "notes" => tr("The complete definitions, including the inputs, feeds and process lists of every template. This is a large response, device/template/listshort.json is usually enough.")
    ),
    array(
      "description" => tr("Get template details"),
      "path" => "device/template/get.json",
      "parameters" => array(
        "type" => array( "default" => "emontx3", "description" => tr("Template type string, see device/template/listshort.json") )
      ),
      "mode" => "write",
      "group" => tr("Device templates")
    ),
    array(
      "description" => tr("Prepare device initialization"),
      "path" => "device/template/prepare.json",
      "parameters" => array(
        "id" => array( "default" => 1 )
      ),
      "mode" => "write",
      "group" => tr("Device templates"),
      "notes" => tr("Returns the inputs, feeds and process lists the device type's template would create, each marked with whether it already exists. Nothing is created. Edit this result and send it back to device/template/init.json to control what is applied. Adding type=... to the request sets the device type before preparing, so pass it only when you mean to change the type.")
    ),
    array(
      "description" => tr("Prepare a custom template"),
      "path" => "device/template/prepare_custom.json",
      "parameters" => array(
        "id" => array( "default" => 1 ),
        "template" => array( "default" => '{"feeds":[],"inputs":[]}', "post" => true, "description" => tr("Template JSON, must be sent in the POST body") )
      ),
      "mode" => "write",
      "group" => tr("Device templates"),
      "http" => "post",
      "notes" => tr("As prepare, but for a template supplied in the request rather than the device type's stored template.")
    ),
    array(
      "description" => tr("Initialize device from its template"),
      "path" => "device/init.json",
      "parameters" => array(
        "id" => array( "default" => 1 ),
        "template" => array( "default" => "", "description" => tr("Optional prepared template JSON, leave empty to apply the device type's default") )
      ),
      "mode" => "write",
      "group" => tr("Device templates"),
      "destructive" => true,
      "notes" => tr("Creates the inputs, feeds and process lists defined by the device type. A device should only need initializing once: initializing twice duplicates its inputs and feeds. The template parameter may be sent in the query string or the POST body.")
    ),
    array(
      "description" => tr("Initialize device with a prepared template"),
      "path" => "device/template/init.json",
      "parameters" => array(
        "id" => array( "default" => 1 ),
        "template" => array( "default" => '{"feeds":[],"inputs":[]}', "post" => true, "description" => tr("Prepared template JSON, must be sent in the POST body") )
      ),
      "mode" => "write",
      "group" => tr("Device templates"),
      "http" => "post",
      "destructive" => true,
      "notes" => tr("Takes the result of device/template/prepare.json, so the caller decides which inputs and feeds are created. The device must have a type set.")
    ),
    array(
      "description" => tr("Initialize device with a custom template"),
      "path" => "device/template/init_custom.json",
      "parameters" => array(
        "id" => array( "default" => 1 ),
        "template" => array( "default" => '{"feeds":[],"inputs":[]}', "post" => true, "description" => tr("Template JSON, must be sent in the POST body") )
      ),
      "mode" => "write",
      "group" => tr("Device templates"),
      "http" => "post",
      "destructive" => true,
      "notes" => tr("Applies a template supplied in the request, no device type is required.")
    ),
    array(
      "description" => tr("Generate a template from a device"),
      "path" => "device/template/generate.json",
      "parameters" => array(
        "id" => array( "default" => 1 )
      ),
      "mode" => "write",
      "group" => tr("Device templates"),
      "notes" => tr("Builds a template definition from the device's existing inputs, feeds and process lists, the reverse of initialization. Useful for turning a configured device into a reusable template file.")
    )
  );
}
