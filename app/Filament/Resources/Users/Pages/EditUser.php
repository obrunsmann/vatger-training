<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);

        $record->update($data);

        if (isset($permissionIds)) {
            $record->permissions()->sync($permissionIds);
        }

        return $record;
    }
}