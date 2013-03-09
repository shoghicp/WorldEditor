<?php

/*
__PocketMine Plugin__
name=WorldEditor
description=World Editor is a port of WorldEdit to PocketMine
version=0.5dev
author=shoghicp
class=WorldEditor
apiversion=4
*/

/* 
Small Changelog
===============

0.4:
- Alpha_1.2 compatible release

0.5:
- Alpha_1.3dev compatible release
- Added Multiple Block lists for //set
- Added Multiple Block lists for replacement block //replace
- Added //limit, //desel, //wand
- Separated selections for each player
- In-game selection mode


*/



class WorldEditor implements Plugin{
	private $api, $selections, $path, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->limit = -1;
		$this->selections = array();
	}
	
	public function init(){
		$this->path = $this->api->plugin->createConfig($this, array(
			"block-limit" => -1,
			"wand-item" => IRON_HOE,
		));
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		$this->limit = $this->config["block-limit"];
		
		$this->api->addHandler("player.block.touch", array($this, "selectionHandler"), 15);
		$this->api->console->register("/", "WorldEditor commands", array($this, "command"));
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
				if($data["item"]->getID() == $this->config["wand-item"] and $this->api->ban->isOp($data["player"]->username)){
					if($data["type"] == "break"){
						$this->setPosition1($data["player"], $data["target"], $output);
					}else{
						$this->setPosition2($data["player"], $data["target"], $output);
					}
					$this->api->chat->sendTo(false, $output, $data["player"]->username);
					return false;
				}
				break;
		}
	}
	
	public function setPosition1($issuer, Vector3 $position, &$output){
		if(!isset($this->selections[$issuer->username])){
			$this->selections[$issuer->username] = array(false, false);
		}		
		$this->selections[$issuer->username][0] = array(round($position->x), round($position->y), round($position->z));
		$count = $this->countBlocks($this->selections[$issuer->username]);
		if($count === false){
			$count = "";
		}else{
			$count = " ($count)";
		}
		$output .= "First position set to (".$this->selections[$issuer->username][0][0].", ".$this->selections[$issuer->username][0][1].", ".$this->selections[$issuer->username][0][2].")$count.\n";
		return true;
	}
	
	public function setPosition2($issuer, Vector3 $position, &$output){
		if(!isset($this->selections[$issuer->username])){
			$this->selections[$issuer->username] = array(false, false);
		}		
		$this->selections[$issuer->username][1] = array(round($position->x), round($position->y), round($position->z));
		$count = $this->countBlocks($this->selections[$issuer->username]);
		if($count === false){
			$count = "";
		}else{
			$count = " ($count)";
		}
		$output .= "Second position set to (".$this->selections[$issuer->username][1][0].", ".$this->selections[$issuer->username][1][1].", ".$this->selections[$issuer->username][1][2].")$count.\n";
		return true;
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		if($alias !== false){
			$cmd = $alias;
		}
		if($cmd{0} === "/"){
			$cmd = substr($cmd, 1);
		}else{
			$output .= "Bad command\n";
		}
		switch($cmd){
			case "wand":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($issuer->hasItem($this->config["wand-item"])){
					$output .= "You already have the wand item.\n";
					break;
				}elseif($issuer->gamemode === CREATIVE){
					$output .= "You are on creative mode.\n";
				}else{
					$this->api->block->drop(new Vector3($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5), BlockAPI::getItem($this->config["wand-item"]));
				}
				$output .= "Break a block to set the #1 position, place for the #1.\n";
				break;
			case "desel":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				unset($this->selections[$issuer->username]);
				$output = "Selection cleared.\n";
				break;
			case "limit":
				if(!isset($params[0])){
					$output .= "Usage: //limit <limit>\n";
					break;
				}
				$limit = intval($params[0]);
				if($limit <= 0){
					$limit = -1;
				}
				if($this->config["block-limit"] > 0){
					$this->limit = $limit === -1 ? $this->config["block-limit"]:min($this->config["block-limit"], $limit);
				}
				$output .= "Block limit set to ".($this->limit === -1 ? "infinite":$this->limit)." block(s).\n";
				break;
			case "pos1":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}				
				$this->setPosition1($issuer, new Vector3($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5), $output);
				break;
			case "pos2":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$this->setPosition2($issuer, new Vector3($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5), $output);
				break;
			case "set":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$items = BlockAPI::fromString($params[0], true);
				foreach($items as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect block.\n";
						return $output;
					}
				}
				$this->W_set($this->selections[$issuer->username], $items, $output);
				break;
			case "replace":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
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
				
				$this->W_replace($this->selections[$issuer->username], $item1, $items2, $output);
				break;
			default:
			case "help":
				$output .= "Commands: //desel, //limit, //pos1, //pos2, //set, //replace, //help\n";
				break;
		}
		return $output;
	}
	
	private function countBlocks($selection){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false){
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
	
	private function W_set($selection, $blocks, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false){
			$output .= "Make a selection first\n";
			return false;
		}
		$bcnt = count($blocks) - 1;
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
		if($this->limit > 0 and $count > $this->limit){
			$output .= "Block limit of ".$this->limit." exceeded, tried to change $count block(s).\n";
			return false;
		}
		$output .= "$count block(s) have been changed.\n";
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $blocks[mt_rand(0, $bcnt)];
					$this->api->block->setBlock(new Vector3($x, $y, $z), $b->getID(), $b->getMetadata(), false); //WARNING!!! Temp. method until I redone chunk sending
				}
			}
		}
		return true;
	}
	
	private function W_replace($selection, Item $block1, $blocks2, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false){
			$output .= "Make a selection first\n";
			return false;
		}
		
		$id1 = $block1->getID();
		$meta1 = $block1->getMetadata();
		
		$bcnt2 = count($blocks2) - 1;
		
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
					$b = $this->api->block->getBlock(new Vector3($x, $y, $z));
					if($b->getID() === $id1 and ($meta1 === false or $b->getMetadata() === $meta1)){
						++$count;
						if($this->limit > 0 and $count > $this->limit){
							$output .= "Block limit of ".$this->limit." exceeded, tried to change $count block(s).\n";
							return false;
						}
						$b2 = $blocks2[mt_rand(0, $bcnt2)];
						$this->api->block->setBlock($b, $b2->getID(), $b2->getMetadata(), false); //WARNING!!! Temp. method until I redone chunk sending
					}					
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
}