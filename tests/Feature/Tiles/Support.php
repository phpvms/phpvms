<?php

declare(strict_types=1);

use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;

/**
 * Helpers shared by the dashboard tile tests. Required by each file, so a
 * parallel run that loads one file alone still has them.
 */
function registerTiles(Tile ...$tiles): void
{
    foreach ($tiles as $tile) {
        app(Tiles::class)->register($tile->id, fn (): Tile => $tile);
    }
}

function dashboardTile(string $id, bool $default = true, int $order = 100, string $zone = 'grid'): Tile
{
    return Tile::livewire($id, 'x::'.$id, $id, 'star', showInDashboard: true, zone: $zone, default: $default, order: $order);
}
