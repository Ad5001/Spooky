<?php
namespace Ad5001\Spooky\tasks;

use pocketmine\Server;
use pocketmine\scheduler\PluginTask;
use pocketmine\Player;

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
        }
    }
    
    /**
     * Registers a ghost to the class
     *
     * @param Ghost $g
     * @return void
     */
    public static function registerGhost(Ghost $g){
        $g->currentSec = 0;
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