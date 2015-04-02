<?php

namespace Weevers\Pulp;

use Weevers\Path\Path
  , org\bovigo\vfs\vfsStream
  , org\bovigo\vfs\vfsStreamDirectory;

class NoopTest extends \PHPUnit_Framework_TestCase {
  public function testSrc() {
    $pulp = new Pulp();

    $fs = vfsStream::setup('root', null, [
      'src' => [
        'a1.md' => 'a1',
        'a2.md' => 'a2',
        'b' => [
          'b1.md' => 'b1',
          'b2.md' => 'b2'
        ]
      ]
    ]);

    $paths = [];
    $expectedBase = Path::resolve('vfs://root/src');
    
    $pulp->src('vfs://root/src/**/*.md')
      ->each(function($file) use(&$paths, $expectedBase) {
        $paths[] = $file->relative;
        $this->assertEquals($expectedBase, $file->base);
      });

    $pulp->run();

    $paths = $this->posixStyle($paths);
    $this->assertEquals(['a1.md', 'a2.md', 'b/b1.md', 'b/b2.md'], $paths);
  }

  protected function posixStyle($path) {
    if (is_array($path)) return array_map([$this, 'posixStyle'], $path);
    return str_replace('\\', '/', $path);
  }
}
