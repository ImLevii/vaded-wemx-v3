<?php

namespace Tests\Feature;

use App\Http\Requests\UploadImagesRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.installed' => true, 'app.license_key' => 'WMX-TESTING-KEY']);
        Cache::put('lcs_checked_at', now(), 21600);
        Storage::fake('images');
        $this->withSession(['admin_reauthenticated_at' => now()->toDateTimeString()]);
    }

    public function test_admin_can_view_the_empty_library_and_upload_controls(): void
    {
        $this->signInAdmin();
        $this->get(route('admin.images.index'))->assertOk()
            ->assertSee('Image Library')->assertSee('Your library starts here')
            ->assertSee('data-dropzone', false)->assertSee('data-image-search', false)
            ->assertSee('name="images[]"', false)->assertSee('image-library.js');
    }

    public function test_gallery_encodes_urls_and_only_lists_supported_images(): void
    {
        $this->signInAdmin();
        Storage::disk('images')->put("quote' #.png", 'image');
        Storage::disk('images')->put('artwork.svg', '<svg></svg>');
        Storage::disk('images')->put('notes.txt', 'not an image');
        $this->get(route('admin.images.index'))->assertOk()
            ->assertSee('quote%27%20%23.png', false)->assertSee('artwork.svg')
            ->assertDontSee('notes.txt')->assertDontSee('navigator.clipboard.writeText', false)
            ->assertSee('loading="lazy"', false);
    }

    public function test_single_upload_uses_a_safe_name_and_returns_metadata(): void
    {
        $this->signInAdmin();
        $response = $this->postJson(route('admin.images.upload'), [
            'image' => UploadedFile::fake()->image('Original Logo.png'),
            'file_name' => 'Summer Badge.png',
        ])->assertCreated()->assertJsonPath('message', 'Image uploaded successfully.')
            ->assertJsonPath('images.0.format', 'PNG');
        $name = $response->json('images.0.name');
        $this->assertMatchesRegularExpression('/^summer-badge-[a-z0-9]{26}\.png$/', $name);
        $this->assertStringEndsWith('/assets/common/img/'.$name, $response->json('images.0.url'));
        Storage::disk('images')->assertExists($name);
    }

    public function test_batch_upload_stores_every_file_without_replacing_existing_assets(): void
    {
        $this->signInAdmin();
        Storage::disk('images')->put('logo.png', 'existing branding');
        $response = $this->postJson(route('admin.images.upload'), ['images' => [
            UploadedFile::fake()->image('logo.png'), UploadedFile::fake()->image('logo.png'),
        ]])->assertCreated()->assertJsonCount(2, 'images');
        $this->assertNotSame($response->json('images.0.name'), $response->json('images.1.name'));
        $this->assertSame('existing branding', Storage::disk('images')->get('logo.png'));
        Storage::disk('images')->assertCount('', 3);
    }

    public function test_standard_form_redirects_with_success_feedback(): void
    {
        $this->signInAdmin();
        $this->post(route('admin.images.upload'), ['images' => [UploadedFile::fake()->image('banner.jpg')]])
            ->assertRedirect(route('admin.images.index'))->assertSessionHas('success', 'Image uploaded successfully.');
        Storage::disk('images')->assertCount('', 1);
    }

    public function test_missing_corrupt_and_oversized_images_are_rejected(): void
    {
        $this->signInAdmin();
        $this->postJson(route('admin.images.upload'), [])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->postJson(route('admin.images.upload'), ['images' => [UploadedFile::fake()->createWithContent('fake.png', 'not an image')]])
            ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->postJson(route('admin.images.upload'), ['image' => UploadedFile::fake()->image('large.png')->size(UploadImagesRequest::maximumSizeKilobytes() + 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        Storage::disk('images')->assertDirectoryEmpty('');
    }

    public function test_svg_and_executable_file_extensions_are_rejected(): void
    {
        $this->signInAdmin();
        $this->postJson(route('admin.images.upload'), ['images' => [UploadedFile::fake()->createWithContent('script.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]])
            ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        $this->postJson(route('admin.images.upload'), ['images' => [UploadedFile::fake()->image('image.php')]])
            ->assertUnprocessable()->assertJsonValidationErrors('images.0');
        Storage::disk('images')->assertDirectoryEmpty('');
    }

    public function test_invalid_batches_and_custom_names_do_not_store_files(): void
    {
        $this->signInAdmin();
        $files = array_map(fn (int $index): UploadedFile => UploadedFile::fake()->image('image-'.$index.'.png'), range(1, config('images.max_files') + 1));
        $this->postJson(route('admin.images.upload'), ['images' => $files])->assertUnprocessable()->assertJsonValidationErrors('images');
        $this->postJson(route('admin.images.upload'), ['images' => array_slice($files, 0, 2), 'file_name' => 'batch'])
            ->assertUnprocessable()->assertJsonValidationErrors('file_name');
        $this->postJson(route('admin.images.upload'), ['image' => UploadedFile::fake()->image('valid.png'), 'file_name' => '!!!'])
            ->assertUnprocessable()->assertJsonValidationErrors('file_name');
        Storage::disk('images')->assertDirectoryEmpty('');
    }

    public function test_form_validation_errors_are_visible(): void
    {
        $this->signInAdmin();
        $this->from(route('admin.images.index'))->post(route('admin.images.upload'), [])->assertSessionHasErrors('images');
        $this->get(route('admin.images.index'))->assertOk()->assertSee('Choose at least one image to upload.');
    }

    public function test_unrelated_staff_cannot_view_or_upload_images(): void
    {
        $this->signInStaff('admin.dashboard');
        $this->get(route('admin.images.index'))->assertForbidden();
        $this->postJson(route('admin.images.upload'), ['image' => UploadedFile::fake()->image('unauthorized.png')])->assertForbidden();
        Storage::disk('images')->assertDirectoryEmpty('');
    }

    public function test_catalog_editors_retain_access_to_the_image_picker(): void
    {
        $this->signInStaff('admin.categories.edit');
        $this->get(route('admin.images.index'))->assertOk();
        $this->postJson(route('admin.images.upload'), ['image' => UploadedFile::fake()->image('category.png')])->assertCreated();
        Storage::disk('images')->assertCount('', 1);
    }

    public function test_guests_cannot_upload_images(): void
    {
        $this->postJson(route('admin.images.upload'), ['image' => UploadedFile::fake()->image('guest.png')])->assertUnauthorized();
        Storage::disk('images')->assertDirectoryEmpty('');
    }

    private function signInAdmin(): void
    {
        $this->actingAs(User::factory()->create(['id' => 1, 'status' => 'active', 'language' => 'en']));
    }

    private function signInStaff(string $permission): void
    {
        $staff = User::factory()->create(['id' => 2, 'status' => 'active', 'language' => 'en']);
        $role = Role::query()->create(['name' => 'Image editor', 'super_admin' => false]);
        $role->permissions()->create(['permission' => $permission]);
        $staff->roles()->create(['role_id' => $role->id]);
        $this->actingAs($staff);
    }
}
