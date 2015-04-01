<?php

require_once(__DIR__ . "/../../vendor/autoload.php");

use ComponentDiscovery\Test\Animal;

class Cat implements Animal {
  function isBird() {
    return false;
  }

  function getPlural() {
    return "cats";
  }
}

class Dog implements Animal {
  function isBird() {
    return false;
  }

  function getPlural() {
    return "dogs";
  }
}

class Emu implements Animal {
  function isBird() {
    return true;
  }

  function getPlural() {
    return "emus";
  }
}

class Eagle implements Animal {
  function isBird() {
    return true;
  }

  function getPlural() {
    return "eagles";
  }
}

class Fish /* implements Animal */ {
  // is not an instance of Animal
}
