<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Minimal staff hierarchy used by Media Library tests: a market, its manager,
 * a project under it, one field supervisor assigned to the project and an
 * admin. Roles must already be seeded (beforeEach seeds RoleAndPermissionSeeder).
 *
 * @return array{market: Market, project: Project, manager: User, field: User, admin: User}
 */
function mediaStaffFixture(): array
{
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project->fieldSupervisors()->attach($field->id);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    return compact('market', 'project', 'manager', 'field', 'admin');
}

/**
 * Customer feedback loop fixture: manager-owned project, an APPROVED package
 * on it, and a customer with granted access. Roles must already be seeded.
 *
 * @return array{market: Market, project: Project, manager: User, template: Template, package: CloseoutPackage, customer: User, admin: User}
 */
function feedbackFixture(): array
{
    // Self-sufficient: other fixtures rely on test-level seeding; this one
    // guarantees roles exist regardless of which test file includes it.
    (new RoleAndPermissionSeeder)->run();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED->value,
    ]);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::query()->create([
        'user_id' => $customer->id,
        'project_id' => $project->id,
        'granted_by' => $manager->id,
    ]);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    return compact('market', 'project', 'manager', 'template', 'package', 'customer', 'admin');
}

/**
 * A feedback round on the fixture package. Shared by the feedback test files so
 * the round/note shape is written once.
 */
function packageRound(array $fixture, FeedbackRoundStatus $status): EntityFeedbackRound
{
    return EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $fixture['package']->id,
        'round_number' => 1,
        'status' => $status->value,
        'opened_by' => $fixture['manager']->id,
        'submitted_by' => $fixture['customer']->id,
        'submitted_at' => now(),
    ]);
}

/** A customer note on that round, from the fixture customer. */
function packageNote(EntityFeedbackRound $round, array $fixture, string $body = 'Question'): EntityFeedbackNote
{
    return $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $fixture['package']->id,
        'body' => $body,
        'created_by' => $fixture['customer']->id,
    ]);
}

/**
 * A real temp UploadedFile with exactly $bytes of content (Laravel's
 * UploadedFile::fake()->create() emits 0-byte files on this stack).
 */
function mediaByteFile(string $name, int $bytes): UploadedFile
{
    $temp = tempnam(sys_get_temp_dir(), 'media_file_');
    file_put_contents($temp, str_repeat('x', $bytes));

    return new UploadedFile($temp, $name, 'application/octet-stream', null, true);
}

/**
 * A DRAFT closeout package (with one category slot) on the fixture project.
 */
function mediaCategorySelection(array $fixture, int $pictureCount = 2): PackageCategorySelection
{
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'created_by' => $fixture['field']->id,
    ]);

    $stage = PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => StageType::CONSTRUCTION,
    ]);

    return PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'picture_count' => $pictureCount,
    ]);
}

function mediaAssign(array $fixture, int $assetId, int $selectionId): TestResponse
{
    return test()->actingAs($fixture['field'])
        ->postJson(route('media-library.assign'), [
            'media_asset_id' => $assetId,
            'category_selection_id' => $selectionId,
        ]);
}

/**
 * A real JPEG UploadedFile carrying EXIF GPS (N 40°7'24", E 51°30'0", 5 m)
 * so tests can exercise EXIF extraction.
 */
function mediaJpegWithExifGps(): UploadedFile
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

    // Insert the EXIF APP1 segment after the JFIF APP0 header.
    $jpeg = file_get_contents($temp);
    $app0Length = unpack('n', substr($jpeg, 4, 2))[1];
    $insertAt = 2 + 2 + $app0Length;
    file_put_contents($temp, substr($jpeg, 0, $insertAt).$exifSegment.substr($jpeg, $insertAt));

    return new UploadedFile($temp, 'gps.jpg', 'image/jpeg', null, true);
}

/**
 * A real, decodable image file on disk wrapped as an upload. Finfo decides the
 * mime type from content (not the passed client mime), matching a real request.
 * Used by the server-side compression tests.
 */
function copTestImage(string $mime, int $width, int $height): UploadedFile
{
    $extension = match ($mime) {
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'jpg',
    };

    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 90, 120, 200));

    $path = sys_get_temp_dir().'/cop_test_'.bin2hex(random_bytes(8)).'.'.$extension;

    match ($mime) {
        'image/png' => imagepng($image, $path),
        'image/webp' => imagewebp($image, $path, 85),
        'image/gif' => imagegif($image, $path),
        default => imagejpeg($image, $path, 85),
    };

    imagedestroy($image);

    return new UploadedFile($path, 'test.'.$extension, $mime, null, true);
}

/** Longest side in pixels of an image file at the given path. */
function copTestImageDimension(string $path): int
{
    /** @var array{int, int} $size */
    $size = getimagesize($path);

    return max($size[0], $size[1]);
}

/**
 * JSON-expecting request headers, optionally carrying the upload bearer token.
 *
 * @return array<string, string>
 */
function mediaJsonHeaders(?string $token = null): array
{
    $headers = ['Accept' => 'application/json'];

    if ($token !== null) {
        $headers['Authorization'] = 'Bearer '.$token;
    }

    return $headers;
}

/**
 * Drive a full chunked upload through initiate → chunks → finalize and return
 * the resulting READY asset (requires Roles seeded + Storage::fake('private')
 * and a sync queue, as the processing job runs inline).
 */
function mediaReadyAsset(array $fixture, int $parts = 2, ?UploadedFile $file = null): MediaAsset
{
    $image = $file ?? UploadedFile::fake()->image('photo.jpg', 1200, 800);
    $split = splitFileIntoChunks($image, $parts);

    $response = test()->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.initiate'), [
            'filename' => 'photo.jpg',
            'total_size' => $split['bytes'],
            'total_chunks' => $parts,
            'mime_type' => 'image/jpeg',
            'project_id' => $fixture['project']->id,
        ])
        ->assertOk();

    $asset = MediaAsset::query()->findOrFail($response->json('asset_id'));
    $token = $response->json('upload_token');

    foreach ($split['files'] as $index => $file) {
        test()->actingAs($fixture['field'])
            ->put(route('media-library.upload.chunk', $asset), [
                'chunk' => $file,
                'chunk_index' => $index,
                'total_chunks' => $parts,
            ], mediaJsonHeaders($token))
            ->assertOk();
    }

    test()->actingAs($fixture['field'])
        ->postJson(route('media-library.upload.finalize', $asset), [], mediaJsonHeaders($token))
        ->assertOk();

    return $asset->refresh();
}

/**
 * Split an uploaded file's bytes into $parts ordered UploadedFiles so a
 * chunked upload can be reassembled back into the exact original file.
 *
 * @return array{files: list<UploadedFile>, bytes: int}
 */
function splitFileIntoChunks(UploadedFile $uploaded, int $parts): array
{
    /** @var string $contents */
    $contents = file_get_contents((string) $uploaded->getRealPath());
    $size = strlen($contents);

    if ($parts < 1 || $size === 0) {
        throw new InvalidArgumentException('Cannot split an empty file.');
    }

    $base = intdiv($size, $parts);
    $remainder = $size % $parts;

    $files = [];
    $offset = 0;

    for ($i = 0; $i < $parts; $i++) {
        $length = $base + ($i < $remainder ? 1 : 0);
        $temp = tempnam(sys_get_temp_dir(), 'media_chunk_');
        file_put_contents($temp, substr($contents, $offset, $length));
        $files[] = new UploadedFile($temp, "chunk-{$i}.bin", 'application/octet-stream', null, true);
        $offset += $length;
    }

    return ['files' => $files, 'bytes' => $size];
}
