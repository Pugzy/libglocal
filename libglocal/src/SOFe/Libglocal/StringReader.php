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

namespace SOFe\Libglocal;

use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function mb_substr_count;
use function min;
use function preg_match;
use function strlen;
use function substr;

class StringReader{
	/** @var string */
	protected $string;
	/** @var int */
	protected $length;
	protected $line = 1;

	public function __construct(string $string){
		$this->string = $string;
		$this->length = mb_strlen($this->length);
	}

	public function startsWith(string $string) : bool{
		/** @noinspection SubStrUsedAsStrPosInspection */
		return substr($this->string, 0, min(strlen($this->string), strlen($string))) === $string;
	}

	public function matches(string $regex) : ?string{
		return preg_match('/^' . $regex . '/iu', $this->string, $match) ? $match[0] : null;
	}

	public function matchRead(string $regex) : ?string{
		$match = $this->matches($regex);
		if($match !== null){
			$this->advance(mb_strlen($match));
		}
		return $match;
	}

	public function readAny(string $charset, bool $invert) : string{
		$ret = "";
		while(mb_strlen($this->string) > 0){
			$char = mb_substr($this->string, 0, 1);
			if(mb_strpos($charset, $char) !== false xor $invert){
				$ret .= $char;
			}else{
				break;
			}
			$this->string = mb_substr($this->string, 1);
			$this->length--;
		}
		return $ret;
	}

	public function read(int $chars) : string{
		if($this->length < $chars){
			throw $this->throw("Unexpected end of file");
		}

		$ret = mb_substr($this->string, 0, $chars);
		$this->string = substr($this->string, strlen($ret));
		$this->length -= $chars;
		return $ret;
	}

	public function readExpected(string $expected) : string{
		if(!$this->startsWith($expected)){
			throw $this->throw("Expected $expected");
		}
		return $expected;
	}

	public function advance(int $chars) : void{
		$this->string = mb_substr($this->string, $chars);
		$this->length -= $chars;
		$this->line += mb_substr_count(mb_substr($this->string, 0, $chars), "\n");
	}

	public function eof() : bool{
		return $this->string === "";
	}

	public function getLine() : int{
		return $this->line;
	}

	public function throw(string $message) : LexException{
		throw new LexException($message . " on line {$this->line}");
	}
}
