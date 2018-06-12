<?php

namespace Gazelle\Exception;

class RouterException extends \RuntimeException {
	public function __construct(string $message = "The route you tried to access is not available.") {
		parent::__construct($message);
	}
}