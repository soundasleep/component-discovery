<?php

namespace ComponentDiscovery\Test;

class MoreAnimalsTest extends AbstractComponentDiscoveryTest {

  function getRoot() {
    return __DIR__ . "/simple";
  }

  function getDiscoveryJson() {
    return $this->getRoot() . "/more_animals.json";
  }

  function getNamespace() {
    return "MoreAnimalsTest";
  }

  function testAnimalKeys() {
    $animals = $this->loadClass("Animals");
    $keys = $animals->getKeys();
    $this->assertEquals(array("Cat", "Dog"), $keys);
  }

  function testAnimalHasKeys() {
    $animals = $this->loadClass("Animals");
    $this->assertTrue($animals->hasKey("Dog"));
    $this->assertTrue($animals->hasKey("Cat"));
    $this->assertFalse($animals->hasKey("Eagle"));
  }

  function testColourKeys() {
    $animals = $this->loadClass("Colours");
    $keys = $animals->getKeys();
    $this->assertEquals(array(), $keys);
  }

}
