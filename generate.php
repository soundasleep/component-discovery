<?php

/**
 * Generates the necessary includes from the given file root.
 */

require(__DIR__ . "/functions.php");

if (count($argv) < 2) {
  throw new Exception("Needs file root parameter");
}

$root = $argv[1];
if (!file_exists($root . "/discovery.json")) {
  throw new Exception("No discovery.json found in '$root'");
}

$json = json_decode(file_get_contents($root . "/discovery.json"), true /* assoc */);

// add default parameters
$json += array(
  'namespace' => 'DiscoveredComponents',
  'src' => 'vendor/*/*',
  'dest' => 'generated/components',
  'depth' => 3,
  'autoload' => 'vendor/autoload.php'
);

if (!is_array($json['src'])) {
  $json['src'] = array($json['src']);
}
echo "Loaded " . count($json['components']) . " component discovery patterns\n";

// load autoloader if necessary
if ($json["autoload"]) {
  require($json["autoload"]);
}

// make target directories as necessary
make_target_directories(array($json['dest'] . "/"));

// now load all of the components
$all_dirs = get_all_directories($root, $json['depth']);
echo "Found " . count($all_dirs) . " potential subdirectories\n";
$selected_dirs = array();
foreach ($json['src'] as $pattern) {
  $selected_dirs = array_merge($selected_dirs, get_directories_to_search($all_dirs, $pattern));
}
echo "Filtered to " . count($selected_dirs) . " matching paths\n";

$component_discoverers = array();

if ($selected_dirs) {
  foreach ($json['components'] as $key => $component_value) {
    if (!is_array($component_value)) {
      $component_value = array(
        "file" => $component_value,
      );
    }

    // default values
    $component_value += array(
      "maps" => array(),
      "masks" => array(),
      "lists" => array(),
    );

    $filename = $component_value["file"];

    $check_instance_of = isset($component_value["instanceof"]) ? $component_value["instanceof"] : false;

    echo "Processing '$key' components...\n";
    $full_name = ucfirst($key);
    $namespace = $json['namespace'];
    $component_discoverers[] = "      \"$key\" => new $namespace\\$full_name()";

    $keys = array();
    $instances = array();
    $all_instances = array();
    $maps = array();
    $masks = array();
    $lists = array();

    $count = 0;
    foreach ($selected_dirs as $dir) {
      if (file_exists($dir . "/" . $filename)) {
        $component = json_decode(file_get_contents($dir . "/" . $filename), true);
        if (!$component) {
          throw new Exception("Could not load JSON from '$dir/$filename'");
        }
        foreach ($component as $component_key => $classname) {
          $keys[] = "\"$component_key\"";
          $instances[] = "      case \"$component_key\": return new $classname(\$config);";
          $all_instances[] = "\"$component_key\" => new $classname(\$config)";

          if ($check_instance_of || $component_value["maps"] || $component_value["lists"]) {
            // instantiate object (using the autoloader as necessary)
            $object = new $classname;

            // do we want to check that this classname is a valid instanceof?
            if ($check_instance_of) {
              if (!is_a($object, $check_instance_of)) {
                throw new Exception("Classname '$classname' is not an instance of '$check_instance_of'");
              }
            }

            // do we want to make any lists or maps?
            foreach ($component_value["maps"] as $key => $method) {
              if (!isset($maps[$key])) {
                $maps[$key] = array();
              }
              $maps[$key][] = "      case \"" . $object->$method() . "\": return \"$component_key\";";
            }

            foreach ($component_value["masks"] as $key => $method) {
              if (!isset($masks[$key])) {
                $masks[$key] = array();
              }
              if ($object->$method()) {
                $masks[$key][] = "\"$component_key\"";
              }
            }

            foreach ($component_value["lists"] as $key => $method) {
              if (!isset($lists[$key])) {
                $lists[$key] = array();
              }
              if ($temp = $object->$method()) {
                $lists[$key][] = is_numeric($temp) ? "$temp" : "\"$temp\"";
              }
            }
          }

        }
        $count++;
      }
    }

    echo "Found $count '$key' components\n";
    $keys = implode(", ", $keys);
    $instances = implode("\n", $instances);
    $all_instances = implode(", ", $all_instances);

    $output_maps = array();
    foreach ($maps as $key => $values) {
      $output_maps[] = "  static function $key(\$input) {
    switch (\$input) {
" . implode("\n", $values) . "
      default:
        throw new \\ComponentDiscovery\\DiscoveryException(\"Could not find any matching $key for '\$input'\");
    }
  }";
    }

    $output_maps = implode($output_maps, "\n\n");

    $output_masks = array();
    foreach ($masks as $key => $values) {
      $output_masks[] = "  static function $key() {
    return array(" . implode(", ", $values) . ");
  }";
    }

    $output_masks = implode($output_masks, "\n\n");

    $output_lists = array();
    foreach ($lists as $key => $values) {
      $output_lists[] = "  static function $key() {
    return array(" . implode(", ", $values) . ");
  }";
    }

    $output_lists = implode($output_lists, "\n\n");

    $fp = fopen($json['dest'] . "/$full_name.php", "w");
    if (!$fp) {
      throw new Exception("Could not open destination file '" . $json['dest'] . "/$key.php' for writing");
    }
    fwrite($fp, "<?php

/**
 * @generated by component-discovery DO NOT EDIT
 */

namespace $namespace;

class $full_name extends \\ComponentDiscovery\\Base {
  static function getKeys() {
    return array($keys);
  }

  static function getInstance(\$key, \$config = false) {
    switch (\$key) {
$instances
      default:
        throw new \\ComponentDiscovery\\DiscoveryException(\"Could not find any $full_name with key '\$key'\");
    }
  }

  static function getAllInstances(\$config = false) {
    return array($all_instances);
  }

  // maps
$output_maps

  // masks
$output_masks

  // lists
$output_lists

}
");
    fclose($fp);
  }
}

// write a all.php
$component_discoverers = implode(",\n", $component_discoverers);
$fp = fopen($json['dest'] . "/All.php", "w");
fwrite($fp, "<?php

/**
 * @generated by component-discovery DO NOT EDIT
 */

namespace $namespace;

class All {
  static function getAllComponentTypes() {
    return array(
$component_discoverers
    );
  }
}
");

echo "Complete.";

// TODO generate a development version that does not require a rebuild?
