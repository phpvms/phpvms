<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthConnections\Pages;

use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use App\Filament\Actions\Drawer;
use App\Filament\Resources\OAuthConnections\OAuthConnectionResource;
use App\Filament\Resources\OAuthConnections\Schemas\OAuthConnectionForm;
use App\Models\OAuthConnection;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Override;

class ManageOAuthConnections extends ManageRecords
{
    protected static string $resource = OAuthConnectionResource::class;

    #[Override]
    public function getSubheading(): ?string
    {
        $providers = collect(app(SocialiteProviderRegistry::class)->all())
            ->filter(fn (array $definition): bool => app(SocialiteProviderRegistry::class)->isInstalled($definition['key']))
            ->pluck('label')
            ->join(', ');

        if ($providers === '') {
            return __('social_login.no_providers_installed');
        }

        return __('social_login.installed_providers', ['providers' => $providers]);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Drawer::configure(
                CreateAction::make()
                    ->label(__('social_login.create_action'))
                    ->icon(Phosphor::PlusCircleLight)
                    ->using(function (array $data): Model {
                        /** @var OAuthConnection $connection */
                        $connection = app(OAuthConnectionService::class)->create(
                            OAuthConnectionForm::prepareData($data),
                        );

                        return $connection;
                    }),
                OAuthConnectionForm::fields(),
            ),
        ];
    }
}
