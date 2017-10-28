<?php

namespace Ad5001\Spooky\sounds;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\level\sound\GenericSound;


class TheReturnMusicPlay extends GenericSound{

	public function __construct(Vector3 $pos, $pitch = 1){
		parent::__construct($pos, LevelEventPacket::EVENT_SOUND_ARMOR_STAND_HIT, $pitch);
	}
}