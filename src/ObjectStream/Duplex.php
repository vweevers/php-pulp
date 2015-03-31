<?php

namespace Weevers\Pulp\ObjectStream;

use Weevers\Pulp\ChainableEmitter
  , React\Stream\DuplexStreamInterface
  , React\Stream\WritableStreamInterface
  , React\Stream\ReadableStreamInterface
  , React\Stream\Util
  , React\EventLoop\LoopInterface
  , Weevers\Pulp\Shared;

class Duplex extends ChainableEmitter implements DuplexStreamInterface, Shared\InheritsLoopInterface {
  use Shared\PipeTrait
    , Shared\DebugTrait
    , MapTrait
    , EachTrait;

  protected $buffer = [];
  protected $softLimit = 10;
  protected $hardLimit = 30;
  protected $loop;

  protected $readable = true;
  protected $writable = true;

  protected $paused = false;
  protected $reading = false;
  protected $closed = false;

  public function __construct(LoopInterface $loop = null, $id = null) {
    $this->setDebugId($id);
    if ($loop!==null) $this->setLoop($loop);
  }

  public function setLoop(LoopInterface $loop) {
    if ($this->loop!==null) return;

    $this->loop = $loop;
    if (!$this->paused) $this->loop->nextTick(function(){
      if (!$this->paused) $this->resume('autostart');
    });
  }

  // ReadableStreamInterface 
  public function isReadable() {
    return $this->readable;
  }

  public function pause() {
    $this->paused = true;
  }

  public function resume() {
    if ($this->closed || !$this->readable) return;
    $this->paused = false;
    $this->_read();
  }

  public function _read() {
    if ($this->reading || $this->paused || !$this->readable) return;
    
    $this->reading = true;

    while (!$this->paused && count($this->buffer) > 0) {
      $item = array_shift($this->buffer);
      $this->emit('data', [$item]);

      if (count($this->buffer)===0) {
        $this->emit('drain');
      }
    }

    $this->reading = false;
  }
  
  // WritableStreamInterface
  public function isWritable() {
    return $this->writable;
  }

  public function write($data) {
    if ($this->writable) {
      if (count($this->buffer) > $this->hardLimit) {
        throw new \OverflowException(
          'Hit hard limit ({$this->hardLimit}). '.
          'You should honour the soft limit ({$this->softLimit}), by waiting for `drain` '.
          'when `write()` returns false.' );
      }

      if ($data) {
        $this->buffer[] = $data;
        if (!$this->reading) $this->loop->nextTick([$this, '_read']);
      }

      return count($this->buffer) < $this->softLimit;
    } else {
      throw new \RuntimeException('Not writable: '.$this);
    }
  }

  public function end($data = null) {
    if (!$this->writable) return;

    if ($data!==null) $this->write($data);

    $this->writable = false;
    
    if (count($this->buffer)>0) {
      $this->once('drain', function(){
        $this->emit('end', [$this]);
        $this->close();
      });
    } else {
      $this->emit('end', [$this]);
      $this->close();
    }
  }

  public function close() {
    if ($this->closed) return;
    $this->closed = true;
    $this->writable = false;
    $this->readable = false;
    $this->buffer = null;
    $this->emit('close', [$this]);
    $this->removeAllListeners();
  }
}
