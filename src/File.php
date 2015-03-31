<?php

namespace Weevers\Pulp;

use React\Stream\DuplexStreamInterface
  , React\Stream\WritableStreamInterface
  , React\Stream\ReadableStreamInterface
  , Weevers\Path\Path;

class File implements \JsonSerializable, \IteratorAggregate {
  private $history;
  private $base;
  private $contents;
  private $cwd;
  private $stat;

  public function __construct(array $attrs) {
    if (isset($attrs['history'])) $this->history = $attrs['history'];
    elseif (isset($attrs['path'])) $this->history = [ $attrs['path'] ];

    if (isset($attrs['contents'])) $this->__set('contents', $attrs['contents']);

    $this->cwd = isset($attrs['cwd']) ? $attrs['cwd'] : getcwd();
    $this->base = isset($attrs['base']) ? $attrs['base'] : $this->cwd;

    if (isset($attrs['stat'])) $this->stat = $attrs['stat'];
  }

  public function isDirectory() {
    return $this->contents === null 
      && isset($this->stat['mode']) 
      && ($this->stat['mode'] & 040000) // S_IFDIR, the directory bit
    ;
  }

  public function isNull() {
    return $this->contents === null;
  }

  public function isStream() {
    return $this->contents instanceof ReadableStreamInterface
      || $this->contents instanceof WritableStreamInterface;
  }

  public function isString() {
    return is_string($this->contents);
  }

  public function pipe($dest) {
    if ($dest instanceof File) {
      $dest = $dest->contents;
    }

    if (!$dest instanceof WritableStreamInterface) {
      throw new \RuntimeException('Destination is not a writable stream');
    }

    if (!$dest->isWritable()) {
      throw new \RuntimeException('Destination stream is not writable');
    }

    if ($this->isStream()) {
      if (!$this->contents->isReadable()) {
        throw new \RuntimeException('Source stream is not readable');
      }

      $this->contents->pipe($dest);
    } elseif ($this->isString()) {
      $dest->end($this->contents);
    }

    return $dest;
  }

  public function __get($k) {
    if ($k==='contents') return $this->contents;
    elseif ($k==='relative') {
      return Path::relative($this->base, $this->path);
    } elseif ($k==='path') {
      return $this->history[count($this->history)-1];
    } elseif (isset($this->$k)) {
      return $this->$k;
    } else {
      throw new \RuntimeException('No such property: '.$k);
    }
  }

  public function __set($k, $v) {
    if ($k==='contents') {
      if ($v!==null && !$v instanceof ReadableStreamInterface && !$v instanceof WritableStreamInterface) {
        $this->assertString($v, ', Readable/Writable StreamInterface or null');
      }
      $this->contents = $v;
    } elseif ($k==='path') {
      $this->assertString($v);
      $this->history[] = $v;
    } elseif ($k==='base') {
      $this->assertString($v);
      $this->base = $v;
    } elseif ($k==='relative') {
      throw new \RuntimeException('$file->relative is generated from the base and path properties. Do not modify it.');
    } else {
      throw new \RuntimeException('No such property: '.$k);
    }
  }

  public function __toString() {
    $path = $this->base && $this->path ? $this->relative : $this->path;
    return '<Pulp\File '.$path.'>';
  }

  // TODO: clone original resource stream?
  public function __clone() {
    if ($this->isStream()) {
      $r = fopen('php://temp/maxmemory:'.$this->contents->bufferSize, 'w+');
      $stream = new Stream($r, static::steal($this->contents, 'loop'));
      $this->contents = $this->contents->pipe($stream);
    }
  }

  // TODO: Cheating. Remove later.
  public function getBufferedData() {
    return $this->isStream() 
      ? static::steal($this->contents->getBuffer(), 'data') 
      : $this->contents;
  }

  // TODO: Cheating. Remove later.
  protected static function steal($obj, $property) {
    if (!$obj) return null;
    return \Closure::bind(function ($obj) use (&$property) {
      return isset($obj->$property) ? $obj->$property : null;
    }, null, $obj)->__invoke($obj);
  }

  public function jsonSerialize() {
    return [
      'history' => $this->history,
      'base' => $this->base,
      'contents' => $this->getBufferedData(),
      'cwd' => $this->cwd,
      'stat' => $this->stat
    ];
  }

  protected function assertString($v, $append = null) {
    if (!is_string($v)) {
      $debug = $v === null ? 'null' : json_encode($v, true);
      $append = $append ? ' '.$append : '';
      throw new \UnexpectedValueException("Expected string{$append}, got: $debug");
    }
  }

  public function getIterator() {
    return new \ArrayIterator(array_map(function($path){
      $file = clone $this;
      $file->history = [ $path ];
      return $file;
    }, $this->history ?: []));
  }
}
