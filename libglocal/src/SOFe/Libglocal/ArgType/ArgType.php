<?php

/*
 * libglocal
 *
 * Copyright (C) 2018 SOFe
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace SOFe\Libglocal\ArgType;

use JsonSerializable;
use SOFe\Libglocal\MessageArg;
use SOFe\Libglocal\MultibyteLineReader;
use function get_class;

abstract class ArgType implements JsonSerializable{
	/** @var MessageArg */
	protected $arg;

	public function setArg(MessageArg $arg) : void{
		$this->arg = $arg;
	}

	public function init() : void{

	}

	public abstract function toString($value) : string;

	public abstract function getName() : string;

	public function parseConstraint(MultibyteLineReader $reader) : bool{
		return false;
	}

	public function jsonSerialize() : array{
		return [
			"class" => get_class($this),
		];
	}
}
