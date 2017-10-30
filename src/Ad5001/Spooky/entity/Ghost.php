<?php
namespace Ad5001\Spooky\entity;

use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\level\Level;
use pocketmine\entity\Human;
use pocketmine\block\Block;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\EntityEventPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;

use pocketmine\math\Vector3;

use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Effect;

use Ad5001\Spooky\Main;
use Ad5001\Spooky\sounds\TheReturnMusicPlay;
use Ad5001\Spooky\tasks\TickTask;



class Ghost extends Human {

	const NETWORK_ID = -1;


	protected $associatedPlayer = null;

	protected $moveTime = 0;

	public $attCooldown = 0;
	public $attackCooldown = 0;

	/**
	 * Constructs the class
	 *
	 * @param Level $level
	 * @param CompoundTag $nbt
	 */
    public function __construct(Level $level, CompoundTag $nbt) {
		$this->setDataProperty(self::DATA_SCALE, self::DATA_TYPE_FLOAT, /*new FloatTag("Scale", */1.2);
		parent::__construct($level, $nbt);
		$it = Item::get(Item::GOLDEN_HOE, 0);
		$it->addEnchantment(Enchantment::getEnchantment(Enchantment::SHARPNESS));
		$this->getInventory()->setItemInHand($it);
	}

	/**
	 * Gets the targeted player
	 *
	 * @return void
	 */
	public function getPlayer(){
		return $this->associatedPlayer;
	}

	/**
	 * Returns the speed of the entity
	 *
	 * @return float
	 */
	public function getSpeed(): float {
		return 2;
    }



	// Phases


	/**
	 * Starts the fight.
	 * 
	 * @param Player $player
	 */
	public function startFight(Player $p) {
		$pk = new PlaySoundPacket();
        $pk->soundName = "mob.armor_stand.hit";
        $pk->x = (int)$p->x;
        $pk->y = (int)$p->y;
        $pk->z = (int)$p->z;
        $pk->volume = 500;
        $pk->pitch = 1;
        $p->dataPacket($pk);
		// Only after the ghost appeared, we can remove it.
		Effect::getEffect(Effect::BLINDNESS)->setDuration(66*20)->setVisible(false)->add($p);
		// $p->addEffect(Effect::getEffect(Effect::SLOWNESS)->setDuration(30*20)->setAmplifier(5)->setVisible(false));
		$this->associatedPlayer = $p;
		TickTask::registerGhost($this);
		$p->sendPopup("Music: The Return by Niviro, www.djniviro.com");
	}
	
	/**
	 * Starts a sequence where the ghost is in an intense fight.
	 *
	 * @return void
	 */
	public function intenseFight(){
		if(!$this->checkIfConnected()) return;
		// TODO: Custom intense fight
	}

	/**
	 * Starts a sequence where the ghost is in an calm fight.
	 *
	 * @return void
	 */
	public function calmFight(){
		if(!$this->checkIfConnected()) return;
	}
	
	/**
	 * Starts a sequence where the ghost blinds the player,
	 * slows him down (zooming effect w/ fov) and he appears invuulnerable 
	 * in front of the player.
	 * @return void
	 */
	public function scareEnterPhase(){
		if(!$this->checkIfConnected()) return;
        $spawnBlock = $this->getPlayer()->getLineOfSight(2);
        $spawnBlock = $spawnBlock[count($spawnBlock) -1];
		$this->getPlayer()->addEffect(
			Effect::getEffect(Effect::BLINDNESS)->setDuration(3*20)->setAmplifier(4)->setVisible(false)
		);
		/*$this->getPlayer()->addEffect(
			Effect::getEffect(Effect::SLOWNESS)->setDuration(3*20)->setAmplifier(10)->setVisible(false)
		);*/
		$this->getPlayer()->addEffect(
			Effect::getEffect(Effect::NAUSEA)->setDuration(3*20)->setAmplifier(10)->setVisible(false)
		);
		if($this->getLevel()->getBlock(new Vector3($spawnBlock->x, $spawnBlock->y, $spawnBlock->z))->getId() !== 0) {
			$this->teleport(new Vector3($spawnBlock->x, $spawnBlock->y + 1, $spawnBlock->z), abs($this->getPlayer()->getYaw() - 180));
		} else {
			$this->teleport(new Vector3($spawnBlock->x, $spawnBlock->y, $spawnBlock->z), abs($this->getPlayer()->getYaw() - 180));
		}
		$this->broadcastNewPos();
	}
	
	/**
	 * Sequence where the ghost starts 
	 * to move the player randomly.
	 *
	 * @return void
	 */
	public function movePlayerRandomly(){
		if(!$this->checkIfConnected()) return;
		if(rand(0,20) == 1) {
			$this->associatedPlayer->teleport(new Vector3($this->associatedPlayer->x + rand(0, 3) - rand(0, 3), $this->associatedPlayer->y, $this->associatedPlayer->z + rand(0, 3) - rand(0, 3)));
		}
	}
	
	/**
	 * Sequence where the ghost starts 
	 * to destroy blocks around the player randomly.
	 *
	 * @return void
	 */
	public function destroyBlocksRandomly(){
		if(!$this->checkIfConnected()) return;
		$this->teleport(new Vector3($this->getPlayer()->x, $this->getPlayer()->y + 3, $this->getPlayer()->z), $this->yaw == 0 ? 359 : $this->yaw - 1, 130);
		srand(time());
		foreach($this->getLevel()->getEntities() as $et){
			if($et instanceof \pocketmine\entity\FallingSand && $et->namedtag->getInt("SpawnTime") !== $null){
				$diffTime = time() - $et->namedtag->getInt("SpawnTime");
				if($diffTime < 4) $et->close();
			}
		}
		for($i = 0; $i < 3; $i++) {
			$rx = rand(0, 12) - 6 + $this->getPlayer()->x;
			$rz = rand(0, 12) - 6 + $this->getPlayer()->z;
			for($ry = 128; $this->getLevel()->getBlock(new Vector3($rx, $ry, $rz))->getId() !== 0 || $ry == 0; $ry--){} // Determining y from the higthest workable block
			$b = $this->getLevel()->getBlock(new Vector3($rx, $ry, $rz));
			// Creating falling sand block
			$nbt = new CompoundTag("", [
				new ListTag("Pos", [
					new DoubleTag("", $b->x + 0,5),
					new DoubleTag("", $b->y),
					new DoubleTag("", $b->z + 0,5)
				]),
				new ListTag("Motion", [
					new DoubleTag("", 0),
					new DoubleTag("", 10),
					new DoubleTag("", 0)
				]),
				new ListTag("Rotation", [
					new FloatTag("", 0),
					new FloatTag("", 0)
				])
			]);;
			$nbt->setInt("TileID", $b->getId());
			$nbt->setInt("SpawnTime", time());
			$nbt->setByte("Data", $b->getDamage());
			$nbt->getListTag("Motion")[1] = 3;
			$fall = Entity::createEntity("FallingSand", $this->getLevel(), $nbt);
			if($fall !== null){
				$fall->spawnToAll();
			}
			$this->getLevel()->setBlock(new Vector3($rx, $ry, $rz), Block::get(Block::AIR));
		}
	}


	/**
	 * Check if the player is still connected to the server
	 *
	 * @return void
	 */
	public function checkIfConnected(){
		if($this->associatedPlayer == null || !$this->associatedPlayer->isOnline()){
			TickTask::unregisterGhost($this);
			$this->close();
			return false;
		}
		return !($this->isClosed() || $this->getLevel() == null);
	}
    
	/**
	 * Spawns the ghost to the player
	 *
	 * @param Player $player
	 * @return void
	 */
    public function spawnTo(Player $player) {		
		if(!isset($this->hasSpawned[$player->getLoaderId()]) &&
		 isset($player->usedChunks[Level::chunkHash($this->chunk->getX(), $this->chunk->getZ())])) {
			$this->hasSpawned[$player->getLoaderId()] = $player;
			$pk = new AddPlayerPacket();
			$pk->username = "";
			$pk->uuid = $this->getUniqueId();
			$pk->entityRuntimeId = $this->getId();
			$pk->position = $this->asVector3();
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->item = $this->getInventory()->getItemInHand();
			$pk->metadata = $this->dataProperties;
			$pk->metadata[self::DATA_NAMETAG] = [self::DATA_TYPE_STRING, "§kThe Ghost"];
			$player->dataPacket($pk);
			$this->inventory->sendArmorContents($player);
			$player->getServer()->updatePlayerListData($this->getUniqueId(), $this->getId(), "§kThe Ghost", $this->skin,  [$player]);
		 }
	}


	// Event listeners
	 /**
     * Check the damage to reduce it by 55%
     *
     * @param EntityDamageEvent $event
     */
    public function attack(EntityDamageEvent $event) {
		if($event instanceof EntityDamageByEntityEvent) {
			if($event->getDamager() instanceof Player && $event->getDamager()->getName() == $this->getPlayer()->getName()){
				if($this->attCooldown == 0){
					$event->setDamage($event->getDamage() * 0.083);
					$deltaX = $this->x - $event->getDamager()->x;
					$deltaZ = $this->z - $event->getDamager()->z;
					$this->knockBack($event->getDamager(), $event->getDamage(), $this->x - $event->getDamager()->x, $this->z - $event->getDamager()->z, $event->getKnockBack());
					$this->setLastDamageCause($event);
					$this->setHealth($this->getHealth() - $event->getFinalDamage());
					$pk = new EntityEventPacket();
					$pk->entityRuntimeId = $this->getId();
					$pk->event = $this->getHealth() <= 0 ? EntityEventPacket::DEATH_ANIMATION : EntityEventPacket::HURT_ANIMATION; //Ouch!
					$this->getPlayer()->dataPacket($pk);
					if(!$this->isAlive()) {
						$this->getPlayer()->sendMessage("You got me! Happy halloween!");
						$this->close();
						TickTask::unregisterGhost($this);
					}
					$this->attCooldown = 5; //0.25s
				}
			} else {
				$event->setCancelled(true);
				$event->getDamager()->addEffect(
					Effect::getEffect(Effect::NAUSEA)->setDuration(30*20)->setAmplifier(99)->setVisible(false)
				);
			}
		}
	}

	/**
	 * When the entity gets an update, recalibrate entity
	 *
	 * @param int $currentTick
	 * @return bool
	 */
    public function onUpdate(int $currentTick): bool {
		if(!$this->checkIfConnected()) return false;
		if($this->attCooldown > 0) $this->attCooldown--;
		if($this->attackCooldown > 0) $this->attackCooldown--;
		// Teleportation
		if(rand(0, 200) == 0) { // Do we do teleportation?
			if(rand(0,1) == 0){ // Which kind of tp? Random around or forward?
				$los = $this->getLineOfSight(10);
				$b = $los[count($los) - 1];
				$b->y++;
				$this->teleport($b);
			} else {
				$x = rand($this->x + 8, $this->x - 8);
				$z = rand($this->z + 8, $this->z - 8);
				for($y = $this->y + 16; $y > $this->y - 16; $y--){
					if($this->getLevel()->getBlock(new Vector3($x, $y -1, $z))->getId() !== 0) break;
				}
				$this->teleport(new Vector3($x, $y, $z));
			}
		}
		if($this->moveTime !== 0){
			$this->moveTime--;
			return true;
		}
		if(abs($this->x - $this->getPlayer()->x) > 50 || abs($this->z - $this->getPlayer()->z) > 50) { // Too far away, teleporting him in front of the player
			$spawnBlock = $this->getPlayer()->getLineOfSight(2);
			$spawnBlock = $spawnBlock[count($spawnBlock) -1];
			if($this->getLevel()->getBlock(new Vector3($spawnBlock->x, $spawnBlock->y, $spawnBlock->z))->getId() !== 0) {
				$this->teleport(new Vector3($spawnBlock->x, $spawnBlock->y + 1, $spawnBlock->z), abs($this->getPlayer()->getYaw() - 180));
			} else {
				$this->teleport(new Vector3($spawnBlock->x, $spawnBlock->y, $spawnBlock->z), abs($this->getPlayer()->getYaw() - 180));
			}
			$this->broadcastNewPos();
		}
		$this->moveTime = 1;
		// Setting specific motion
		$diffV3 = $this->associatedPlayer->asVector3()->subtract($this->asVector3());
		$distDiff = $diffV3->asVector3()->abs();
		$distDiff = $distDiff->x + $distDiff->z;
		// Check if we can attack the player
		if($this->distanceSquared($this->associatedPlayer) <= 2){
			$this->attackEntity($this->associatedPlayer);
		}
		// If not, try moving torowards him
		if ($distDiff > 0) {
			$this->motionX = $this->getSpeed() * 0.15 * ($diffV3->x / $distDiff);
			$this->motionZ = $this->getSpeed() * 0.15 * ($diffV3->z / $distDiff);
			$this->yaw = rad2deg(-atan2($diffV3->x / $distDiff, $diffV3->z / $distDiff));
		}
		if($diffV3->y == 0){
			$this->pitch = 0;
		} else {
			$this->pitch = rad2deg(-atan2($diffV3->y, sqrt($diffV3->x ** 2 + $diffV3->z ** 2)));;
		}
		$currentB = $this->getLevel()->getBlock($this->asVector3());
		if($currentB instanceof \pocketmine\block\Liquid){ // in water, we need to get it floating
			$this->motionY = $this->gravity * 2;
		} else {
			// Check if the ghost is in air and not stuck in the ground. Then, we'll get the target block to check if it's possible to jump.
			if($currentB->canPassThrough()){
				$targetB = $this->getTargetBlock(2);
			} else {
				$targetB = $currentB;
			}
			$canJump = true;
			// Check 3 blocks up that position (to see if the entity can go up)
			for($i = 1; $i <= 3; $i++){
				$blockUp = $targetB->asVector3();
				$blockUp->y += $i;
				if(!$this->getLevel()->getBlock($blockUp)->canPassThrough()) $canJump = false;
			}
			// FInally, jump if possible
			$under = $this->asVector3()->floor();
			$under->y--;
			if($canJump && $this->gravity * 3.2 > $this->motionY) {
				$this->motionY = $this->gravity * 3.2;
			} elseif ($this->getLevel()->getBlock($under)->getId() == 0 ) {
				$this->motionY = -$this->gravity * 3.2;
			} else {
				$this->motionY = 0;
			}
		}
		if(!isset($this->lastUpdate)) $this->lastUpdate = $currentTick - 1;
		$tickDiff = abs($currentTick - $this->lastUpdate);
        $this->lastUpdate = $currentTick;
		$this->fastMove($this->motionX * $tickDiff, $this->motionY, $this->motionZ * $tickDiff);
		$this->updateMovement();
		$this->broadcastNewPos();
		return true;
	}

	/**
	 * Attacks an entity when the ghost is close enought from it.
	 *
	 * @param Entity $et
	 * @return void
	 */
	public function attackEntity(Entity $et){
		if($et instanceof Player && $this->attackCooldown == 0){
			$damage = [
				EntityDamageEvent::MODIFIER_BASE => 3 // Two hit a player which has no armor
			];
			$points = 0;
			foreach($et->getInventory()->getArmorContents() as $armorItem){
				$points += $armorItem->getDefensePoints();
			}
			$damage[EntityDamageEvent::MODIFIER_ARMOR] = -($damage[EntityDamageEvent::MODIFIER_BASE] * $points * 0.04);
			$ev = new EntityDamageByEntityEvent($this, $this->associatedPlayer, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
			$et->attack($ev);
			$this->attackCooldown = 10; // 0.5s
		}
	}

	/**
	 * Gives some drops when the ghost die
	 *
	 * @return array
	 */
	public function getDrops() : array{
		switch(rand(0, 2)){
			case 0:
			$it = Item::get(Item::GOLDEN_HOE, 0);
			$it->setCustomName("§r§cSoul Stealer");
			$it->setNamedTagEntry(new IntTag("customDamage", 10));
			$it->setNamedTagEntry(new IntTag("sneakInvisible", 1));
			$e = Enchantment::getEnchantment(Enchantment::SHARPNESS);
			$e->setLevel(10);
			$it->addEnchantment($e);
			return [$it];
			break;
			case 1:
			break;
			case 2:
			return [];
			break;
		}
	}


	/**
	 * Broadcats the new position to the player.
	 *
	 * @return void
	 */
	public function broadcastNewPos(){
		$pk = new MovePlayerPacket();
		$pk->entityRuntimeId = $this->getId();
		$pk->position = $this->asVector3();
		$pk->yaw = $this->yaw;
		$pk->headYaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$this->getPlayer()->dataPacket($pk);
		$this->getPlayer()->sendPopup("He's " . ($this->x - $this->getPlayer()->x) . ", " . ($this->y - $this->getPlayer()->y) . ", " . ($this->z - $this->getPlayer()->z) . " blocks away");
	}


}