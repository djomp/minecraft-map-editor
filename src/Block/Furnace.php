<?php

namespace MinecraftMapEditor\Block;

class Furnace extends \MinecraftMapEditor\Block
{
    use Shared\Create;

    const NORTH = 2;
    const SOUTH = 3;
    const WEST = 4;
    const EAST = 5;

    /**
     * Get a furnace facing in the given direction.
     *
     * @param int $blockRef Which furnace to use
     * @param int $facing   The direction it faces; one of the class constants
     *
     * @throws \Exception
     */
    public function __construct($blockRef, $facing)
    {
        $block = $this->checkBlock($blockRef, Ref::getStartsWith('FURNACE'));

        $this->checkDataRefValidAll($facing, 'Invalid facing reference for furnace');

        parent::__construct($block[0], $facing);
    }
}
