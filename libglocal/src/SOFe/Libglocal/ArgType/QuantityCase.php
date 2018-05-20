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

use function sprintf;

class QuantityCase{
	/** @var NumberConstraint[] */
	protected $constraints;
	/** @var string */
	protected $value;

	public function __construct(array $constraints, string $value){
		$this->constraints = $constraints;
		$this->value = $value;
	}

	public function resolve(float $number) : ?string{
		foreach($this->constraints as $constraint){
			if(!$constraint->matches($number)){
				return null;
			}
		}
		return sprintf($this->value, $number);
	}
}