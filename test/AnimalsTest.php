<?php

namespace ComponentDiscovery\Test;

class AnimalsTest extends AbstractComponentDiscoveryTest {

  function getRoot() {
    return __DIR__ . "/simple";
  }

  function getDiscoveryJson() {
    return $this->getRoot() . "/animals.json";
  }

  function getNamespace() {
    return "AnimalsTest";
  }

  function testAnimalKeys() {
    $animals = $this->loadClass("Animals");
    $keys = $animals->getKeys();
    $this->assertEquals(array("Dog"), $keys);
  }

  function testAnimalHasKeys() {
    $animals = $this->loadClass("Animals");
    $this->assertTrue($animals->hasKey("Dog"));
    $this->assertFalse($animals->hasKey("Cat"));
    $this->assertFalse($animals->hasKey("dog"));
  }

}
