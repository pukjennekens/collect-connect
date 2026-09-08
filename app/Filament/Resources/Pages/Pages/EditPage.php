<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['blocks'] = array_map(fn (array $block): array => isset($block['columns'])
            ? ['type' => 'legacy', 'data' => ['row' => $block]] : $block, $data['blocks'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['blocks'] = array_map(fn (array $block): array => ($block['type'] ?? null) === 'legacy'
            ? $block['data']['row'] : $block, $data['blocks'] ?? []);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
