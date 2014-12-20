<?php

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

function make_target_directories($dirs) {
  foreach ($dirs as $path) {
    // remove any filenames
    if (substr($path, -1) !== "/") {
      $path = substr($path, 0, strrpos($path, "/"));
    }
    if (!file_exists($path)) {
      echo "Making directory '$path' recursively...\n";
      mkdir($path, 0777, true);
    }
  }
}
