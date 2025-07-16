<?php

declare(strict_types=1);

namespace Drupal\ab_tests\Exceptions;

/**
 * Custom exception for when the context is not valid.
 */
class InvalidContextException extends \InvalidArgumentException {}
