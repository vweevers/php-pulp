<?php

namespace Weevers\Pulp;

use React\EventLoop\LoopInterface
  , React\Stream\Buffer
  , React\Stream\WritableStreamInterface
  , React\Stream\Stream as DuplexStream
  , InvalidArgumentException;

/**
 * Duplex buffer stream
 *
 * Behaves slightly different from React\Stream\Stream:
 * - starts reading when someone starts listening
 * - unless the stream was explicitly paused through pause()
 * - when piped into multiple streams, it waits for all to drain
 */
class Stream extends DuplexStream {
  use Shared\PipeTrait, Shared\DebugTrait;

  protected $started = false;

  // only true if stream is explicitly paused with `pause()`
  protected $paused = false;
  
  public function __construct($stream, LoopInterface $loop, $id = null) {
    $this->setDebugId($id);
    $this->stream = $stream;
    
    if (!is_resource($this->stream) || get_resource_type($this->stream) !== "stream") {
      throw new InvalidArgumentException('First parameter must be a valid stream resource');
    }

    stream_set_blocking($this->stream, 0);

    $this->loop = $loop;
    $this->buffer = new Buffer($this->stream, $this->loop);

    $this->buffer->on('error', function ($error) {
      $this->emit('error', array($error, $this));
      $this->close();
    });

    $this->buffer->on('drain', function () {
      $this->emit('drain', array($this));
    });
  }

  public function on($event, callable $cb) {
    if ($event==='data' && !$this->started) {
      $this->started = true;
      if (!$this->paused) $this->resume();
    }

    parent::on($event, $cb);
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

  public function pause() {
    $this->paused = true;
    parent::pause();
  }

  public function resume() {
    $this->paused = false;
    parent::resume();
  }
}
