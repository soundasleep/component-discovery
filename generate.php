<?php

/**
 * Generates the necessary includes from the given file root.
 */

if (count($argv) < 2) {
  throw new Exception("Needs file root parameter");
}

$root = $argv[1];
if (!file_exists($root . "/discovery.json")) {
  throw new Exception("No discovery.json found in '$root'");
}

$json = json_decode(file_get_contents($root . "/discovery.json"), true /* assoc */);
echo "Loaded " . count($json['components']) . " component discovery patterns\n";

function get_directories_to_search($dirs, $pattern) {
  // convert pattern into a regular expression
  $pattern = str_replace("*", "[^/]+", $pattern);
  $pattern = "#" . $pattern . "$#";
  
  // find all matching directories
  $result = array();
  foreach ($dirs as $dir) {
    if (preg_match($pattern, $dir)) {
      $result[] = $dir;
    }
  }

  return $result;
}

function get_all_directories($root, $max_depth = 3) {
  $result = array();
  if ($handle = opendir($root)) {
    while (false !== ($entry = readdir($handle))) {
      if ($entry != "." && $entry != ".." && is_dir($root . "/" . $entry)) {
          $result[] = $root . "/" . $entry;
          if ($max_depth > 1) {
            $result = array_merge($result, get_all_directories($root . "/" . $entry, $max_depth - 1));
          }
        }
    }
    closedir($handle);
  }
  return $result;
}

// now load all of the components
$all_dirs = get_all_directories($root);
echo "Found " . count($all_dirs) . " potential subdirectories\n";
$selected_dirs = get_directories_to_search($all_dirs, $json['src']);
echo "Filtered to " . count($selected_dirs) . " matching paths\n";

$component_discoverers = array();

if ($selected_dirs) {
  foreach ($json['components'] as $key => $filename) {
    echo "Processing '$key' components...\n";
    $full_name = ucfirst($key);
    $component_discoverers[] = "      \"$key\" => new DiscoveredComponents\\$full_name()";

    $keys = array();
    $instances = array();
    $all_instances = array();

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
          $all_instances[] = "new $classname(\$config)";
        }
        $count++;
      }
    }

    echo "Found $count '$key' components\n";
    $keys = implode(", ", $keys);
    $instances = implode("\n", $instances);
    $all_instances = implode(", ", $all_instances);

    $fp = fopen($json['dest'] . "/$full_name.php", "w");
    if (!$fp) {
      throw new Exception("Could not open destination file '" . $json['dest'] . "/$key.php' for writing");
    }
    fwrite($fp, "<?php

/**
 * @generated by component-discovery DO NOT EDIT
 */

namespace DiscoveredComponents;

class $full_name extends \\ComponentDiscovery\\Base {
  function getKeys() {
    return [$keys];
  }

  function getInstance(\$key, \$config = false) {
    switch (\$key) {
$instances
      default:
        throw new \\ComponentDiscovery\\DiscoveryException(\"Could not find any $full_name with key '\\\$key'\");
    }
  }

  function getAllInstances(\$config = false) {
    return [$all_instances];
  }
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

namespace DiscoveredComponents;

class All {
  function list() {
    return array(
$component_discoverers
    );
  }
}
");

echo "Complete.";

// TODO generate a development version that does not require a rebuild?