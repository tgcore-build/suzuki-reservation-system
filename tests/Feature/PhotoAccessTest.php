<?php

namespace Tests\Feature;

use App\Mail\CustomerLoginCode;
use App\Models\Customer;
use App\Models\Record;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Customer $customer;
    private Record $record;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();

        $this->customer = Customer::create(['name' => 'テスト花子', 'phone' => '09011112222', 'birthday' => '1990-04-01', 'email' => 'hanako@example.com']);

        $this->actingAs(User::factory()->create());
        $this->post(route('records.store', $this->customer), [
            'performed_on' => '2026-10-05', 'menu' => 'カラー',
            'photo_before' => UploadedFile::fake()->createWithContent('before.png', base64_decode(self::PNG)),
        ])->assertRedirect();
        $this->record = $this->customer->records()->first();
        auth()->logout();
    }

    private function loginAsCustomer(): void
    {
        $this->post('/my/login', ['phone' => '09011112222', 'birthday' => '1990-04-01']);
        $this->post('/my/verify/send');
        $code = null;
        Mail::assertSent(CustomerLoginCode::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->post('/my/verify', ['code' => $code]);
    }

    public function test_photo_is_saved_in_private_disk_not_public(): void
    {
        Storage::disk('local')->assertExists($this->record->photo_before);
        Storage::disk('public')->assertMissing($this->record->photo_before);
        $this->assertSame(route('records.photo', [$this->record, 'photo_before']), $this->record->photoUrl('photo_before'));
    }

    public function test_guest_cannot_see_photo(): void
    {
        $this->get(route('records.photo', [$this->record, 'photo_before']))->assertRedirect('/login');
        $this->get(route('my.records.photo', [$this->record, 'photo_before']))->assertForbidden();
    }

    public function test_admin_can_see_photo(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('records.photo', [$this->record, 'photo_before']))->assertOk();
        $this->assertSame(base64_decode(self::PNG), $response->streamedContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        $this->get(route('records.photo', [$this->record, 'photo_after']))->assertNotFound(); // 写真がない欄
        $this->get("/records/{$this->record->id}/photo/memo")->assertNotFound();           // 写真以外の欄
    }

    public function test_only_the_customer_can_see_own_photo(): void
    {
        // 1段階目（電話番号＋生年月日）だけでは見られない
        $this->post('/my/login', ['phone' => '09011112222', 'birthday' => '1990-04-01']);
        $this->get(route('my.records.photo', [$this->record, 'photo_before']))->assertForbidden();

        $this->loginAsCustomer();
        $this->get(route('my.records.photo', [$this->record, 'photo_before']))->assertOk();
        $this->get('/my')->assertSee(route('my.records.photo', [$this->record, 'photo_before']), false);

        // 他人の記録の写真は見られない
        $other = Customer::create(['name' => '別の人', 'phone' => '08099998888']);
        $othersRecord = $other->records()->create(['performed_on' => '2026-10-01', 'menu' => 'カット', 'photo_before' => $this->record->photo_before]);
        $this->get(route('my.records.photo', [$othersRecord, 'photo_before']))->assertForbidden();
    }

    public function test_s3_uses_30_minute_temporary_url(): void
    {
        config(['salon.photo_disk' => 's3', 'filesystems.disks.s3.driver' => 's3']);
        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')->once()
            ->withArgs(fn ($path, $expires) => $path === $this->record->photo_before && abs($expires->diffInMinutes(now(), true) - 30) < 1)
            ->andReturn('https://example-bucket.s3.amazonaws.com/signed');
        Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

        $this->assertSame('https://example-bucket.s3.amazonaws.com/signed', $this->record->photoUrl('photo_before'));
    }

    public function test_move_old_public_photos_to_private(): void
    {
        $old = $this->customer->records()->create(['performed_on' => '2026-09-01', 'menu' => 'カット', 'photo_before' => 'records/old.png']);
        Storage::disk('public')->put('records/old.png', 'old-photo');

        $this->artisan('photos:move-to-private --dry-run')->assertSuccessful();
        Storage::disk('public')->assertExists('records/old.png');

        $this->artisan('photos:move-to-private')->assertSuccessful();
        Storage::disk('public')->assertMissing('records/old.png');
        $this->assertSame('old-photo', Storage::disk('local')->get('records/old.png'));
    }
}
