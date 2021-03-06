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

namespace SOFe\Libglocal\Parser\Lexer;

use Generator;
use SOFe\Libglocal\Parser\Token;
use function array_pop;
use function assert;
use function count;
use function json_encode;
use function str_replace;
use function strpos;

class LibglocalLexerGenerator{
	private const IDENTIFIER_REGEX = '[A-Za-z0-9_\\.\\-]+';

	private $indentStack = [];

	public function lex(StringReader $reader) : Generator{
		while(!$reader->eof()){
			yield from $this->lexLine($reader);
			yield new Token(Token::CHECKSUM, "");
		}
		foreach($this->indentStack as $_ => $_){
			yield new Token(Token::INDENT_DECREASE, "");
		}
	}

	private function lexLine(StringReader $reader) : Generator{
		$ret = yield from $this->lineStart($reader);
		if(!$ret){
			return;
		}

		yield from $this->lineBody($reader);

		foreach(["\r\n", "\n"] as $newline){
			if($reader->startsWith($newline)){
				yield new Token(Token::WHITESPACE, $reader->readExpected($newline));
			}
		}
	}

	private function lineStart(StringReader $reader) : Generator{
		$lf = $reader->readAny("\r\n", false);
		if(!empty($lf)){
			yield new Token(Token::WHITESPACE, $lf);
		}

		$white = $reader->readAny(" \t", false);

		if($reader->startsWith("//")){
			if(!empty($white)){
				yield new Token(Token::WHITESPACE, $white);
			}
			$comment = $reader->readAny("\r\n", true) . $reader->readAny("\r\n", false);
			yield new Token(Token::COMMENT, $comment);
			return false;
		}

		$lf = $reader->readAny("\r\n", false);
		if(!empty($lf)){
			yield new Token(Token::WHITESPACE, $white . $lf);
			return false;
		}

		if(empty($white)){
			/** @noinspection PhpUnusedLocalVariableInspection */
			foreach($this->indentStack as $_){
				yield new Token(Token::INDENT_DECREASE, "");
			}
			$this->indentStack = [];

			return true;
		}

		if(empty($this->indentStack)){
			$this->indentStack[] = $white;
			yield new Token(Token::INDENT_INCREASE, $white);
			return true;
		}

		$last = $this->indentStack[count($this->indentStack) - 1];
		if(strpos($white, $last) === 0){
			if($white !== $last){ // $white starts with $last
				$this->indentStack[] = $white;
				yield new Token(Token::INDENT_INCREASE, "");
			}
			yield new Token(Token::INDENT, $white);
			return true;
		}

		$ok = false;
		while(!empty($this->indentStack)){
			$last = $this->indentStack[count($this->indentStack) - 1];
			if($last === $white){
				$ok = true;
				break;
			}
			yield new Token(Token::INDENT_DECREASE, "");
			array_pop($this->indentStack);
		}
		if(!$ok){
			throw $reader->throw("Invalid indent \"" . str_replace("\t", "\\t", $white) . "\"");
		}
		yield new Token(Token::INDENT, $white);

		return true;
	}

	private function lineBody(StringReader $reader) : Generator{
		if($reader->startsWith("@")){
			yield from $this->readMathRule($reader);
			return;
		}

		$nextFirst = true;
		$isArgLine = false;
		while(true){
			$isFirst = $nextFirst;
			$nextFirst = false;
			if($reader->startsWith("\n") || $reader->startsWith("\r\n") || $reader->eof()){
				return;
			}

			if($reader->startsWith("=")){
				yield new Token(Token::EQUALS, $reader->readExpected("="));
				yield from $this->readWhitespace($reader);

				if($isArgLine){
					yield from $this->attributeValue($reader);
					return;
				}
				break;
			}

			if($isFirst){
				if($reader->startsWith("*")){
					yield new Token(Token::MOD_DOC, $reader->readExpected("*"));
					yield from $this->readWhitespace($reader);
					break;
				}

				if($reader->startsWith('~')){
					yield new Token(Token::MOD_VERSION, $reader->readExpected('~'));
					yield from $this->readWhitespace($reader);
					continue;
				}

				if($reader->startsWith('$')){
					yield new Token(Token::MOD_ARG, $reader->readExpected('$'));
					yield from $this->readWhitespace($reader);
					$isArgLine = true;
					continue;
				}
			}


			yield from $this->readIdentifier($reader, true, false);
			yield from $this->readWhitespace($reader);
		}

		yield from $this->literal($reader, false);
	}

	private function readMathRule(StringReader $reader) : Generator{
		while(!$reader->startsWith("\r\n") && !$reader->startsWith("\n")){
			yield from $this->readWhitespace($reader, " \t,", false, Token::MATH_SEPARATOR);
			static $tokenMap = [
				"@" => Token::MATH_AT,
				"%" => Token::MATH_MOD,
				"=" => Token::MATH_EQ,
				"<>" => Token::MATH_NE,
				"!=" => Token::MATH_NE,
				"<=" => Token::MATH_LE,
				"<" => Token::MATH_LT,
				">=" => Token::MATH_GE,
				">" => Token::MATH_GT,
			];
			foreach($tokenMap as $symbol => $type){
				if($reader->startsWith($symbol)){
					yield new Token($type, $reader->readExpected($symbol));
					continue;
				}
			}
			if(($number = $reader->matchRead('-?[0-9](\\.[0-9]+)?')) !== null){
				yield new Token(Token::NUMBER, $number);
				continue;
			}
			if(yield from $this->readIdentifier($reader, false)){
				continue;
			}
		}
	}

	private function literal(StringReader $reader, bool $closeable) : Generator{
		while(true){
			$literal = $reader->readAny("\r\n\\#\$%}", true);
			if(!empty($literal)){
				yield new Token(Token::LITERAL, $literal);
			}

			if($reader->eof()){
				return;
			}
			if($reader->startsWith("\r\n") || $reader->startsWith("\n")){
				if($reader->matchRead('[ \\t\\r\\n]+([!|\\\\])', $match) !== null){
					switch($match[1]){
						case '!':
							yield new Token(Token::CONT_NEWLINE, $match[0]);
							break;
						case '|':
							yield new Token(Token::CONT_SPACE, $match[0]);
							break;
						case '\\':
							yield new Token(Token::CONT_CONCAT, $match[0]);
							break;
						default:
							assert(false, "Unexpected match {$match[1]}");
					}
					continue;
				}
				return;
			}
			if($reader->startsWith("\\")){
				yield new Token(Token::ESCAPE, $reader->read(2));
				continue;
			}
			if($reader->startsWith("}")){
				if(!$closeable){
					throw $reader->throw("Unexpected }, must be escaped as \\}");
				}
				return;
			}

			if($reader->getRemainingString(){1} !== "{"){
				yield new Token(Token::LITERAL, $reader->read(1));
				continue;
			}

			if($reader->startsWith('#{')){
				yield new Token(Token::MESSAGE_REF_START, $reader->readExpected('#{'));
				yield from $this->ref($reader);
				continue;
			}

			if($reader->startsWith('${')){
				yield new Token(Token::ARG_REF_START, $reader->readExpected('${'));
				yield from $this->ref($reader);
				continue;
			}

			assert($reader->startsWith('%{'));
			yield from $this->span($reader);
		}
	}

	private function ref(StringReader $reader) : Generator{
		yield from $this->readWhitespace($reader, " \t\r\n");
		if($reader->startsWith('$')){
			yield new Token(Token::MOD_ARG, $reader->readExpected('$'));
			yield from $this->readWhitespace($reader, " \t\r\n");
		}
		yield from $this->readIdentifier($reader, true, false);

		yield from $this->attributeList($reader);

		yield new Token(Token::CLOSE_BRACE, $reader->readExpected("}"));
		yield new Token(Token::CHECKSUM, "");
	}

	private function attributeList(StringReader $reader) : Generator{
		while(!$reader->startsWith("}")){
			yield from $this->readWhitespace($reader, " \t,\r\n", true);
			if($reader->startsWith("}")){
				break;
			}
			if($isMath = $reader->startsWith("@")){
				yield new Token(Token::MATH_AT, $reader->readExpected("@"));
			}
			yield from $this->readIdentifier($reader, !$isMath, false); // key
			yield from $this->readWhitespace($reader, " \t\r\n");
			if($reader->startsWith("=")){
				yield new Token(Token::EQUALS, $reader->readExpected("=")); // =
				yield from $this->readWhitespace($reader, " \t\r\n");
			}
			yield from $this->attributeValue($reader); // value
		}
	}

	private function attributeValue(StringReader $reader) : Generator{
		if(($number = $reader->matchRead('-?[0-9]+(\\.[0-9]+)?')) !== null){
			yield new Token(Token::NUMBER, $number);
			return;
		}
		if($reader->startsWith("{")){
			yield new Token(Token::OPEN_BRACE, $reader->read(1));
			yield from $this->literal($reader, true);
			yield new Token(Token::CLOSE_BRACE, $reader->readExpected("}"));
			return;
		}
		if($reader->startsWith("#")){
			yield new Token(Token::ATTRIBUTE_SIMPLE_MESSAGE, $reader->readExpected("#"));
			yield from $this->readWhitespace($reader);
		}
		$hasIdentifier = yield from $this->readIdentifier($reader, false, false);
		if(!$hasIdentifier){
			throw $reader->throw("Expected identifier, number or {literal}");
		}
	}

	private function span(StringReader $reader) : Generator{
		yield new Token(Token::SPAN_START, $reader->readExpected("%{"));
		yield from $this->readWhitespace($reader);
		yield from $this->readIdentifier($reader, true, true, Token::SPAN_NAME);
		yield from $this->readWhitespace($reader);
		yield from $this->literal($reader, true);
		yield new Token(Token::CLOSE_BRACE, $reader->readExpected("}"));
		yield new Token(Token::CHECKSUM, "");
	}


	private function readWhitespace(StringReader $reader, string $charset = " \t", bool $must = false, int $tokenType = Token::WHITESPACE) : Generator{
		$white = $reader->readAny($charset, false);
		if(!empty($white)){
			yield new Token($tokenType, $white);
			return true;
		}

		if($must){
			throw $reader->throw("Expected any of " . json_encode($charset));
		}
		return false;
	}

	private function readIdentifier(StringReader $reader, bool $must, bool $needWhite = true, int $identifierType = Token::IDENTIFIER) : Generator{
		while(true){
			$identifier = $reader->matchRead(self::IDENTIFIER_REGEX);
			if($identifier === null){
				if($must){
					throw $reader->throw("Expected identifier");
				}
				return false;
			}

			if($reader->startsWith(':')){
				yield new Token(Token::FLAG, $identifier);
				yield new Token(Token::WHITESPACE, $reader->readExpected(":"));
				continue;
			}

			yield new Token($identifierType, $identifier);
			if($needWhite && !$reader->eof() && $reader->matches('[ \\t\\r\\n]+') === null){
				throw $reader->throw("Expected whitespace behind identifier");
			}
			return true;
		}
	}
}
