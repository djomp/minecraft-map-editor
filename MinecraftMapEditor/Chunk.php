<?php

namespace MinecraftMapEditor;

class Chunk
{
    /** @var \Nbt\Node NBT tree for this chunk **/
    public $nbtNode;

    /** @var bool Flag if the chunk has been changed **/
    public $changed = false;

    /** @var \Nbt\Node Node for the sections tag **/
    private $sectionsTag;

    /** @var \Nbt\Node Node for the block entities tag **/
    private $blockEntitiesTag;

    /** @var \Nbt\Node[] Array of nodes for each section within the sections tag **/
    private $sectionsList;

    /** @var Array[] Array of cached section information for each section **/
    private $sectionParts = [];

    /** @var Int[] Array of affected zx coords **/
    private $affected = [];

    /** @var \Nbt\Node[] Array of Block Entity Information, keyed on co-ordinates **/
    private $blockEntities = [];

    /**
     * Initialise a chunk based on NBT data.
     *
     * @param string $nbtString The (raw) nbtString
     */
    public function __construct($nbtString)
    {
        if ($nbtString != null) {
            $this->nbtNode = (new \Nbt\Service())->readString($nbtString);
            // Cache the sections tag so we don't need to look for it every time
            $this->sectionsTag = $this->nbtNode->findChildByName('Sections');
            // Organise the block entities
            $this->blockEntitiesTag = $this->nbtNode->findChildByName('TileEntities');
            $this->readBlockEntities();
        }
    }

    /**
     * Read in the block entity data from the chunk.
     */
    public function readBlockEntities()
    {
        foreach ($this->blockEntitiesTag->getChildren() as $blockEntity) {
            // Pull the co-ordinates from the entry
            // These are absolute co-ordinates, not relative to the chunk
            $x = $blockEntity->findChildByName('x')->getValue();
            $y = $blockEntity->findChildByName('y')->getValue();
            $z = $blockEntity->findChildByName('z')->getValue();
            $coords = new Coords\BlockCoords($x, $y, $z);
            $this->blockEntities[$coords->toKey()] = $blockEntity;
        }
    }

    /**
     * Get the NBT string for this chunk.
     *
     * @return string
     */
    public function getNBTstring()
    {
        return (new \Nbt\Service())->writeString($this->nbtNode);
    }

    /**
     * Set a block in the world. Will overwrite a block if one exists at the co-ordinates.
     *
     * @param Coords\BlockCoords $blockCoords Co-ordinates of the block
     * @param array              $block       Information about the new block
     */
    public function setBlock($blockCoords, $block)
    {
        $chunkCoords = $blockCoords->toChunkCoords();
        $yRef = $chunkCoords->getSectionRef();

        // Get the block ID
        $blocks = $this->getSectionPart($yRef, 'Blocks');
        $blockRef = $chunkCoords->getSectionYZX();

        $blockIDList = $blocks->getValue();
        if ($block['blockID'] <= 255) {
            if ($blockIDList[$blockRef] != $block['blockID']) {
                $blockIDList[$blockRef] = $block['blockID'];
                $this->setChanged($blockCoords);
                $blocks->setValue($blockIDList);
            }
        } else {
            // No vanilla blocks above ID 255 yet (10th October 2015, 1.9 snapshots)
            trigger_error('Block ID larger than 255 requested. This is not supported yet.', E_ERROR);
        }

        // set block data
        $blockData = $this->getSectionPart($yRef, 'Data');
        $this->setNibbleIn($blockData, $blockCoords, $block['blockData']);

        // set block entity data
        $this->updateBlockEntity(
            $blockCoords,
            isset($block['blockEntity']) ? $block['blockEntity'] : null
        );
    }

    /**
     * Get information about a block.
     *
     * @param Coords\BlockCoords $blockCoords
     *
     * @return array
     */
    public function getBlock($blockCoords)
    {
        $chunkCoords = $blockCoords->toChunkCoords();

        // Get the block ID
        $blockID = $this->getBlockID($chunkCoords);

        // Block Data
        // Get the nibble for this block
        $thisBlockData = $this->getNibbleFrom(
            $this->getSectionPart($chunkCoords->getSectionRef(), 'Data')->getValue(),
            $chunkCoords->getSectionYZX()
        );

        // Block Entity
        $blockEntity = isset($this->blockEntities[$blockCoords->toKey()])
                ? $this->blockEntities[$blockCoords->toKey()]
                : null;

        return [
            'blockID' => $blockID,
            'blockData' => $thisBlockData,
            'blockEntity' => $blockEntity,
            ];
    }

    public function getBlockID($chunkCoords)
    {
        $yRef = $chunkCoords->getSectionRef();

        // check if there's an Add field
        $add = $this->getSectionPart($yRef, 'Add');
        if ($add !== false) {
            // No vanilla blocks above ID 255 yet (10th October 2015, 1.9 snapshots)
            trigger_error('This chunk has block IDs above 255. This is not supported yet.', E_ERROR);
        }

        return $this->getSectionPart($yRef, 'Blocks')->getValue()[$chunkCoords->getSectionYZX()];
    }

    /**
     * Get a specific tag from a section (which is then cached).
     *
     * @param int    $yRef
     * @param string $name
     *
     * @return \Nbt\Node
     */
    private function getSectionPart($yRef, $name)
    {
        if (!isset($this->sectionParts[$yRef][$name])) {
            $this->sectionParts[$yRef][$name] =
                $this->getSection($yRef)->findChildByName($name);
        }

        return $this->sectionParts[$yRef][$name];
    }

    /**
     * Get the correct section from within the Sections tag (based on Y index).
     *
     * @param \Nbt\Node $node
     * @param int       $yRef
     *
     * @return \Nbt\Node
     */
    private function getSection($yRef)
    {
        if (isset($this->sectionsList[$yRef])) {
            return $this->sectionsList[$yRef];
        }

        foreach ($this->sectionsTag->getChildren() as $childNode) {
            if ($childNode->findChildByName('Y')->getValue() == $yRef) {
                $this->sectionsList[$yRef] = $childNode;

                return $childNode;
            }
        }

        // If we didn't find one, we must create one

        // Doesn't need a name, it's part of a list
        $newY = \Nbt\Tag::tagCompound('', [
            \Nbt\Tag::tagByte('Y', $yRef),
            \Nbt\Tag::tagByteArray('Blocks',     array_fill(0, 4096, 0x0)),
            \Nbt\Tag::tagByteArray('Data',       array_fill(0, 2048, 0x0)),
            \Nbt\Tag::tagByteArray('BlockLight', array_fill(0, 2048, 0x0)),
            \Nbt\Tag::tagByteArray('SkyLight',   array_fill(0, 2048, 0x0)),
        ]);

        // Add it to the list
        $this->sectionsTag->addChild($newY);
        $this->sectionsList[$yRef] = $newY;

        return $newY;
    }

    /**
     * Get a nibble from an array of bytes.
     *
     * @param array $array
     * @param int   $blockRef
     *
     * @return binary (?)
     */
    private function getNibbleFrom($array, $blockRef)
    {
        $arrayRef = floor($blockRef / 2);

        return $blockRef % 2 == 0
            ? $array[$arrayRef] & 0x0F
            : ($array[$arrayRef] >> 4) & 0x0F; // the & 0x0F should be redundant?
    }

    /**
     * Set a nibble in an array to the given value.
     *
     * @param \Nbt\Node          $node
     * @param Coords\BlockCoords $blockCoords
     * @param int                $value
     */
    private function setNibbleIn($node, $blockCoords, $value)
    {
        $blockRef = $blockCoords->toChunkCoords()->getSectionYZX();
        // This function should check if it's changing anything, and call setChanged if it does

        $array = $node->getValue();
        $arrayRef = floor($blockRef / 2);

        // Save the original value to check if anything changes
        $origValue = $array[$arrayRef];

        // Get the current value, blocking out the value we want to copy in
        $curValue = $array[$arrayRef] & ($blockRef % 2 == 0 ? 0xF0 : 0x0F);
        // Add the nibble we want to the correct side of the byte
        $newValue = $curValue | ($blockRef % 2 == 0 ? $value : ($value << 4));
        // and set the value in the array
        $array[$arrayRef] = $newValue;

        // Check if we've change anything
        if ($newValue !== $origValue) {
            $this->setChanged($blockCoords);
        }

        $node->setValue($array);
    }

    /**
     * Do tasks required before saving.
     */
    public function prepareForSaving()
    {
        // Set Lightpopulated to zero to force a lighting update
        $this->nbtNode->findChildByName('LightPopulated')->setValue(0x00);
        // Set the last update time
        $this->nbtNode->findChildByName('LastUpdate')->setValue(time());
        // Update the height map
        $this->updateHeightMap();
        // Write the block entities back to the NBT tree
        $this->saveBlockEntities();
    }

    /**
     * Update the height map before saving.
     */
    private function updateHeightMap()
    {
        if (count($this->affected)) {
            // Get the keys for y sections, to work out the largest y value to work from
            $yRefs = [];
            foreach ($this->sectionsTag->getChildren() as $subSection) {
                $yRefs[] = $subSection->findChildByName('Y')->getValue();
            }

            // Get the current height map
            $heightMapTag = $this->nbtNode->findChildByName('HeightMap');
            $heightMapArray = $heightMapTag->getValue();

            // Step through each affected z-x pair
            foreach ($this->affected as $zxVal) {
                $zxRef = Coords\FlatCoordRef::fromZXval($zxVal);
                $heightMapArray[$zxVal] = 0;
                for ($y = (max($yRefs) * 16) + 15; $y >= 0; --$y) {
                    $block = $this->getBlockID(new Coords\ChunkCoords($zxRef->x, $y, $zxRef->z));
                    if ($block != 0x00) {
                        $heightMapArray[$zxVal] = $y;
                        break;
                    }
                }
            }

            // Write the height map back
            $heightMapTag->setValue($heightMapArray);
        }
    }

    /**
     * Mark that this chunk has been changed, and record the ZX value of the block changed.
     *
     * @param Coords\BlockCoords $coords
     */
    private function setChanged($coords)
    {
        $this->changed = true;

        // Set that this zx pair was changed, so we need to recalcuate the height map on it
        $this->affected[] = $coords->toChunkCoords()->getZX();
        $this->affected = array_unique($this->affected);
    }

    /**
     * Update the block entity list for this chunk.
     *
     * @param Coords\BlockCoords $blockCoords
     * @param \Nbt\Node|null     $blockEntity
     */
    public function updateBlockEntity($blockCoords, $blockEntity)
    {
        if ($blockEntity === null) {
            if (isset($this->blockEntities[$blockCoords->toKey()])) {
                unset($this->blockEntities[$blockCoords->toKey()]);
                $this->setChanged($blockCoords);
            }
        } else {
            $this->setChanged($blockCoords);
            // Add the co-ordinates to the block entity
            if ($blockEntity->findChildByName('x')) {
                $blockEntity->findChildByName('x')->setValue($blockCoords->x);
            } else {
                $blockEntity->addChild(\Nbt\Tag::tagInt('x', $blockCoords->x));
            }
            if ($blockEntity->findChildByName('y')) {
                $blockEntity->findChildByName('y')->setValue($blockCoords->y);
            } else {
                $blockEntity->addChild(\Nbt\Tag::tagInt('y', $blockCoords->y));
            }
            if ($blockEntity->findChildByName('z')) {
                $blockEntity->findChildByName('z')->setValue($blockCoords->z);
            } else {
                $blockEntity->addChild(\Nbt\Tag::tagInt('z', $blockCoords->z));
            }

            $this->blockEntities[$blockCoords->toKey()] = $blockEntity;
        }
    }

    /**
     * Save the block entities back to the tree.
     */
    public function saveBlockEntities()
    {
        // Probably easiest to reset the whole thing
        $this->blockEntitiesTag->removeAllChildren();

        if (count($this->blockEntities) == 0) {
            // When empty, lists have a zero payload type
            $this->blockEntitiesTag->setPayloadType(0x00);
        } else {
            $this->blockEntitiesTag->setPayloadType(\Nbt\Tag::TAG_COMPOUND);
            foreach ($this->blockEntities as $blockEntity) {
                $this->blockEntitiesTag->addChild($blockEntity);
            }
        }
    }
}
