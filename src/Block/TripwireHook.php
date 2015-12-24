<?php

namespace MinecraftMapEditor\Block;

class TripwireHook extends \MinecraftMapEditor\Block
{
    use Traits\Create;

    const FACING_SOUTH = 0;
    const FACING_WEST = 1;
    const FACING_NORTH = 2;
    const FACING_EAST = 3;

    const NOT_CONNECTED = 0b0000;
    const CONNECTED = 0b0100;
    const ACTIVATED = 0b1000;

    /**
     * Get a tripwire hook facing the given way with the given state.
     *
     * @param int $facing One of the FACING_ class constants
     * @param int $state  Either TripwireHook::NOT_CONNECTED, TripwireHook::CONNECTED
     *                    or TripwireHook::ACTIVATED
     *
     * @throws \Exception
     */
    public function __construct($facing, $state)
    {
        $this->setBlockIDFor(Ref::TRIPWIRE_HOOK);

        $this->checkDataRefValidStartsWith($facing, 'FACING_', 'Invalid facing setting for tripwire hook');
        $this->checkInList(
            $state,
            [self::NOT_CONNECTED, self::CONNECTED, self::ACTIVATED],
            'Invalid state for tripwire hook'
        );

        $this->setBlockData($facing | $state);
    }
}
