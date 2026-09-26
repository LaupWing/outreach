<?php

namespace App\Support\Places;

use RuntimeException;

/**
 * Google said no: a bad key, billing off, quota gone. The message is meant for the run's error column.
 */
class PlacesException extends RuntimeException {}
