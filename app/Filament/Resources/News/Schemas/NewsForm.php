<?php

namespace App\Filament\Resources\News\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class NewsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject')
                    ->label(__('filament.news_subject'))
                    ->string()
                    ->required()
                    ->columnSpanFull(),

                RichEditor::make('body')
                    ->label(__('filament.news_body'))
                    ->required()
                    ->columnSpanFull(),

                Toggle::make('send_notifications')
                    ->label(__('filament.news_send_notifications'))
                    ->dehydrated(false)
                    ->default(false)
                    ->onColor('success')
                    ->onIcon(Heroicon::CheckCircle)
                    ->offColor('danger')
                    ->offIcon(Heroicon::XCircle),
            ]);
    }
}
