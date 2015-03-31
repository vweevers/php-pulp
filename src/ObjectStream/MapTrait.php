<?php

namespace Weevers\Pulp\ObjectStream;

trait MapTrait {
  public function map(\Closure $map, array $options = []) {
    $fn = new \ReflectionFunction($map);

    if ($fn->getNumberOfParameters()<2) {
      return $this->syncMap($map, $options);
    }

    return $this->pipe(new Transform($map), $options);
  }

  public function syncMap(\Closure $map, array $options = []) {
    $stream = new Transform(function($file, $done) use($map) {
      $done(null, $map($file));
    });

    return $this->pipe($stream, $options);
  }
}
