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

namespace SOFe\Libglocal\Argument\Type\String;

use pocketmine\command\CommandSender;
use SOFe\Libglocal\Argument\Attribute\ArgumentAttribute;
use SOFe\Libglocal\Argument\Type\ArgumentType;
use SOFe\Libglocal\FormattedString;
use SOFe\Libglocal\Parser\Ast\Constraint\ConstraintBlock;
use SOFe\Libglocal\Parser\Ast\Constraint\LiteralConstraintBlock;
use SOFe\Libglocal\Parser\Ast\Literal\LiteralElement;

class StringArgumentType implements ArgumentType{
	/** @var StringConstraint[] */
	protected $constraints = [];
	/** @var LiteralElement|null */
	protected $default = null;

	public function getType() : string{
		return "string";
	}

	public function setDefault(LiteralElement $default) : void{
		$this->default = $default;
	}

	public function applyConstraint(ConstraintBlock $constraint) : void{
		if($constraint instanceof LiteralConstraintBlock){
			switch($constraint->getDirective()){
				case "enum":
					$this->constraints[] = new ExactStringConstraint($constraint->getValue()->requireStatic(), false);
					return;
				case "ienum":
					$this->constraints[] = new ExactStringConstraint($constraint->getValue()->requireStatic(), true);
					return;
				case "pattern":
					$this->constraints[] = new PatternStringConstraint($constraint->getValue()->requireStatic());
					return;
			}
		}

		$constraint->throwInit("Incompatible constraint $constraint applied on argument/field of string type");
	}

	/**
	 * @param mixed               $value
	 * @param CommandSender       $context
	 * @param ArgumentAttribute[] $attributes
	 *
	 * @return FormattedString
	 */
	public function toString($value, CommandSender $context, array $attributes) : FormattedString{

	}
}
