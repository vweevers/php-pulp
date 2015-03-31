<?php

namespace Weevers\Pulp\Shared;

use React\EventLoop\LoopInterface;

interface InheritsLoopInterface {
  public function setLoop(LoopInterface $loop);
}
