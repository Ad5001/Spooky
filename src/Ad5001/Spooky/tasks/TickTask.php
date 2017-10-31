<?php
namespace Ad5001\Spooky\tasks;

use pocketmine\Server;
use pocketmine\scheduler\PluginTask;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\entity\Effect;

use Ad5001\Spooky\Main;
use Ad5001\Spooky\entity\Ghost;



class TickTask extends PluginTask {
    protected static $ghosts = [];

    /**
     * Constructs the class
     *
     * @param Main $main
     */
    public function __construct(Main $main) {
        parent::__construct($main);
        $this->main = $main;
        $this->server = $main->getServer();
    }

    /** 
     * Tick tack...
     *
     * @param int $tick
     * @return void
     */
    public function onRun(int $tick) {
        foreach(self::$ghosts as $i => $g){
            self::$ghosts[$i]->currentSec += 0.1;
            switch(round(self::$ghosts[$i]->currentSec, 1)){
                case 48: // 0m48s
                self::$ghosts[$i]->getPlayer()->getLevel()->setTime(13000); // Set time to night
                break;
                case 64: // 1m04s
                self::$ghosts[$i]->spawnTo(self::$ghosts[$i]->getPlayer());
                self::$ghosts[$i]->scareEnterPhase();
                break;
                case 82: // 1m22s
                self::$ghosts[$i]->movePlayerRandomly();
                self::$ghosts[$i]->repeatFunc = "move";
                break;
                case 88: // 1m28s
                self::$ghosts[$i]->repeatFunc = null;
                break;
                case 95: // 1m35s
                self::$ghosts[$i]->destroyBlocksRandomly();
                self::$ghosts[$i]->repeatFunc = "blockdis";
                break;
                case 100: // 1m40s
                self::$ghosts[$i]->repeatFunc = null;
                self::$ghosts[$i]->scareEnterPhase();
                break;
                case 151: // 2m31s
                self::$ghosts[$i]->scareEnterPhase();
                break;
                case 168: // 2m48s
                self::$ghosts[$i]->movePlayerRandomly();
                self::$ghosts[$i]->repeatFunc = "move";
                break;
                case 176: // 2m56s
                self::$ghosts[$i]->destroyBlocksRandomly();
                self::$ghosts[$i]->repeatFunc = "blockdis";
                break;
                case 183: // 3m03s
                self::$ghosts[$i]->repeatFunc = null;
                break;
                case 197: // 3m17s
                self::$ghosts[$i]->scareEnterPhase();
                break;
                case 262: // 4m22
                self::$ghosts[$i]->getPlayer()->sendMessage("Mwahahahaha... Try being faster next time!");
                self::$ghosts[$i]->close();
                self::unregisterGhost(self::$ghosts[$i]);
                break;
                default:
                switch(self::$ghosts[$i]->repeatFunc){
                    case "move":
                    self::$ghosts[$i]->movePlayerRandomly();
                    break;
                    case "blockdis":
                    self::$ghosts[$i]->destroyBlocksRandomly();
                    break;
                }
                break;
            }
        }
        // Setting invisibility
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            if(isset($p->getInventory()->getItemInHand()->getNamedTag()->sneakInvisible) && $p->isSneaking()) {
                $p->addEffect(Effect::getEffect(Effect::INVISIBILITY)->setDuration(3)->setVisible(false));
            }
        }
    }
    
    /**
     * Registers a ghost to the class
     *
     * @param Ghost $g
     * @return void
     */
    public static function registerGhost(Ghost $g){
        $g->currentSec = 0.0;
        $g->repeatFunc = null;
        self::$ghosts[$g->getId()] = $g;
    }
        
    /**
     * Unregisters a ghost from the class
     *
     * @param Ghost $g
     * @return void
     */
    public static function unregisterGhost(Ghost $g){
        unset(self::$ghosts[$g->getId()]);
    }


}