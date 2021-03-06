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

namespace SOFe\Libglocal\Parser\Ast\Message;

use SOFe\Libglocal\Parser\Ast\AstNode;
use SOFe\Libglocal\Parser\Token;

class MessageGroupBlock extends AstNode implements MessageParentBlock{
	/** @var Token[] */
	protected $flags;
	/** @var string */
	protected $id;

	/** @var MessageGroupBlock[] */
	protected $groups = [];
	/** @var MessageBlock[] */
	protected $messages = [];


	protected function accept() : bool{
		while(($token = $this->acceptToken(Token::FLAG)) !== null){
			$this->flags[] = $token;
		}
		if(($token = $this->acceptToken(Token::IDENTIFIER)) === null){
			return false;
		}
		$this->id = $token->getCode();
		$this->acceptToken(Token::EQUALS);

		return $this->acceptToken(Token::INDENT_INCREASE) !== null;
	}

	protected function complete() : void{
		while($this->acceptToken(Token::INDENT_DECREASE) === null){
			$child = $this->expectAnyChildren(MessageGroupBlock::class, MessageBlock::class);
			if($child instanceof MessageGroupBlock){
				$this->groups[] = $child;
			}elseif($child instanceof MessageBlock){
				$this->messages[] = $child;
			}
		}
	}

	protected static function getNodeName() : string{
		return "message group";
	}

	public function toJsonArray() : array{
		return [
			"flags" => $this->flags,
			"id" => $this->id,
			"groups" => $this->groups,
			"messages" => $this->messages,
		];
	}


	/**
	 * @return Token[]
	 */
	public function getFlags() : array{
		return $this->flags;
	}

	public function getId() : string{
		return $this->id;
	}

	/**
	 * @return MessageGroupBlock[]
	 */
	public function getGroups() : array{
		return $this->groups;
	}

	/**
	 * @return MessageBlock[]
	 */
	public function getMessages() : array{
		return $this->messages;
	}
}
