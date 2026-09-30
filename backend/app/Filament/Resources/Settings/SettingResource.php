<?php
namespace App\Filament\Resources\Settings;
use App\Models\Setting;
use App\Filament\Resources\Settings\Pages\ManageSettings;
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

class SettingResource extends Resource {
 protected static ?string $model = Setting::class;
 protected static ?string $recordTitleAttribute = 'id';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([Select::make('key')->options(['brand'=>'Brand name','support_email'=>'Support email','homepage_intro'=>'Homepage introduction','help_content'=>'Help content','notification_note'=>'Notification information'])->required()->unique(ignoreRecord:true),Textarea::make('value')->required()->maxLength(5000)->helperText('Public text only. Never store credentials here.')])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('key')->searchable(),TextColumn::make('value')->limit(100),TextColumn::make('updated_at')->dateTime()])->filters([])->recordActions([EditAction::make()])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageSettings::route('/')]; }
 
}
