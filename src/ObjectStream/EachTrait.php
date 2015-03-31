<?php

namespace Weevers\Pulp\ObjectStream;

trait EachTrait {
  public function each(\Closure $each, array $options = []) {
    $fn = new \ReflectionFunction($each);

    if ($fn->getNumberOfParameters()<2) {
      return $this->syncEach($each, $options);
    }

    $stream = new Transform(function($file, $done) use($each){
      $each($file, function($err = null) use($file, $done) {
        $done($err, $file);
      });
    });

    return $this->pipe($stream, $options);
  }

  public function syncEach(\Closure $each, array $options = []) {
    $stream = new Transform(function($file, $done) use($each){
      $each($file);
      $done(null, $file);
    });

    return $this->pipe($stream, $options);
  }
}
