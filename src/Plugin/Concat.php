<?php

namespace Weevers\Pulp\Plugin;

use Weevers\Pulp\ObjectStream\Transform
  , Weevers\Pulp\ObjectStream\Duplex
  , Weevers\Pulp\File;

class Concat extends Transform {
  protected $concat = '';

  public function __construct($path, LoopInterface $loop = null) {
    parent::__construct([$this, 'map'], $loop);

    $this->on('end', function() use($path) {
      $file = new File([
        'path' => $path,
        'contents' => $this->concat
      ]);

      $this->emit('data', [$file]);
    });
  }

  public function map($file, \Closure $done) {
    if ($file->isStream()) {
      $file->pipe(new Duplex($this->loop))->on('data', function($data){
        $this->concat.= $data;
      })->on('close', function() use($done, $file){
        $done();
      })->on('error', $done)->resume();
    } elseif ($file->isString()) {
      $this->concat.= $file->contents;
      $done();
    } else {
      $done();
    }
  }
}
