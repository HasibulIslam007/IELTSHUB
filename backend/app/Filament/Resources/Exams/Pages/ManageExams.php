<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExams extends ManageRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Create test'),
            \Filament\Actions\Action::make('import')->label('Import / validate JSON')->schema([
                \Filament\Forms\Components\Textarea::make('json')->label('Complete tests JSON array')->required()->rows(14),
                \Filament\Forms\Components\Toggle::make('commit')->label('Commit only if every row is valid')->default(false),
            ])->action(function(array $data){try{$rows=json_decode($data['json'],true,512,JSON_THROW_ON_ERROR);}catch(\JsonException $e){throw \Illuminate\Validation\ValidationException::withMessages(['json'=>'Invalid JSON: '.$e->getMessage()]);}if(!is_array($rows)||!array_is_list($rows)||count($rows)>50){throw \Illuminate\Validation\ValidationException::withMessages(['json'=>'Provide an array of at most 50 tests.']);}$result=app(\App\Services\ContentService::class)->import($rows,auth()->user(),$data['commit']);if($result['errors']){throw \Illuminate\Validation\ValidationException::withMessages(['json'=>json_encode($result['errors'])]);}\Filament\Notifications\Notification::make()->title($result['committed']?'Import committed':'Dry run passed: '.$result['count'].' valid tests')->success()->send();}),
            \Filament\Actions\Action::make('template')->label('CSV template')->url('/admin/templates/questions.csv'),
        ];
    }
}
