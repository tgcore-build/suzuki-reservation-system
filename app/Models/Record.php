<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Record extends Model
{
    protected $fillable = [
        'customer_id', 'reservation_id', 'performed_on', 'menu', 'materials',
        'duration_minutes', 'memo', 'photo_before', 'photo_after',
    ];

    protected $casts = [
        'performed_on' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    // 写真のURL。S3のときは30分だけ有効な一時URLにする（写真を公開しないため）
    public function photoUrl(string $field): ?string
    {
        $path = $this->{$field};
        if (! $path) {
            return null;
        }

        $diskName = config('salon.photo_disk');
        $disk = Storage::disk($diskName);

        return
cat > tests/Feature/RecordTest.php <<'EOF'
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_with_photo_can_be_created_shown_and_deleted(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        $customer = Customer::create(['name' => 'A', 'phone' => '09011110000']);

        $this->post(route('records.store', $customer), [
            'performed_on' => '2026-10-05',
            'menu' => 'カラー',
            'photo_before' => UploadedFile::fake()->image('before.jpg'),
        ])->assertRedirect(route('customers.show', $customer));

        $record = $customer->records()->first();
        Storage::disk('public')->assertExists($record->photo_before);

        $this->get(route('customers.show', $customer))->assertOk()->assertSee('カラー');

        $path = $record->photo_before;
        $this->delete(route('records.destroy', $record))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('records', ['id' => $record->id]);
    }

    public function test_record_requires_menu_and_date(): void
    {
        $this->actingAs(User::factory()->create());
        $customer = Customer::create(['name' => 'A', 'phone' => '09011110000']);

        $this->post(route('records.store', $customer), [])
            ->assertSessionHasErrors(['performed_on', 'menu']);
    }
}
