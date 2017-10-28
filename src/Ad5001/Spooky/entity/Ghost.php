<?php
namespace Ad5001\Spooky\entity;

use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\entity\Human;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\math\Vector3;

use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\entity\Effect;

use Ad5001\Spooky\Main;
use Ad5001\Spooky\sounds\TheReturnMusicPlay;
use Ad5001\Spooky\tasks\TickTask;



class Ghost extends Human {

	/**
	 * 0: Not fighting
	 * 1: Simple fight
	 *
	 * @var integer
	 */
	protected $fightType = 0;

	protected $associatedPlayer = null;

	/**
	 * Constructs the class
	 *
	 * @param Level $level
	 * @param CompoundTag $nbt
	 */
    public function __construct(Level $level, CompoundTag $nbt) {
		$this->setDataProperty(self::DATA_SCALE, self::DATA_TYPE_FLOAT, new FloatTag("Scale", 1.2));
		parent::__construct($level, $nbt);
		$it = Item::get(Item::GOLDEN_HOE, 0);
		$it->addEnchantement(Enchantement::getEnchantement(Enchantement::SHARPNESS));
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
		return $this->fightType * 2;
    }



	// Phases


	/**
	 * Starts the fight.
	 * 
	 * @param Player $player
	 */
	public function startFight(Player $p) {
		$p->getLevel()->addSound(new TheReturnMusicPlay($p->asVector3()), [$p->getViewers()]);
		// Only after the ghost appeared, we can remove it.
		Effect::getEffectById(Effect::BLINDNESS)->setDuration(66*20)->setVisible(false)->add($p);
		Effect::getEffectById(Effect::INVISIBILITY)->setDuration(63*20)->setVisible(false)->add($this);
		$this->spawnTo($p);
		$this->associatedPlayer = $p;
		TickTask::registerGhost($this);
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
		$this->fightType = 1;
	}
	
	/**
	 * Starts a sequence where the ghost blinds the player,
	 * slows him down (zooming effect w/ fov) and he appears invuulnerable 
	 * in front of the player.
	 * @return void
	 */
	public function blackOutEnterPhase(){
		if(!$this->checkIfConnected()) return;
		$this->fightType = 0;
        $spawnBlock = $this->getPlayer()->getLineOfSight(2, 3, []);
        $spawnBlock = $spawnBlock[count($spawnBlock) -1];
		$this->getPlayer()->addEffect(
			Effect::getEffectById(Effect::BLINDNESS)->setDuration(30*20)->setAmplifier(4)->setVisible(false)
		);
		$this->getPlayer()->addEffect(
			Effect::getEffectById(Effect::SLOWNESS)->setDuration(30*20)->setAmplifier(99)->setVisible(false)
		);
		$this->getPlayer()->addEffect(
			Effect::getEffectById(Effect::NAUSEA)->setDuration(30*20)->setAmplifier(99)->setVisible(false)
		);
		$this->teleport(new Vector3($spawnBlock->x, $spawnBlock->y, $spawnBlock->z), abs($this->getPlayer()->getYaw() - 180));
		$this->removeEffect(Effect::INVISIBILITY);
	}
	
	/**
	 * Ends a sequence where the ghost blinds the player,
	 * slows him down (zooming effect w/ fov) and he appears invuulnerable 
	 * in front of the player.
	 * @return void
	 */
	public function blackOutExitPhase(){
		if(!$this->checkIfConnected()) return;
		$this->getPlayer()->removeEffect(Effect::BLINDNESS);
		$this->getPlayer()->removeEffect(Effect::NAUSEA);
		$this->getPlayer()->removeEffect(Effect::SLOWNESS);
	}
	
	/**
	 * Sequence where the ghost starts 
	 * to move the player randomly.
	 *
	 * @return void
	 */
	public function movePlayerRandomly(){
		if(!$this->checkIfConnected()) return;
		$this->fightType = 0;
		for($i = 0; $i < 10; $i++){
			$this->associatedPlayer->setMotion(new Vector3(rand() - rand(), 0, rand() - rand()));
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
		$this->fightType = 0;
		$this->teleport(new Vector3($this->getPlayer()->x, $this->getPlayer()->y + 7, $this->getPlayer()->z), $this->yaw == 0 ? 359 : $this->yaw - 1, 130);
		srand(hash("sha512", time()));
		foreach($this->getLevel()->getEntities() as $et){
			if($et instanceof \pocketmine\entity\FallingSand && $et->namedtag->getInt("SpawnTime") !== $null){
				$diffTime = time() - $et->namedtag->getInt("SpawnTime");
				if($diffTime < 4) $et->close();
			}
		}
		for($i = 0; $i < 3; $i++) {
			$rx = rand(0, 12) - 6;
			$rz = rand(0, 12) - 6;
			for($ry = 128; $this->getLevel()->getBlock($rx, $ry, $rz)->getId() !== 0 || $ry == 0; $ry--){} // Determining y from the higthest workable block
			$b = $this->getLevel()->getBlock($rx, $ry, $rz);
			// Creating falling sand block
			$nbt = Entity::createBaseNBT($b->asVector3()->add(0.5, 0, 0.5));
			$nbt->setInt("TileID", $b->getId());
			$nbt->setInt("SpawnTime", time());
			$nbt->setByte("Data", $b->getDamage());
			$nbt->getListTag("Motion")[1] = 3;
			$fall = Entity::createEntity("FallingSand", $this->getLevel(), $nbt);
			if($fall !== null){
				$fall->spawnToAll();
			}
		}
	}


	/**
	 * Check if the player is still connected to the server
	 *
	 * @return void
	 */
	public function checkIfConnected(){
		if(!$this->associatedPlayer->isOnline()){
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
			$uuid = $this->getUniqueId();
			$entityId = $this->getId();
			$pk = new AddPlayerPacket();
			$pk->uuid = $uuid;
			$pk->username = "";
			$pk->entityRuntimeId = $entityId;
			$pk->position = $this->asVector3();
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			$pk->item = $this->getInventory()->getItemInHand();
			$pk->metadata = $this->dataProperties;
			$pk->metadata[self::DATA_NAMETAG] = [self::DATA_TYPE_STRING, $this->getDisplayName($player)];
			$player->dataPacket($pk);
			$this->inventory->sendArmorContents($player);
			$player->getServer()->updatePlayerListData($uuid, $entityId, "The Ghost", $this->skinId, $this->skin, [$player]);
		 }
	}


	// Event listeners
	 /**
     * Check the damage to reduce it by 25%
     *
     * @param EntityDamageEvent $source
     */
    public function attack(EntityDamageEvent $source) {
		if($event instanceof EntityDamageByEntityEvent) {
			if($event->getDamager() instanceof Player && $event->getDamager()){
				$source->setDamage($source->getDamage() * 0.75);
			} else {
				$event->setCancelled(true);
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
		if(!$this->checkIfConnected()) return;
		$diffV3 = $this->associatedPlayer->asVector3()->subtract($this->asVector3());
		$distDiff = $diffV3->asVector3()->abs();
		$distDiff = $distDiff->x + $distDiff->z;
		if($this->distanceSquared($this->associatedPlayer) <= 1){
			$this->attackEntity($this->associatedPlayer);
		}
		if ($diff > 0) {
			$this->motionX = $this->getSpeed() * 0.15 * ($x / $diff);
			$this->motionZ = $this->getSpeed() * 0.15 * ($z / $diff);
			$this->yaw = rad2deg(-atan2($x / $diff, $z / $diff));
		}
		$this->pitch = $y == 0 ? 0 : rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));
	}

	/**
	 * Attacks an entity when the ghost is close enought from it.
	 *
	 * @param Entity $et
	 * @return void
	 */
	public function attackEntity(Entity $et){
		$ev = new EntityDamageByEntityEvent($this, $this->associatedPlayer, EntityDamageEvent::CAUSE_ENTITY_ATTACK
	/* Todo Calcule armor */);
		$et->attack($ev);
	}

	/**
	 * Gives some drops when the ghost die
	 *
	 * @return array
	 */
	public function getDrops() : array{
		return 
	}


}