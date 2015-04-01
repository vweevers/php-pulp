# pulp

pulp is a streaming build system, *something like* [gulp](http://gulpjs.com/) for PHP. Meaning: with `src("*.css")` you get a asynchronous duplex stream that emits virtual file objects. This stream can then be piped to one or more plugins or to a filesystem destination with `dest("target-dir")`. **pulp is a weekend experiment, please use a VM if you want to play with it.** Requires PHP 5.4+.

[![packagist status](https://img.shields.io/packagist/v/weevers/pulp.svg?style=flat-square)](https://packagist.org/packages/weevers/pulp) [![Travis build status](https://img.shields.io/travis/vweevers/php-pulp.svg?style=flat-square&label=travis)](http://travis-ci.org/vweevers/php-pulp) [![AppVeyor build status](https://img.shields.io/appveyor/ci/vweevers/php-pulp.svg?style=flat-square&label=appveyor)](https://ci.appveyor.com/project/vweevers/php-pulp) [![Dependency status](https://www.versioneye.com/user/projects/551ac01e3661f134fe0001d5/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/551ac01e3661f134fe0001d5)

Jump to: [install](#install) / [license](#license)

## example

This example bundles all the javascript files in "assets" and its subdirectories, then writes it to "build/all.js".

```php
<?php

use Weevers\Pulp\Pulp
  , Weevers\Pulp\Plugin;

$pulp = new Pulp();

$pulp
  ->src('assets/**/*.js')
  ->pipe(new Plugin\Concat('all.js'))
  ->pipe($pulp->dest('build'))
  ->each(function($file){
    echo "bundled all js in {$file->path}\n";
  })
;

// Nothing happens until we start an event loop
$pulp->run();

?>
```

If we leave out the Concat plugin, all javascript files are copied. Note that a file like "assets/js/app.js" would be copied to "build/js/app.js".

```php
<?php

$pulp
  ->src('assets/**/*.js')
  ->pipe($pulp->dest('build'));

$pulp->run();

?>
```

## install

With [composer](https://getcomposer.org/) do:

```
composer require weevers/pulp
```

## license

[MIT](http://opensource.org/licenses/MIT) © [Vincent Weevers](http://vincentweevers.nl)
