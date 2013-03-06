<?php

/*
__PocketMine Plugin__
name=WorldEditor
description=World Editor is a port of WorldEdit to PocketMine
version=0.4
author=shoghicp
class=WorldEditor
apiversion=3
*/

/* 
Small Changelog
===============

0.4:
- Alpha_1.2 compatible release


*/


class WorldEditor implements Plugin{
	private $api, $pos1, $pos2, $path, $config;
	public function __construct(ServerAPI $api, $server = false){
		$this->api = $api;
		$this->pos1 = false;
		$this->pos2 = false;
	}
	
	public function init(){
		$this->path = $this->api->plugin->createConfig($this, array(
			"target-player" => false, //player ingame
		));	
		$this->config = $this->api->plugin->readYAML($this->path."config.yml");
		$this->api->console->register("/", "WorldEditor commands", array($this, "command"));
		if(!method_exists($this->api->console, "alias")){
			$this->api->console->register("/pos1", "WorldEditor commands", array($this, "command"));
			$this->api->console->register("/pos2", "WorldEditor commands", array($this, "command"));
			$this->api->console->register("/set", "WorldEditor commands", array($this, "command"));
			$this->api->console->register("/replace", "WorldEditor commands", array($this, "command"));
			$this->api->console->register("/help", "WorldEditor commands", array($this, "command"));		
		}else{
			$this->api->console->alias("/pos1", "/");
			$this->api->console->alias("/pos2", "/");
			$this->api->console->alias("/set", "/");
			$this->api->console->alias("/replace", "/");
			$this->api->console->alias("/help", "/");
		}
	}
	
	public function __destruct(){

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
			case "pos1":
				if($issuer instanceof Player){
					$target = $issuer->username;
				}else{
					$target = trim(implode(" ", $params));
					$target = $target != "" ? $target:$this->config["target-player"];
				}
				
				if(($player = $this->api->player->get($target)) !== false){
					$this->pos1 = array(round($player->entity->x - 0.5), round($player->entity->y), round($player->entity->z - 0.5));
					$count = $this->countBlocks();
					if($count === false){
						$count = "";
					}else{
						$count = " ($count)";
					}
					$output .= "First position set to (".$this->pos1[0].", ".$this->pos1[1].", ".$this->pos1[2].")$count.\n";
				}else{
					$output .= "Target player ".$target." is not connected.\n";
				}
				break;
			case "pos2":
				if($issuer instanceof Player){
					$target = $issuer->username;
				}else{
					$target = trim(implode(" ", $params));
					$target = $target != "" ? $target:$this->config["target-player"];
				}
				
				if(($player = $this->api->player->get($target)) !== false){
					$this->pos2 = array(round($player->entity->x - 0.5), round($player->entity->y), round($player->entity->z - 0.5));
					$count = $this->countBlocks();
					if($count === false){
						$count = "";
					}else{
						$count = " ($count)";
					}
					$output .= "Second position set to (".$this->pos2[0].", ".$this->pos2[1].", ".$this->pos2[2].")$count.\n";
				}else{
					$output .= "Target player ".$target." is not connected.\n";
				}
				break;
			case "set":
				$item = BlockAPI::fromString($params[0]);
				if($item->getID() > 0xff){
					$output .= "Incorrect block.\n";
				}
				$this->W_set($item, $output);
				break;
			case "replace":
				$item1 = BlockAPI::fromString($params[0]);
				if($item1->getID() > 0xff){
					$output .= "Incorrect block 1.\n";
				}
				$item2 = BlockAPI::fromString($params[1]);
				if($item2->getID() > 0xff){
					$output .= "Incorrect block 2.\n";
				}
				
				$this->W_replace($item1, $item2, $output);
				break;
			default:
			case "help":
				$output .= "Commands: //pos1, //pos2, //set, //replace, //help\n";
				break;
		}
		return $output;
	}
	
	private function countBlocks(){
		if($this->pos1 === false or $this->pos2 === false){
			return false;
		}	
		$startX = min($this->pos1[0], $this->pos2[0]);
		$endX = max($this->pos1[0], $this->pos2[0]);
		$startY = min($this->pos1[1], $this->pos2[1]);
		$endY = max($this->pos1[1], $this->pos2[1]);
		$startZ = min($this->pos1[2], $this->pos2[2]);
		$endZ = max($this->pos1[2], $this->pos2[2]);
		return ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
	}
	
	private function W_set(Item $block, &$output = null){
		if($this->pos1 === false or $this->pos2 === false){
			$output .= "Make a selection first\n";
			return false;
		}
		$id = $block->getID();
		$meta = $block->getMetadata();
		$startX = min($this->pos1[0], $this->pos2[0]);
		$endX = max($this->pos1[0], $this->pos2[0]);
		$startY = min($this->pos1[1], $this->pos2[1]);
		$endY = max($this->pos1[1], $this->pos2[1]);
		$startZ = min($this->pos1[2], $this->pos2[2]);
		$endZ = max($this->pos1[2], $this->pos2[2]);
		$count = ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
		$output .= "$count block(s) have been changed.\n";
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$this->api->block->setBlock(new Vector3($x, $y, $z), $id, $meta, false); //WARNING!!! Temp. method until I redone chunk sending
				}
			}
		}
		return true;
	}
	
	private function W_replace(Item $block1, Item $block2, &$output = null){
		if($this->pos1 === false or $this->pos2 === false){
			$output .= "Make a selection first\n";
			return false;
		}
		
		$id1 = $block1->getID();
		$meta1 = $block1->getMetadata();
		$id2 = $block2->getID();
		$meta2 = $block2->getMetadata();
		
		$startX = min($this->pos1[0], $this->pos2[0]);
		$endX = max($this->pos1[0], $this->pos2[0]);
		$startY = min($this->pos1[1], $this->pos2[1]);
		$endY = max($this->pos1[1], $this->pos2[1]);
		$startZ = min($this->pos1[2], $this->pos2[2]);
		$endZ = max($this->pos1[2], $this->pos2[2]);
		$count = 0; 
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $this->api->block->getBlock(new Vector3($x, $y, $z));
					if($b->getID() === $id1 and ($meta1 === false or $b->getMetadata() === $meta1)){
						++$count;
						$this->api->block->setBlock($b, $id2, $meta2, false); //WARNING!!! Temp. method until I redone chunk sending
					}					
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
}