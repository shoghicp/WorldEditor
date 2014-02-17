<?php

/*
__PocketMine Plugin__
name=WorldEditor
description=World Editor is a port of WorldEdit to PocketMine
version=0.9.1
author=shoghicp
class=WorldEditor
apiversion=7,8,9,10,11,12
*/

/* 
Small Changelog
===============

0.4:
- Alpha_1.2 compatible release

0.5:
- Alpha_1.2.1 compatible release
- Added Multiple Block lists for //set
- Added Multiple Block lists for replacement block //replace
- Added //limit, //desel, //wand, //sphere, //hsphere, /toggleeditwand
- Separated selections for each player
- In-game selection mode
- Sessions

0.6:
- Alpha_1.3dev compatible release

0.7:
- Multiworld compatible release
- Added correct block counts

0.8:
- Added //copy, //paste, //cut

0.9:
- Improved memory consumption on big changes

0.9.1:
- Alpha_1.3.12 release

*/



class WorldEditor implements Plugin{
	private $api, $sessions, $path, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->sessions = array();
	}
	
	public function init(){
		$this->path = $this->api->plugin->configPath($this);
		$this->config = new Config($this->path."config.yml", CONFIG_YAML, array(
			"block-limit" => -1,
			"wand-item" => IRON_HOE,
		));
		
		$this->api->addHandler("player.block.touch", array($this, "selectionHandler"), 15);
		$this->api->console->register("/", "WorldEditor commands.", array($this, "command"));
		$this->api->console->register("toggleeditwand", "", array($this, "command"));
		$this->api->console->alias("/copy", "/");
		$this->api->console->alias("/cut", "/");
		$this->api->console->alias("/paste", "/");
		$this->api->console->alias("/hsphere", "/");
		$this->api->console->alias("/sphere", "/");
		$this->api->console->alias("/wand", "/");
		$this->api->console->alias("/limit", "/");
		$this->api->console->alias("/desel", "/");
		$this->api->console->alias("/pos1", "/");
		$this->api->console->alias("/pos2", "/");
		$this->api->console->alias("/set", "/");
		$this->api->console->alias("/replace", "/");
		$this->api->console->alias("/help", "/");
	}
	
	public function __destruct(){

	}
	
	public function selectionHandler($data, $event){
		$output = "";
		switch($event){
			case "player.block.touch":
				if($data["item"]->getID() == $this->config->get("wand-item") and $this->api->ban->isOp($data["player"]->username) and $this->session($data["player"])["wand-usage"] === true){
					$session =& $this->session($data["player"]);
					if($data["type"] == "break"){
						$this->setPosition1($session, $data["target"], $output);
					}else{
						$this->setPosition2($session, $data["target"], $output);
					}
					$this->api->chat->sendTo(false, $output, $data["player"]->username);
					return false;
				}
				break;
		}
	}
	
	public function &session(Player $issuer){
		if(!isset($this->sessions[$issuer->username])){
			$this->sessions[$issuer->username] = array(
				"selection" => array(false, false),
				"clipboard" => false,
				"block-limit" => $this->config->get("block-limit"),
				"wand-usage" => true,
			);
		}
		return $this->sessions[$issuer->username];
	}
	
	public function setPosition1(&$session, Position $position, &$output){
		$session["selection"][0] = array(round($position->x), round($position->y), round($position->z), $position->level);
		$count = $this->countBlocks($session["selection"]);
		if($count === false){
			$count = "";
		}else{
			$count = " ($count)";
		}
		$output .= "First position set to (".$session["selection"][0][0].", ".$session["selection"][0][1].", ".$session["selection"][0][2].")$count.\n";
		return true;
	}
	
	public function setPosition2(&$session, Position $position, &$output){
		$session["selection"][1] = array(round($position->x), round($position->y), round($position->z), $position->level);
		$count = $this->countBlocks($session["selection"]);
		if($count === false){
			$count = "";
		}else{
			$count = " ($count)";
		}
		$output .= "Second position set to (".$session["selection"][1][0].", ".$session["selection"][1][1].", ".$session["selection"][1][2].")$count.\n";
		return true;
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		if($alias !== false){
			$cmd = $alias;
		}
		if($cmd{0} === "/"){
			$cmd = substr($cmd, 1);
		}
		
		switch($cmd){
			case "paste":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				
				$this->W_paste($session["clipboard"], $issuer->entity, $output);
				break;
			case "copy":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"], $startX, $startY, $startZ);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to copy $count block(s).\n";
					break;
				}
				
				$blocks = $this->W_copy($session["selection"], $output);
				if(count($blocks) > 0){
					$offset = array($startX - $issuer->entity->x - 0.5, $startY - $issuer->entity->y, $startZ - $issuer->entity->z - 0.5);
					$session["clipboard"] = array($offset, $blocks);
				}
				break;
			case "cut":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"], $startX, $startY, $startZ);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to cut $count block(s).\n";
					break;
				}
				
				$blocks = $this->W_cut($session["selection"], $output);
				if(count($blocks) > 0){
					$offset = array($startX - $issuer->entity->x - 0.5, $startY - $issuer->entity->y, $startZ - $issuer->entity->z - 0.5);
					$session["clipboard"] = array($offset, $blocks);
				}
				break;
			case "toggleeditwand":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$session["wand-usage"] = $session["wand-usage"] == true ? false:true;
				$output .= "Wand Item is now ".($session["wand-usage"] === true ? "enabled":"disabled").".\n";
				break;
			case "wand":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($issuer->hasItem($this->config->get("wand-item"))){
					$output .= "You already have the wand item.\n";
					break;
				}elseif($issuer->gamemode === CREATIVE){
					$output .= "You are on creative mode.\n";
				}else{
					$this->api->entity->drop(new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), BlockAPI::getItem($this->config->get("wand-item")));
				}
				$output .= "Break a block to set the #1 position, place for the #1.\n";
				break;
			case "desel":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$session["selection"] = array(false, false);
				$output = "Selection cleared.\n";
				break;
			case "limit":
				if(!isset($params[0]) or trim($params[0]) === ""){
					$output .= "Usage: //limit <limit>\n";
					break;
				}
				$limit = intval($params[0]);
				if($limit < 0){
					$limit = -1;
				}
				if($this->config->get("block-limit") > 0){
					$limit = $limit === -1 ? $this->config->get("block-limit"):min($this->config->get("block-limit"), $limit);
				}
				$this->session($issuer)["block-limit"] = $limit;
				$output .= "Block limit set to ".($limit === -1 ? "infinite":$limit)." block(s).\n";
				break;
			case "pos1":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$this->setPosition1($this->session($issuer), new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), $output);
				break;
			case "pos2":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$this->setPosition2($this->session($issuer), new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), $output);
				break;

			case "hsphere":
				$filled = false;
			case "sphere":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if(!isset($filled)){
					$filled = true;
				}
				if(!isset($params[1]) or $params[1] == ""){
					$output .= "Usage: //$cmd <block> <radius>.\n";
					break;
				}
				$radius = abs(floatval($params[1]));
				
				$session =& $this->session($issuer);
				$items = BlockAPI::fromString($params[0], true);
				
				foreach($items as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect block.\n";
						return $output;
					}
				}
				$this->W_sphere(new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), $items, $radius, $radius, $radius, $filled, $output);
				break;
			case "set":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"]);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to change $count block(s).\n";
					break;
				}
				$items = BlockAPI::fromString($params[0], true);
				foreach($items as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect block.\n";
						return $output;
					}
				}
				$this->W_set($session["selection"], $items, $output);
				break;
			case "replace":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"]);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to change $count block(s).\n";
					break;
				}
				$item1 = BlockAPI::fromString($params[0]);
				if($item1->getID() > 0xff){
					$output .= "Incorrect target block.\n";
					break;
				}
				$items2 = BlockAPI::fromString($params[1], true);
				foreach($items2 as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect replacement block.\n";
						return $output;
					}
				}
				
				$this->W_replace($session["selection"], $item1, $items2, $output);
				break;
			default:
			case "help":
				$output .= "Commands: //cut, //copy, //paste, //sphere, //hsphere, //desel, //limit, //pos1, //pos2, //set, //replace, //help, //wand, /toggleeditwand\n";
				break;
		}
		return $output;
	}
	
	private function countBlocks($selection, &$startX = null, &$startY = null, &$startZ = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			return false;
		}
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		return ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
	}

	private function W_paste($clipboard, Position $pos, &$output = null){
		if(count($clipboard) !== 2){
			$output .= "Copy something first.\n";
			return false;
		}
		$clipboard[0][0] += $pos->x - 0.5;
		$clipboard[0][1] += $pos->y;
		$clipboard[0][2] += $pos->z - 0.5;
		$offset = array_map("round", $clipboard[0]);
		$count = 0;
		
		foreach($clipboard[1] as $x => $i){
			foreach($i as $y => $j){
				foreach($j as $z => $block){
					$b = BlockAPI::get(ord($block{0}), ord($block{1}));
					$count += (int) $pos->level->setBlockRaw(new Vector3($x + $offset[0], $y + $offset[1], $z + $offset[2]), $b, false);
					unset($b);
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
	
	private function W_copy($selection, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return array();
		}
		$level = $selection[0][3];
		
		$blocks = array();
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = $this->countBlocks($selection);
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = chr($b->getID()).chr($b->getMetadata());
					unset($b);
				}
			}
		}
		$output .= "$count block(s) have been copied.\n";
		return $blocks;
	}
	
	private function W_cut($selection, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return array();
		}
		$totalCount = $this->countBlocks($selection);
		if($totalCount > 524288){
			$send = false;
		}else{
			$send = true;
		}
		$level = $selection[0][3];
		
		$blocks = array();
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = $this->countBlocks($selection);
		$air = new AirBlock();
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = chr($b->getID()).chr($b->getMetadata());
					$level->setBlockRaw(new Vector3($x, $y, $z), $air, false, $send);
					unset($b);
				}
			}
		}
		if($send === false){
			$forceSend = function($X, $Y, $Z){
				$this->changedCount[$X.":".$Y.":".$Z] = 4096;
			};			
			$forceSend->bindTo($level, $level);
			for($X = $startX >> 4; $X <= ($endX >> 4); ++$X){
				for($Y = $startY >> 4; $Y <= ($endY >> 4); ++$Y){
					for($Z = $startZ >> 4; $Z <= ($endZ >> 4); ++$Z){
						$forceSend($X,$Y,$Z);
					}
				}
			}
		}
		$output .= "$count block(s) have been cut.\n";
		return $blocks;
	}
	
	private function W_set($selection, $blocks, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return false;
		}
		$totalCount = $this->countBlocks($selection);
		if($totalCount > 524288){
			$send = false;
		}else{
			$send = true;
		}
		$level = $selection[0][3];
		$bcnt = count($blocks) - 1;
		$bcnt2 = count($blocks2) - 1;
		if($bcnt < 0){
			$output .= "Incorrect blocks.\n";
			return false;
		}
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = 0; //$count = $this->countBlocks($selection);
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $blocks[mt_rand(0, $bcnt)];
					$count += (int) $level->setBlockRaw(new Vector3($x, $y, $z), $b->getBlock(), false, $send);
				}
			}
		}
		if($send === false){
			$forceSend = function($X, $Y, $Z){
				$this->changedCount[$X.":".$Y.":".$Z] = 4096;
			};			
			$forceSend->bindTo($level, $level);
			for($X = $startX >> 4; $X <= ($endX >> 4); ++$X){
				for($Y = $startY >> 4; $Y <= ($endY >> 4); ++$Y){
					for($Z = $startZ >> 4; $Z <= ($endZ >> 4); ++$Z){
						$forceSend($X,$Y,$Z);
					}
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
	
	private function W_replace($selection, Item $block1, $blocks2, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return false;
		}
		
		$totalCount = $this->countBlocks($selection);
		if($totalCount > 524288){
			$send = false;
		}else{
			$send = true;
		}
		$level = $selection[0][3];
		$id1 = $block1->getID();
		$meta1 = $block1->getMetadata();
		
		$bcnt2 = count($blocks2) - 1;
		if($bcnt2 < 0){
			$output .= "Incorrect blocks.\n";
			return false;
		}
		
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = 0;
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					if($b->getID() === $id1 and ($meta1 === false or $b->getMetadata() === $meta1)){
						$count += (int) $level->setBlockRaw($b, $blocks2[mt_rand(0, $bcnt2)]->getBlock(), false, $send);
					}
					unset($b);
				}
			}
		}
		if($send === false){
			$forceSend = function($X, $Y, $Z){
				$this->changedCount[$X.":".$Y.":".$Z] = 4096;
			};			
			$forceSend->bindTo($level, $level);
			for($X = $startX >> 4; $X <= ($endX >> 4); ++$X){
				for($Y = $startY >> 4; $Y <= ($endY >> 4); ++$Y){
					for($Z = $startZ >> 4; $Z <= ($endZ >> 4); ++$Z){
						$forceSend($X,$Y,$Z);
					}
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
	
	public static function lengthSq($x, $y, $z){
		return ($x * $x) + ($y * $y) + ($z * $z);
	}
	
	private function W_sphere(Position $pos, $blocks, $radiusX, $radiusY, $radiusZ, $filled = true, &$output = null){
		$count = 0;

        $radiusX += 0.5;
        $radiusY += 0.5;
        $radiusZ += 0.5;

        $invRadiusX = 1 / $radiusX;
        $invRadiusY = 1 / $radiusY;
        $invRadiusZ = 1 / $radiusZ;

        $ceilRadiusX = (int) ceil($radiusX);
        $ceilRadiusY = (int) ceil($radiusY);
        $ceilRadiusZ = (int) ceil($radiusZ);

		$bcnt = count($blocks) - 1;
		
        $nextXn = 0;
		$breakX = false;
		for($x = 0; $x <= $ceilRadiusX and $breakX === false; ++$x){
			$xn = $nextXn;
			$nextXn = ($x + 1) * $invRadiusX;
			$nextYn = 0;
			$breakY = false;
			for($y = 0; $y <= $ceilRadiusY and $breakY === false; ++$y){
				$yn = $nextYn;
				$nextYn = ($y + 1) * $invRadiusY;
				$nextZn = 0;
				$breakZ = false;
				for($z = 0; $z <= $ceilRadiusZ; ++$z){
					$zn = $nextZn;
					$nextZn = ($z + 1) * $invRadiusZ;
					$distanceSq = WorldEditor::lengthSq($xn, $yn, $zn);
					if($distanceSq > 1){
						if($z === 0){
							if($y === 0){
								$breakX = true;
								$breakY = true;
								break;
							}
							$breakY = true;
							break;
						}
						break;
					}
					
					if($filled === false){						
						if(WorldEditor::lengthSq($nextXn, $yn, $zn) <= 1 and WorldEditor::lengthSq($xn, $nextYn, $zn) <= 1 and WorldEditor::lengthSq($xn, $yn, $nextZn) <= 1){
							continue;
						}
					}					

					$count += (int) $pos->level->setBlockRaw($pos->add($x, $y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, $y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add($x, -$y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add($x, $y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, -$y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add($x, -$y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, $y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, -$y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					
				}
			}
		}
		
		$output .= "$count block(s) have been changed.\n";
		return true;	
	}
}
