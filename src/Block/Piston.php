<?php

namespace MinecraftMapEditor\Block;

class Piston extends \MinecraftMapEditor\Block
{
    const UP = 0x1;
    const DOWN = 0x0;
    const NORTH = 0x2;
    const EAST = 0x5;
    const SOUTH = 0x3;
    const WEST = 0x4;

    /**
     * Get a piston. If extended, this will only do the piston body, not the head
     *
     * @param int $blockRef  Which piston
     * @param int $direction The direction the piston head is pointing; one of the class constants
     * @param bool $extended [optional] Is the piston extended? TODO CHANGE THIS TO A CONST VALUE
     *
     * @throws \Exception
     */
    public function __construct($blockRef, $direction, $extended = false)
    {
        $block = self::checkBlock($blockRef, [
            Ref::PISTON,
            Ref::PISTON_STICKY,
        ]);

        self::checkDataRefValidAll($direction, 'Invalid direction for piston');

        if ($extended) {
            $direction |= 0x8;
        }

        parent::__construct($block[0], $direction);
    }
}
