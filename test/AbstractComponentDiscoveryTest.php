<?php

namespace ComponentDiscovery\Test;

abstract class AbstractComponentDiscoveryTest extends \PHPUnit_Framework_TestCase {

  abstract function getRoot();

  function getNamespace() {
    return "DiscoveredComponents";
  }

  function getDiscoveryJson() {
    return $this->getRoot() . "/discovery.json";
  }

  function setUp() {
    $this->execute("rm -rf " . escapeshellarg($this->getGeneratedDir()));
    $this->doGenerate($this->getRoot(), $this->getDiscoveryJson());
  }

  function getGeneratedDir() {
    return $this->getRoot() . "/generated";
  }

  function tearDown() {
    // empty
  }

  function execute($command) {
    if ($this->isDebug()) {
      echo ">>> $command\n";
      $last = system($command, $return);
    } else {
      $last = exec($command, $ignored, $return);
    }
    $this->assertEquals(0, $return, "Command '$command' returned $return: '$last'");
  }

  /**
   * Execute the find.php script
   * In the future, this should be done by instantiating a class rather than running shell commands
   * @param $json optional JSON input file for the find script
   */
  function doGenerate($dir, $json = false) {
    $command = "cd " . escapeshellarg($dir) . " && php -f " . escapeshellarg(__DIR__ . "/../generate.php") . " .";
    if ($json) {
      $command .= " " . escapeshellarg($json);
    }
    $this->execute($command);
  }

  function isDebug() {
    global $argv;
    if (isset($argv)) {
      foreach ($argv as $value) {
        if ($value === "--debug" || $value === "--verbose") {
          return true;
        }
      }
    }
    return false;
  }

  function testGeneratedDirIsCreated() {
    $dir = $this->getGeneratedDir();
    $this->assertTrue(is_dir($dir), "'$dir' should be a directory");
    $this->assertTrue(file_exists($dir), "'$dir' should exist");
  }

  function loadClass($name) {
    $class = $this->getNamespace() . "\\" . $name;
    if (!class_exists($class)) {
      require($this->getGeneratedDir() . "/" . $name . ".php");
    }
    return new $class;
  }

  function testAllHasGetAllComponentTypes() {
    $this->assertMethodExists($this->loadClass("All"), 'getAllComponentTypes');
  }

  function assertMethodExists($object, $method) {
    $this->assertTrue(method_exists($object, $method), "Method '$method' should exist on '" . get_class($object) . "'");
  }

}
