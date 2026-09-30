<?php
namespace App\Filament\Resources\AuditEvents;
use App\Models\AuditEvent;
use App\Filament\Resources\AuditEvents\Pages\ManageAuditEvents;
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

class AuditEventResource extends Resource {
 protected static ?string $model = AuditEvent::class;
 protected static ?string $recordTitleAttribute = 'id';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return false; }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('actor_id'),TextColumn::make('action')->searchable(),TextColumn::make('subject_type'),TextColumn::make('subject_id'),TextColumn::make('created_at')->dateTime()])->filters([])->recordActions([])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageAuditEvents::route('/')]; }
 
}
