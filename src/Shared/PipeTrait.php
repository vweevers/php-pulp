<?php

namespace Weevers\Pulp\Shared;

use React\EventLoop\LoopInterface
  , React\Stream\WritableStreamInterface;

trait PipeTrait {
  protected $awaitingDrain = 0;

  protected function increaseWaitingDrain() {
    $this->awaitingDrain++;
  }

  protected function decreaseWaitingDrain() {
    if (--$this->awaitingDrain===0) {
      $this->resume();
    }
  }

  public function pipe(WritableStreamInterface $dest, array $options = array()) {   
    $dest->emit('pipe', array($this));

    if ($dest instanceof InheritsLoopInterface) $dest->setLoop($this->loop);

    $this->on('data', function ($data) use ($dest) {
      $feedMore = $dest->write($data);
      if (false === $feedMore) {
        $this->pause();
        $this->increaseWaitingDrain();
        $dest->once('drain', [$this, 'decreaseWaitingDrain']);
      }
    });

    $end = isset($options['end']) ? $options['end'] : true;
    
    if ($end && $this !== $dest) {
      $this->once('end', function () use ($dest) {
        $dest->end();
      });
    }

    return $dest;
  }
}
