<?php
namespace App\Filament\Resources\Contacts;
use App\Models\Contact;
use App\Filament\Resources\Contacts\Pages\ManageContacts;
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

class ContactResource extends Resource {
 protected static ?string $model = Contact::class;
 protected static ?string $recordTitleAttribute = 'id';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return false; }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([TextInput::make('name')->disabled(),TextInput::make('email')->disabled(),Textarea::make('message')->disabled()->rows(6)->columnSpanFull(),TextInput::make('delivery_status')->disabled(),Select::make('status')->options(['new'=>'New','in_progress'=>'In progress','resolved'=>'Resolved'])->required()])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('name')->searchable(),TextColumn::make('email')->searchable(),TextColumn::make('message')->limit(50),TextColumn::make('delivery_status')->badge(),TextColumn::make('status')->badge()])->filters([])->recordActions([EditAction::make()])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageContacts::route('/')]; }
 
}
