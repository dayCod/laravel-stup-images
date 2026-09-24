<?php

declare(strict_types=1);

use Daycode\StupImage\Concerns\HasStupImages;
use Daycode\StupImage\Exceptions\DisallowedMimeTypeException;
use Daycode\StupImage\Facades\StupImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');

    Schema::create('profiles', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('avatar')->nullable();
        $table->string('cover_photo')->nullable();
        $table->softDeletes();
    });
});

it('stores an uploaded file assigned to an image attribute', function (): void {
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);

    expect($profile->avatar)->toBeString()->toStartWith('avatars/')
        ->and($profile->fresh()->avatar)->toBe($profile->avatar);
    Storage::disk('local')->assertExists($profile->avatar);
});

it('applies the attribute options', function (): void {
    $profile = Profile::create(['cover_photo' => UploadedFile::fake()->image('a.jpg', 1600, 900)]);

    expect($profile->cover_photo)->toStartWith('covers/')->toEndWith('.jpg');
    Storage::disk('public')->assertExists([$profile->cover_photo, StupImage::variantPath($profile->cover_photo, 'thumbnail')]);
    expect(getimagesizefromstring((string) Storage::disk('public')->get($profile->cover_photo))[0])->toBe(800);
});

it('deletes the old image after replacing it', function (): void {
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $profile->avatar;

    $profile->update(['avatar' => UploadedFile::fake()->image('b.jpg')]);

    expect($profile->avatar)->not->toBe($old);
    Storage::disk('local')->assertMissing($old);
    Storage::disk('local')->assertExists($profile->avatar);
});

it('deletes old variants after replacing an image', function (): void {
    $profile = Profile::create(['cover_photo' => UploadedFile::fake()->image('a.jpg')]);
    $old = $profile->cover_photo;

    $profile->update(['cover_photo' => UploadedFile::fake()->image('b.jpg')]);

    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing([
        $profile->cover_photo,
        StupImage::variantPath($profile->cover_photo, 'thumbnail'),
    ]);
    Storage::disk('public')->assertMissing($old);
});

it('keeps the old image when the new upload is invalid', function (): void {
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $profile->avatar;

    expect(fn () => $profile->update(['avatar' => UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]))
        ->toThrow(DisallowedMimeTypeException::class);

    expect($profile->fresh()->avatar)->toBe($old);
    Storage::disk('local')->assertExists($old);
});

it('removes images stored by the same save when another attribute fails', function (): void {
    expect(fn () => Profile::create([
        'avatar' => UploadedFile::fake()->image('a.jpg'),
        'cover_photo' => UploadedFile::fake()->create('b.pdf', 1, 'application/pdf'),
    ]))->toThrow(DisallowedMimeTypeException::class);

    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Profile::count())->toBe(0);
});

it('deletes the image when the attribute is cleared', function (?string $value): void {
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $old = $profile->avatar;

    $profile->update(['avatar' => $value]);

    Storage::disk('local')->assertMissing($old);
})->with([null, '']);

it('leaves images alone when other attributes change', function (): void {
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);

    $profile->update(['name' => 'Jane']);

    Storage::disk('local')->assertExists($profile->avatar);
});

it('allows a plain path to be assigned', function (): void {
    Storage::disk('local')->put('avatars/existing.jpg', 'x');

    $profile = Profile::create(['avatar' => 'avatars/existing.jpg']);

    expect($profile->avatar)->toBe('avatars/existing.jpg');
});

it('deletes the images when the model is deleted', function (): void {
    $profile = PlainProfile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $avatar = $profile->avatar;

    $profile->delete();

    Storage::disk('local')->assertMissing($avatar);
});

it('keeps the images when the model is soft deleted', function (): void {
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);

    $profile->delete();
    Storage::disk('local')->assertExists($profile->avatar);

    $profile->forceDelete();
    Storage::disk('local')->assertMissing($profile->avatar);
});

it('does not fail the save when an old image cannot be deleted', function (): void {
    Log::spy();
    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);
    Storage::set('local', brokenDisk(delete: false));
    Storage::disk('local')->put($profile->avatar, 'x');

    $profile->update(['avatar' => UploadedFile::fake()->image('b.jpg')]);

    expect($profile->fresh()->avatar)->toBe($profile->avatar);
    Log::shouldHaveReceived('error')->once();
});

it('generates urls', function (): void {
    $profile = Profile::create([
        'avatar' => UploadedFile::fake()->image('a.jpg'),
        'cover_photo' => UploadedFile::fake()->image('b.jpg'),
    ]);

    expect($profile->avatarUrl())->toBe(Storage::disk('local')->url($profile->avatar))
        ->and($profile->coverPhotoUrl())->toBe(Storage::disk('public')->url($profile->cover_photo))
        ->and($profile->coverPhotoUrl('thumbnail'))->toBe(Storage::disk('public')->url(StupImage::variantPath($profile->cover_photo, 'thumbnail')))
        ->and($profile->stupImageUrl('avatar'))->toBe($profile->avatarUrl())
        ->and((new Profile)->avatarUrl())->toBeNull();
});

it('rejects a non-string variant in dynamic url calls', function (): void {
    (new Profile(['avatar' => 'a.jpg']))->avatarUrl(['thumbnail']);
})->throws(InvalidArgumentException::class, 'avatarUrl()');

it('forwards other dynamic calls to the model', function (): void {
    Profile::create(['name' => 'Jane']);

    expect((new Profile)->where('name', 'Jane')->count())->toBe(1)
        ->and(fn () => (new Profile)->nameUrl())->toThrow(BadMethodCallException::class);
});

it('normalizes the attribute definitions', function (): void {
    expect((new Profile)->stupImageAttributes())->toBe([
        'avatar' => ['directory' => 'avatars'],
        'cover_photo' => ['directory' => 'covers', 'disk' => 'public', 'preset' => 'medium', 'variants' => ['thumbnail']],
    ])->and((new NoImages)->stupImageAttributes())->toBe([]);
});

it('works with the fake', function (): void {
    $fake = StupImage::fake();

    $profile = Profile::create(['avatar' => UploadedFile::fake()->image('a.jpg')]);
    $profile->update(['avatar' => UploadedFile::fake()->image('b.jpg')]);

    $fake->assertStoredCount(2)->assertStored($profile->avatar);
});

class Profile extends Model
{
    use HasStupImages;
    use SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    protected array $stupImages = [
        'avatar' => 'avatars',
        'cover_photo' => ['directory' => 'covers', 'disk' => 'public', 'preset' => 'medium', 'variants' => ['thumbnail']],
    ];
}

class PlainProfile extends Model
{
    use HasStupImages;

    public $timestamps = false;

    protected $table = 'profiles';

    protected $guarded = [];

    protected array $stupImages = ['avatar' => 'avatars'];
}

class NoImages extends Model
{
    use HasStupImages;
}
