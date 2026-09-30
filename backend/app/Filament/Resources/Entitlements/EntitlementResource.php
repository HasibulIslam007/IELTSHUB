<?php
namespace App\Filament\Resources\Entitlements;
use App\Models\Entitlement;
use App\Filament\Resources\Entitlements\Pages\ManageEntitlements;
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

class EntitlementResource extends Resource {
 protected static ?string $model = Entitlement::class;
 protected static ?string $recordTitleAttribute = 'id';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return static::canViewAny(); }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([Select::make('user_id')->relationship('user','email')->searchable()->preload()->required(),Select::make('plan_id')->relationship('plan','name')->preload(),Select::make('exam_id')->relationship('exam','title')->searchable()->preload()->helperText('Leave empty to grant all premium tests.'),DateTimePicker::make('expires_at'),DateTimePicker::make('revoked_at'),Hidden::make('source')->default('manual'),Hidden::make('granted_by')->default(fn()=>auth()->id())])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('user.email')->searchable(),TextColumn::make('exam.title')->placeholder('All premium tests'),TextColumn::make('source')->badge(),TextColumn::make('expires_at')->dateTime(),TextColumn::make('revoked_at')->dateTime()])->filters([])->recordActions([EditAction::make()])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManageEntitlements::route('/')]; }
 
}
