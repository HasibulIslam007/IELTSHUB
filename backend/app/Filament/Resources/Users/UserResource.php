<?php
namespace App\Filament\Resources\Users;
use App\Models\User;
use App\Filament\Resources\Users\Pages\ManageUsers;
use Filament\Forms\Components\{TextInput,Textarea,Select,Toggle,DateTimePicker,Repeater,TagsInput,FileUpload,Hidden};
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\{TextColumn,IconColumn};
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\{Action,EditAction,CreateAction};
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use App\Services\{Audit,ContentService,AssessmentService};
use Filament\Notifications\Notification;

class UserResource extends Resource {
 protected static ?string $model = User::class;
 protected static ?string $recordTitleAttribute = 'name';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('name')->required()->maxLength(100), TextInput::make('email')->email()->required()->unique(ignoreRecord:true), Select::make('role')->options(['student'=>'Student','teacher'=>'Teacher','admin'=>'Administrator'])->required()->default('student'), Toggle::make('suspended'), TextInput::make('password')->password()->minLength(10)->required(fn(string $operation)=>$operation==='create')->dehydrated(fn($state)=>filled($state))])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('role')->badge(), IconColumn::make('suspended')->boolean(),TextColumn::make('deletion_requested_at')->dateTime(),TextColumn::make('created_at')->dateTime()])->filters([SelectFilter::make('role')->options(['student'=>'Student','teacher'=>'Teacher','admin'=>'Administrator'])])->recordActions([EditAction::make()->using(function(User $record,array $data): User { abort_if($record->id===auth()->id() && (($data['role']??'admin')!=='admin'||($data['suspended']??false)),422,'You cannot remove your own administrator access.');$record->forceFill($data)->save();return $record; })])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageUsers::route('/')]; }
 
}
