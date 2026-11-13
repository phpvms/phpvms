<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Features\Addons\Support\BundledAddonLinker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('phpvms:addons-link-bundled')]
#[Description('Link each bundled addon (modules/<name>) into the addon root, storage/app/addons')]
class AddonsLinkBundled extends Command
{
    public function handle(BundledAddonLinker $linker): int
    {
        try {
            $results = $linker->link();
        } catch (RuntimeException $runtimeException) {
            $this->components->error($runtimeException->getMessage());

            return self::FAILURE;
        }

        foreach ($results as $name => $result) {
            $this->components->twoColumnDetail($name, $result);
        }

        return self::SUCCESS;
    }
}
