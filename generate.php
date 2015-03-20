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
if (!$json) {
  throw new Exception("Could not load JSON from '$root/discovery.json'");
}

// add default parameters
$json += array(
  'namespace' => 'DiscoveredComponents',
  'src' => 'vendor/*/*',
  'dest' => 'generated/components',
  'depth' => 3,
  'composer_depth' => 3,
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
      "instances" => array(),
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
    $packages = array();
    $maps = array();
    $masks = array();
    $lists = array();
    $mask_instances = array();

    // initialise maps so we don't generate missing methods if there are no results
    foreach ($component_value['maps'] as $key => $method) {
      $maps[$key] = array();
    }
    foreach ($component_value['masks'] as $key => $method) {
      $masks[$key] = array();
    }
    foreach ($component_value['lists'] as $key => $method) {
      $lists[$key] = array();
    }
    foreach ($component_value['instances'] as $key => $method) {
      $mask_instances[$key] = array();
    }

    $count = 0;
    foreach ($selected_dirs as $dir) {
      if (file_exists($dir . "/" . $filename)) {
        $component = json_decode(file_get_contents($dir . "/" . $filename), true);
        if (!$component) {
          throw new Exception("Could not load JSON from '$dir/$filename'");
        }

        // try and find a composer.json for this component
        $path = $dir;
        $composer_json = false;
        for ($i = 1; $i < $json['composer_depth']; $i++) {
          if (file_exists($path . "/composer.json")) {
            $composer_json = json_decode(file_get_contents($path . "/composer.json"), true /* assoc */);
            break;
          }
          $path .= "/..";
        }

        foreach ($component as $component_key => $classname) {
          // we can assume class names are unique
          if (is_numeric($component_key)) {
            $component_key = $classname;
          }

          $keys[] = "\"$component_key\"";
          $instances[] = "      case \"$component_key\": return new $classname(\$config);";
          $all_instances[] = "\"$component_key\" => new $classname(\$config)";
          if ($composer_json) {
            $packages[] = "      case \"$component_key\": return \"" . $composer_json['name'] . "\";";
          }

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
              $maps[$key][] = "      case \"" . $object->$method() . "\": return \"$component_key\";";
            }

            foreach ($component_value["masks"] as $key => $method) {
              if ($object->$method()) {
                $masks[$key][] = "\"$component_key\"";
              }
            }

            foreach ($component_value["lists"] as $key => $method) {
              if ($temp = $object->$method()) {
                $lists[$key][] = "\"$component_key\" => " . (is_numeric($temp) ? "$temp" : "\"$temp\"");
              }
            }

            foreach ($component_value["instances"] as $key => $instanceof_name) {
              if (is_a($object, $instanceof_name)) {
                $mask_instances[$key][] = "\"$component_key\"";
              }
            }
          }

        }
        $count++;
      }
    }

    echo "Found $count '$full_name' components\n";
    $keys = implode(", ", $keys);
    $instances = implode("\n", $instances);
    $packages = implode("\n", $packages);

    $output_maps = array();
    foreach ($maps as $key => $values) {
      $method_name = $component_value["maps"][$key];
      $output_maps[] = "
  /**
   * A reverse map for objects based on the result of their {@code $method_name()}.
   * @return the string key based on the return value of their {@code $method_name()} method
   */
  static function $key(\$input) {
    switch (\$input) {
" . implode("\n", $values) . "
      default:
        throw new \\ComponentDiscovery\\DiscoveryException(\"Could not find any matching $key for '\$input'\");
    }
  }
";
    }

    $output_maps = implode($output_maps, "");

    $output_masks = array();
    foreach ($masks as $key => $values) {
      $method_name = $component_value["masks"][$key];
      $output_masks[] = "
  /**
   * @return an array of all classes that return `true` with their {@code $method_name()} method
   */
  static function $key() {
    return array(
      " . implode(",\n      ", $values) . "
    );
  }
";
    }

    $output_masks = implode($output_masks, "");

    $output_lists = array();
    foreach ($lists as $key => $values) {
      $method_name = $component_value["lists"][$key];
      $output_lists[] = "
  /**
   * @return an array of all {@code $method_name()} values across all components, associated with their unique key
   */
  static function $key() {
    return array(
      " . implode(",\n      ", $values) . "
    );
  }
";
    }

    $output_lists = implode($output_lists, "");

    $output_mask_instances = array();
    foreach ($mask_instances as $key => $values) {
      $method_name = $component_value["instances"][$key];
      $output_mask_instances[] = "
  /**
   * @return an array of all keys which classes are instances of $method_name
   */
  static function $key() {
    return array(
      " . implode(",\n      ", $values) . "
    );
  }
";
    }

    $output_mask_instances = implode($output_mask_instances, "");

    $fp = fopen($json['dest'] . "/$full_name.php", "w");
    if (!$fp) {
      throw new Exception("Could not open destination file '" . $json['dest'] . "/$key.php' for writing");
    }
    fwrite($fp, "<?php

/**
 * @generated by component-discovery DO NOT EDIT
 */

namespace $namespace;

/**
 * Runtime component $full_name discovery,
 * generated from '$filename' component definition files.
 *
 * Each component has a unique key (available through {@link #getKeys()})
 * and a runtime instance object (available through {@link #getInstance()}).
 *
 * @generated by component-discovery DO NOT EDIT
 */
class $full_name extends \\ComponentDiscovery\\Base {

  /**
   * @return an array of all unique $full_name string keys
   */
  static function getKeys() {
    return array($keys);
  }

  /**
   * @return true if this key is a valid key according to {@link #getKeys()}, false otherwise
   */
  static function hasKey(\$key) {
    return in_array(\$key, self::getKeys());
  }

  /**
   * @return the corresponding runtime instance object for the given string \$key
   */
  static function getInstance(\$key, \$config = false) {
    switch (\$key) {
$instances
      default:
        throw new \\ComponentDiscovery\\DiscoveryException(\"Could not find any $full_name with key '\$key'\");
    }
  }

  /**
   * @return the corresponsing composer package name that defined this object \$key,
   *     or {@code null} if none is defined or none could be found.
   */
  static function getDefiningPackage(\$key) {
    switch (\$key) {
$packages
      default: return null;
    }
  }

  /**
   * @return an array of all $full_name instances, each with their unique string \$key
   */
  static function getAllInstances(\$config = false) {
    return array(
      " . implode(",\n      ", $all_instances) . "
    );
  }
$output_maps$output_masks$output_lists$output_mask_instances
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
