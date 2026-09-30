<?php
namespace App\Filament\Resources\Payments;
use App\Models\Payment;
use App\Filament\Resources\Payments\Pages\ManagePayments;
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

class PaymentResource extends Resource {
 protected static ?string $model = Payment::class;
 protected static ?string $recordTitleAttribute = 'id';
 public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && !auth()->user()?->suspended; }
 public static function canCreate(): bool { return false; }
 public static function canEdit(Model $record): bool { return static::canViewAny(); }
 public static function canDelete(Model $record): bool { return false; }
 public static function canDeleteAny(): bool { return false; }

 public static function form(Schema $schema): Schema { return $schema->components([])->columns(2); }
 public static function table(Table $table): Table { return $table->columns([TextColumn::make('provider'),TextColumn::make('event_id'),TextColumn::make('status')->badge(),TextColumn::make('amount_minor'),TextColumn::make('currency'),TextColumn::make('created_at')->dateTime()])->filters([])->recordActions([])->defaultSort('id','desc'); }
 public static function getPages(): array { return ['index'=>ManagePayments::route('/')]; }
 
}
