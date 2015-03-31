<?php

namespace Weevers\Pulp\ObjectStream;

use React\EventLoop\LoopInterface;

class Transform extends Duplex {
  protected $transform;

  public function __construct(callable $transform, LoopInterface $loop = null, $id = null) {
    parent::__construct($loop, $id);
    $this->transform = $transform;
  }

  public function _read() {
    if (count($this->buffer)===0 || $this->reading 
      || $this->paused || !$this->readable) return;
    
    $this->reading = true;
    $item = array_shift($this->buffer);

    $called = 0;

    $done = function($err = null, $item = null) use(&$called) {
      if ($called++) return;

      if ($err!==null) $this->emit('error', [$err]);
      elseif ($item!==null) $this->emit('data', [$item]);
      
      $this->reading = false;

      if (count($this->buffer)===0) $this->emit('drain');
      else $this->_read();
    };

    if ($this->transform instanceof \Closure) {
      // TODO: test if this is faster than `call_user_func()`
      $this->transform->__invoke($item, $done);
    } else {
      call_user_func($this->transform, $item, $done);
    }
  }
}
