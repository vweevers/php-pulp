<?php

namespace Weevers\Pulp\Shared;

use React\EventLoop\LoopInterface
  , React\Stream\WritableStreamInterface;

trait DebugTrait {
  protected $debugId;

  protected function setDebugId($id) {
    $this->debugId = $id;
  }

  public function __toString() {
    static $nid = 0;

    $class = explode('\\', get_called_class());
    $class = array_slice($class, -2);
    $class = implode('\\', $class);

    if ($this->debugId===null) $this->debugId = chr(65 + $nid++);

    return "<$class {$this->debugId}>";
  }
}
