<?php

namespace Weevers\Pulp;

use Evenement\EventEmitter;

class ChainableEmitter extends EventEmitter {
  public function on($event, callable $listener) {
    parent::on($event, $listener);
    return $this;
  }

  public function once($event, callable $listener) {
    parent::once($event, $listener);
    return $this;
  }

  public function removeListener($event, callable $listener) {
    parent::removeListener($event, $listener);
    return $this;
  }

  public function removeAllListeners($event = null) {
    parent::removeAllListeners($event);
    return $this;
  }

  public function emit($event, array $arguments = []) {
    parent::emit($event, $arguments);
    return $this;
  }
}
