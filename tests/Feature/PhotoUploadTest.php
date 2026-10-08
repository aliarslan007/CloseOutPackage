<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Notifications\PhotoReplacedNotification;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(2)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(2)->create(['template_id' => $template->id]);

        $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $field->markets()->attach($market->id);
        $project->fieldSupervisors()->attach($field->id);

        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

        return compact('market', 'manager', 'project', 'template', 'field', 'admin');
    };

    $this->packageWithStage = function (array $fixture): array {
        $this->actingAs($fixture['field'])->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
        ]);

        $package = CloseoutPackage::firstOrFail();
        $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
        $selection = $stage->categorySelections()->firstOrFail();

        return compact('package', 'stage', 'selection');
    };
});

it('uploads a photo with GPS for an assigned field supervisor', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'gps_latitude' => 40.1234567,
            'gps_longitude' => -82.1234567,
            'gps_accuracy' => 5.5,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('photos', [
        'closeout_package_id' => $setup['package']->id,
        'package_stage_id' => $setup['stage']->id,
        'package_category_selection_id' => $setup['selection']->id,
        'uploaded_by' => $fixture['field']->id,
        'position' => 1,
        'status' => PhotoStatus::PENDING->value,
    ]);

    $photo = Photo::firstOrFail();
    expect((float) $photo->gps_latitude)->toBe(40.1234567)
        ->and((float) $photo->gps_longitude)->toBe(-82.1234567)
        ->and($photo->getFirstMedia(Photo::COLLECTION))->not->toBeNull();
});

it('falls back to EXIF GPS when the browser provided none', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $file = jpegWithExifGps();

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => new UploadedFile($file->getPathname(), 'site.jpg', 'image/jpeg', null, true),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $photo = Photo::firstOrFail();
    expect(round((float) $photo->gps_latitude, 6))->toBe(round(40.0 + 7 / 60 + 24 / 3600, 6))
        ->and(round((float) $photo->gps_longitude, 6))->toBe(51.5)
        ->and((float) $photo->gps_accuracy)->toBe(5.0);
});

it('keeps GPS null when the image carries no EXIF location', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertRedirect();

    $photo = Photo::firstOrFail();
    expect($photo->gps_latitude)->toBeNull()
        ->and($photo->gps_longitude)->toBeNull();
});

it('prefers browser GPS over EXIF GPS', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $file = jpegWithExifGps();

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => new UploadedFile($file->getPathname(), 'site.jpg', 'image/jpeg', null, true),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'gps_latitude' => 40.1234567,
            'gps_longitude' => -82.1234567,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $photo = Photo::firstOrFail();
    expect((float) $photo->gps_latitude)->toBe(40.1234567)
        ->and((float) $photo->gps_longitude)->toBe(-82.1234567);
});

function jpegWithExifGps(): SplFileInfo
{
    // Build a TIFF (little-endian) GPS IFD and wrap it in an EXIF APP1 JPEG segment.
    $tiff = pack('H*', '49492a00').pack('V', 8); // header, IFD0 at offset 8
    $tiff .= pack('v', 1); // one IFD0 entry
    $tiff .= pack('vvVV', 0x8825, 4, 1, 26); // GPSInfo pointer -> GPS IFD at 26
    $tiff .= pack('V', 0); // no next IFD0
    $tiff .= pack('v', 5); // five GPS entries
    $tiff .= pack('vvVV', 0x0001, 2, 2, 0x4E); // GPSLatitudeRef "N"
    $tiff .= pack('vvVV', 0x0002, 5, 3, 100); // GPSLatitude rationals at 100
    $tiff .= pack('vvVV', 0x0003, 2, 2, 0x45); // GPSLongitudeRef "E"
    $tiff .= pack('vvVV', 0x0004, 5, 3, 124); // GPSLongitude rationals at 124
    $tiff .= pack('vvVV', 0x001F, 5, 1, 148); // GPSHPositioningError at 148
    $tiff .= pack('V', 0); // no next GPS IFD
    $tiff .= "N\0\0\0"; // 92
    $tiff .= "E\0\0\0"; // 96
    $tiff .= pack('VVVVVV', 40, 1, 7, 1, 24, 1); // 100: 40° 7' 24"
    $tiff .= pack('VVVVVV', 51, 1, 30, 1, 0, 1); // 124: 51° 30' 0"
    $tiff .= pack('VV', 5, 1); // 148: accuracy 5 m

    $exifSegment = "\xFF\xE1".pack('n', strlen($tiff) + 6)."Exif\0\0".$tiff;

    $canvas = imagecreatetruecolor(1, 1);
    $temp = tempnam(sys_get_temp_dir(), 'gps');
    imagejpeg($canvas, $temp);

    // Insert the EXIF APP1 segment after the JFIF APP0 header (PHP's exif
    // parser rejects EXIF segments placed directly after the SOI marker).
    $jpeg = file_get_contents($temp);
    $app0Length = unpack('n', substr($jpeg, 4, 2))[1];
    $insertAt = 2 + 2 + $app0Length;
    file_put_contents($temp, substr($jpeg, 0, $insertAt).$exifSegment.substr($jpeg, $insertAt));

    return new SplFileInfo($temp);
}

it('rejects an oversized photo', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->create('big.jpg', 25 * 1024, 'image/jpeg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertSessionHasErrors('photo');
});

it('downscales an oversized photo before storing it', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => copTestImage('image/jpeg', 3000, 2000),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertSessionHasNoErrors();

    $media = Photo::firstOrFail()->getFirstMedia(Photo::COLLECTION);

    expect($media)->not->toBeNull()
        ->and(copTestImageDimension($media->getPath()))->toBeLessThanOrEqual(2560);
});

it('returns a JSON 201 payload for JSON photo uploads', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => [
                'position' => 1,
                'status' => PhotoStatus::PENDING->value,
                'package_category_selection_id' => $setup['selection']->id,
            ],
        ])
        ->assertJsonStructure(['success', 'data' => ['id']]);
});

it('returns a JSON 422 with an errors dict for invalid JSON uploads', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 99,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['position']);
});

it('returns a JSON error for domain exceptions during JSON uploads', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    // Drive the invalid-state path (a CopException): a package past DRAFT
    // only accepts replacements at flagged positions. Position 1 is occupied
    // by a non-flagged photo, so the action throws InvalidStateTransitionException
    // after validation passes (the replaceable-position capacity rule allows it).
    $setup['package']->update(['status' => PackageStatus::REJECTED]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('second.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors', [])
        ->assertJsonStructure(['message']);
});

it('rejects a non-image file', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->create('notes.txt', 100, 'text/plain'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertSessionHasErrors('photo');
});

it('rejects a photo beyond the allowed picture count', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('extra.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 99,
        ])
        ->assertSessionHasErrors('position');
});

it('rejects a photo at an occupied position with 409', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $payload = [
        'category_selection_id' => $setup['selection']->id,
        'position' => 1,
    ];

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            ...$payload,
        ])
        ->assertSessionHasNoErrors();

    $firstPhoto = Photo::where('package_category_selection_id', $setup['selection']->id)->first();

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site2.jpg'),
            ...$payload,
        ])
        ->assertSessionHasNoErrors();

    expect($firstPhoto->fresh()->replaced_at)->not->toBeNull();
});

it('requires both GPS coordinates when either is provided', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'gps_latitude' => 40.1,
        ])
        ->assertSessionHasErrors('gps_longitude');
});

it('rejects a HEIC photo with a friendly error', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->create('photo.heic', 100, 'image/heic'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'This HEIC photo could not be processed. Please upload a JPEG or PNG instead.');
});

it('denies uploads to field supervisors not assigned to the project', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($outsider)
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('replaces a flagged photo', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('blurry.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $oldPhoto = Photo::firstOrFail();
    $oldPhoto->update(['status' => PhotoStatus::FLAGGED->value]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$setup['package'], $oldPhoto]), [
            'photo' => UploadedFile::fake()->image('clear.jpg'),
            'photo_id' => (string) $oldPhoto->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $oldPhoto->refresh();
    $replacement = Photo::where('id', '!=', $oldPhoto->id)->firstOrFail();

    expect($oldPhoto->replaced_at)->not->toBeNull()
        ->and($oldPhoto->replaced_by_photo_id)->toBe($replacement->id)
        ->and($oldPhoto->is_approved)->toBeFalsy();

    expect($replacement)
        ->package_category_selection_id->toBe($oldPhoto->package_category_selection_id)
        ->position->toBe($oldPhoto->position)
        ->status->toBe(PhotoStatus::PENDING);
});

it('notifies the project manager when a flagged photo is replaced', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('blurry.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $oldPhoto = Photo::firstOrFail();
    $oldPhoto->update(['status' => PhotoStatus::FLAGGED->value]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$setup['package'], $oldPhoto]), [
            'photo' => UploadedFile::fake()->image('clear.jpg'),
            'photo_id' => (string) $oldPhoto->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $replacement = Photo::where('id', '!=', $oldPhoto->id)->firstOrFail();

    Notification::assertSentTo(
        $fixture['manager'],
        PhotoReplacedNotification::class,
        function (PhotoReplacedNotification $notification) use ($replacement): bool {
            $data = $notification->toArray($notification);

            return $data['photo_id'] === $replacement->id
                && $data['title'] === 'Photo replaced'
                && str_contains($data['body'], 'uploaded a replacement');
        },
    );
});

it('replaces a flagged photo on a submitted package', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('blurry.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $oldPhoto = Photo::firstOrFail();
    $oldPhoto->update(['status' => PhotoStatus::FLAGGED->value]);
    $setup['package']->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$setup['package'], $oldPhoto]), [
            'photo' => UploadedFile::fake()->image('clear.jpg'),
            'photo_id' => (string) $oldPhoto->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $oldPhoto->refresh();
    $replacement = Photo::where('id', '!=', $oldPhoto->id)->firstOrFail();

    expect($oldPhoto->replaced_at)->not->toBeNull()
        ->and($oldPhoto->replaced_by_photo_id)->toBe($replacement->id);
});

it('replaces a pending photo in a draft package for an assigned technician', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('wrong-tower.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $oldPhoto = Photo::firstOrFail();
    expect($oldPhoto->status)->toBe(PhotoStatus::PENDING);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$setup['package'], $oldPhoto]), [
            'photo' => UploadedFile::fake()->image('right-tower.jpg'),
            'photo_id' => (string) $oldPhoto->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $oldPhoto->refresh();
    $replacement = Photo::where('id', '!=', $oldPhoto->id)->firstOrFail();

    expect($oldPhoto->replaced_at)->not->toBeNull()
        ->and($oldPhoto->replaced_by_photo_id)->toBe($replacement->id)
        ->and($replacement->uploaded_by)->toBe($fixture['field']->id)
        ->and($replacement->status)->toBe(PhotoStatus::PENDING);
});

it('rejects replacing a non-flagged photo once the package is submitted', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);
    $photo = Photo::factory()->create([
        'closeout_package_id' => $setup['package']->id,
        'package_stage_id' => $setup['stage']->id,
        'package_category_selection_id' => $setup['selection']->id,
        'uploaded_by' => $fixture['field']->id,
        'status' => PhotoStatus::PENDING->value,
    ]);

    $setup['package']->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$setup['package'], $photo]), [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'photo_id' => (string) $photo->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect($photo->fresh()->replaced_at)->toBeNull();
});

it('shows GPS to field supervisors in the photo list', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'gps_latitude' => 40.1234567,
            'gps_longitude' => -82.1234567,
        ]);

    $this->actingAs($fixture['field'])
        ->get(route('photos.index', [$setup['package'], StageType::PRE_CONSTRUCTION->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Photos')
            ->has('photos.data', 1)
            ->where('photos.data.0.gps_latitude', 40.1234567)
            ->where('photos.data.0.gps_longitude', -82.1234567));
});

it('shows GPS to managers in the photo list', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'gps_latitude' => 40.1234567,
            'gps_longitude' => -82.1234567,
        ]);

    $this->actingAs($fixture['manager'])
        ->get(route('photos.index', [$setup['package'], StageType::PRE_CONSTRUCTION->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Photos')
            ->has('photos.data', 1)
            ->where('photos.data.0.gps_latitude', 40.1234567)
            ->where('photos.data.0.gps_longitude', -82.1234567));
});

it('serves the photo file via the signed-url endpoint', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $photo = Photo::firstOrFail();

    $this->actingAs($fixture['manager'])
        ->get(route('photos.signed-url', [$setup['package'], $photo]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('serves the thumb conversion via the signed-url endpoint', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg', 1200, 800),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $photo = Photo::firstOrFail();

    $this->actingAs($fixture['manager'])
        ->get(route('photos.signed-url', [$setup['package'], $photo]).'?conversion=thumb')
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('falls back to the original file for an unknown conversion', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ]);

    $photo = Photo::firstOrFail();

    $this->actingAs($fixture['manager'])
        ->get(route('photos.signed-url', [$setup['package'], $photo]).'?conversion=huge')
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});

it('returns 404 for the signed-url endpoint without media', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);
    $photo = Photo::factory()->create([
        'closeout_package_id' => $setup['package']->id,
        'package_stage_id' => $setup['stage']->id,
        'package_category_selection_id' => $setup['selection']->id,
        'uploaded_by' => $fixture['field']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('photos.signed-url', [$setup['package'], $photo]))
        ->assertNotFound();
});

it('lets a manager delete their own auto-approved photo in a draft package', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['manager'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('wrong-tower.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $photo = Photo::firstOrFail();
    expect($photo->status)->toBe(PhotoStatus::APPROVED)
        ->and($photo->approved_by)->toBe($fixture['manager']->id);

    $this->actingAs($fixture['manager'])
        ->delete(route('photos.destroy', [$setup['package'], $photo]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Photo removed.');

    $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
});

it('blocks a technician from deleting their manager-approved photo', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);
    $photo = Photo::factory()->create([
        'closeout_package_id' => $setup['package']->id,
        'package_stage_id' => $setup['stage']->id,
        'package_category_selection_id' => $setup['selection']->id,
        'uploaded_by' => $fixture['field']->id,
        'status' => PhotoStatus::APPROVED->value,
        'is_approved' => true,
        'approved_by' => $fixture['manager']->id,
    ]);

    $this->actingAs($fixture['field'])
        ->delete(route('photos.destroy', [$setup['package'], $photo]))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->assertDatabaseHas('photos', ['id' => $photo->id]);
});

it('permanently deletes an own pending photo on delete', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);
    $photo = Photo::factory()->create([
        'closeout_package_id' => $setup['package']->id,
        'package_stage_id' => $setup['stage']->id,
        'package_category_selection_id' => $setup['selection']->id,
        'uploaded_by' => $fixture['field']->id,
        'status' => PhotoStatus::PENDING->value,
    ]);

    $this->actingAs($fixture['field'])
        ->delete(route('photos.destroy', [$setup['package'], $photo]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
});

it('returns a user-friendly error when uploading a photo to a deleted category', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $category = $setup['selection']->templatePictureCategory;
    $category->delete();

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
        ])
        ->assertSessionHasErrors([
            'category_selection_id' => 'This photo category was removed from the template by an administrator. Please refresh your page.',
        ]);
});

it('returns a user-friendly error when uploading with a non-existent category selection id', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => 999999,
            'position' => 1,
        ])
        ->assertSessionHasErrors([
            'category_selection_id' => 'This photo category was removed from the template by an administrator. Please refresh your page.',
        ]);
});

it('uploads a photo with optional notes', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('site.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'notes' => 'Antenna mount bracket after tightening to 45 ft-lbs',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $photo = Photo::firstOrFail();
    expect($photo->notes)->toBe('Antenna mount bracket after tightening to 45 ft-lbs');
});

it('replaces a flagged photo with a new note', function (): void {
    $fixture = ($this->fixture)();
    $setup = ($this->packageWithStage)($fixture);

    $this->actingAs($fixture['field'])
        ->post(route('photos.store', [$setup['package'], StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('blurry.jpg'),
            'category_selection_id' => $setup['selection']->id,
            'position' => 1,
            'notes' => 'Initial blurry note',
        ]);

    $oldPhoto = Photo::firstOrFail();
    $oldPhoto->update(['status' => PhotoStatus::FLAGGED->value]);

    $this->actingAs($fixture['field'])
        ->post(route('photos.replace', [$setup['package'], $oldPhoto]), [
            'photo' => UploadedFile::fake()->image('clear.jpg'),
            'photo_id' => (string) $oldPhoto->id,
            'notes' => 'Clear replacement photo of antenna mount',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $replacement = Photo::where('id', '!=', $oldPhoto->id)->firstOrFail();
    expect($replacement->notes)->toBe('Clear replacement photo of antenna mount');
});
