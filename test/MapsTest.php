<?php

namespace ComponentDiscovery\Test;

require(__DIR__ . "/simple/vendor/autoload.php");

use \ComponentDiscovery\DiscoveryException;

class MapsTest extends AbstractComponentDiscoveryTest {

  function getRoot() {
    return __DIR__ . "/simple";
  }

  function getDiscoveryJson() {
    return $this->getRoot() . "/maps.json";
  }

  function getNamespace() {
    return "MapsTest";
  }

  function testAnimalKeys() {
    $animals = $this->loadClass("Animals");
    $keys = $animals->getKeys();
    $this->assertEquals(array("Cat", "Dog", "Eagle"), $keys);
  }

  function testGetInstance() {
    $animals = $this->loadClass("Animals");
    $cat = $animals->getInstance("Cat");
    $this->assertEquals("Cat", get_class($cat));
  }

  function testGetBirds() {
    $animals = $this->loadClass("Animals");
    $keys = $animals->getBirds();
    $this->assertEquals(array("Eagle"), $keys);
  }

  function testGetPlurals() {
    $animals = $this->loadClass("Animals");
    $this->assertEquals("Cat", $animals->getPlural("cats"));
  }

  function testGetPluralsException() {
    $animals = $this->loadClass("Animals");
    try {
      $animals->getPlural("invalid");
      $this->fail("Expected exception");
    } catch (DiscoveryException $e) {
      // expected
    }
  }

  function testColourKeys() {
    $animals = $this->loadClass("Colours");
    $keys = $animals->getKeys();
    $this->assertEquals(array(), $keys);
  }

}
