<?php

namespace Wedeto\Util\Cache;

use Psr\Cache\CacheException;
use RuntimeException;

class Exception extends RuntimeException implements CacheException
{}
