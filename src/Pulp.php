<?php

namespace Weevers\Pulp;

use Weevers\Pulp\ObjectStream
  , React\EventLoop\LoopInterface
  , React\EventLoop\Factory
  , Weevers\Path\Path
  , MD\Foundation\Utils\FilesystemUtils;

class Pulp {
  protected $loop;

  public function __construct(LoopInterface $loop = null) {
    if ($loop === null) $loop = Factory::create();
    $this->loop = $loop;
  }

  public function getLoop() {
    return $this->loop;
  }

  public function nextTick($fn) {
    $this->loop->nextTick($fn);
  }

  public function run() {
    $this->loop->run();
  }

  // TODO:
  // - sorted and unique stream
  // - negation
  public function src() {
    $stream = new ObjectStream\Duplex($this->loop);
    
    $patterns = func_get_args();
    if (count($patterns)===1 && is_array($patterns[0])) $patterns = $patterns[0];

    $next = function() use(&$next, $stream, &$patterns) {
      if (count($patterns)===0) return $stream->end();

      $pattern = array_shift($patterns);
      $this->assertPattern($pattern);
      $isGlob = self::isGlob($pattern);

      // hack for a directory glob
      if (!$isGlob && is_dir($pattern)) {
        $isGlob = true;
        $pattern = rtrim($pattern, '/\\') . '/*.*'; 
      }

      if ($isGlob) {
        $base = Path::resolve(self::globParent($pattern));

        // TODO: do an asynchronous glob? or use a recursive iterator
        $results = FilesystemUtils::glob($pattern, GLOB_BRACE);
        
        $nextFile = function() use(&$nextFile, &$results, &$next, $stream, $base) {
          if (count($results)===0) return $next();
          $result = array_shift($results);

          if ($stream->write( $this->createFile($result, $base) )===false) {
            $stream->on('drain', $nextFile);
          } else {
            $nextFile();
          }
        };

        $nextFile();
      } else {
        if ($stream->write( $this->createFile($pattern) )===false) {
          $stream->on('drain', $next);
        } else {
          $next();
        }
      }
    };

    $this->nextTick($next);
    return $stream;
  }

  protected static function isGlob($pattern) {
    // from jonschlinkert/is-glob (TODO: credit)
    return (bool) preg_match('/[!*{}?(|)[\]]/', $pattern);
  }

  protected static function globParent($p) {
    while (self::isGlob($p)) $p = dirname($p);
    return $p;
  }

  public function dest($dest) {
    $dest = ltrim( rtrim($dest, '/\\ '), ' ');
    if (!$dest) throw new \InvalidArgumentException('Empty path');

    $base = Path::resolve($dest);

    $map = function($file, $done) use ($base) {
      $path = Path::resolve($base, $file->relative);
      $scheme = Path::getScheme($path);

      if ($scheme==='php') {
        throw new \RuntimeException(
          'The php:// stream wrapper is not suited for multiple files; use a ConcatStream.');
      }

      // let's be safe
      if ($scheme==='file' && !Path::isInside($path, $cwd = getcwd())) {
        throw new \RuntimeException(sprintf(
          'Destination "%s" lies outside of current working directory "%s"', $path, $cwd));
      }
      
      $file->path = $path;
      $file->base = $base;

      // echo "will write {$file->path} ($file)\n";
      // return $done();
     
      $fp = fopen($file->path, 'w+');
      if (!$fp) throw new \RuntimeException("Could not open {$file->path}");

      $filedest = new Stream($fp, $this->loop);

      $file->pipe($filedest)->on('close', function() use($done, $file) {
        $file->contents = new Stream(fopen($file->path, 'r'), $this->loop);
        $done(null, $file);
      })->on('error', function($err) use($done) {
        $done($err);
      });
    };

    return new ObjectStream\Transform($map);
  }

  public function createFile($path, $base = null) {
    $fp = fopen($path, 'r+');
    
    if (!$fp) {
      throw new \RuntimeException('Could not open '.$path);
    }

    return new File([
      'path' => Path::resolve($path),
      'base' => $base === null ? null : Path::resolve($base),
      'contents' => new Stream($fp, $this->loop, $path),
      'stat' => fstat($fp)
    ]);
  }

  protected function assertPattern($ptn) {
    if (!is_string($ptn)) {
      throw new \InvalidArgumentException('Path or glob pattern must be a string');
    }
  }
}
