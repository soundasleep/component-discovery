<?php

namespace ComponentDiscovery\Test;

class EmptyTest extends AbstractComponentDiscoveryTest {

  function getRoot() {
    return __DIR__ . "/simple";
  }

  function getDiscoveryJson() {
    return $this->getRoot() . "/empty.json";
  }

  function getNamespace() {
    return "EmptyTest";
  }

}
