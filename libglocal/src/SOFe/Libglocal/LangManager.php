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

use InvalidArgumentException;
use pocketmine\plugin\Plugin;
use SOFe\Libglocal\ArgType\ArgType;
use SOFe\Libglocal\ArgType\DefaultArgTypeProvider;
use function in_array;

class LangManager{
	/** @var Plugin */
	protected $plugin;
	/** @var ArgTypeProvider[] */
	public $typeProviders = [];

	/** @var LangParser[] */
	protected $inputs = [];
	/** @var LangParser[] */
	protected $bases = [];

	/** @var Message[] */
	protected $messages = [];

	public function __construct(Plugin $plugin){
		$this->plugin = $plugin;
		$this->typeProviders[] = new DefaultArgTypeProvider();
	}

	public function loadFile(string $humanName, $resource) : void{
		$this->inputs[] = $parser = new LangParser($this, $humanName, $resource);
		$parser->parseHeader();
		if($parser->isBase()){
			$this->bases[] = $parser;
		}
	}

	public function init() : void{
		foreach($this->bases as $langParser){
			$langParser->parseMessages();
		}
		foreach($this->inputs as $langParser){
			if(!in_array($langParser, $this->bases, true)){
				$langParser->parseMessages();
			}
		}

		foreach($this->messages as $message){
			$message->init();
		}
	}


	public function getPlugin() : Plugin{
		return $this->plugin;
	}

	public function &getMessages() : array{
		return $this->messages;
	}


	public function createArgType(?string $modifier, string $name) : ArgType{
		foreach($this->typeProviders as $provider){
			if(($type = $provider->createArgType($modifier, $name)) !== null){
				return $type;
			}
		}

		throw new ParseException("Unknown argument type \"$modifier:$name\"");
	}

	public function translate(string $lang, string $id, array $args) : string{
		if(!isset($this->messages[$id])){
			throw new InvalidArgumentException("Translation \"{$id}\" not found");
		}

		return $this->messages[$id]->translate($lang, $args);
	}

	public function addTypeProvider(ArgTypeProvider $provider) : void{
		$this->typeProviders[] = $provider;
	}
}
