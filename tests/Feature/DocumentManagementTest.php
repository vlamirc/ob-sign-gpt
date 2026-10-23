<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_create_a_private_pdf_draft(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('contrato.pdf', "%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF");
        Livewire::actingAs($user)->test('pages::documents.create')->set('title', 'Contrato de teste')->set('pdf', $file)
            ->set('signers', [['name' => 'Maria', 'email' => 'MARIA@example.test']])->call('save')->assertHasNoErrors();
        $document = Document::sole();
        $this->assertSame($user->id, $document->user_id);
        $this->assertSame('draft', $document->status);
        $this->assertSame('maria@example.test', $document->signers()->sole()->email);
        Storage::disk('local')->assertExists($document->path);
        $this->assertSame(hash('sha256', Storage::disk('local')->get($document->path)), $document->sha256);
        $this->assertSame(1, $document->events()->count());
    }

    public function test_cross_account_document_access_and_actions_are_denied(): void
    {
        $document = Document::factory()->create();
        $this->actingAs(User::factory()->create());
        $this->get(route('documents.show', $document))->assertNotFound();
        $this->get(route('documents.original', $document))->assertNotFound();
        $this->get(route('documents.evidence', $document))->assertNotFound();
        $this->get(route('dashboard'))->assertOk()->assertDontSee($document->title);
        Livewire::test('pages::documents.show', ['document' => $document])->assertNotFound();
    }

    public function test_non_pdf_and_duplicate_signers_are_rejected(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test('pages::documents.create')->set('title', 'Teste')
            ->set('pdf', UploadedFile::fake()->createWithContent('bad.pdf', 'plain text')->mimeType('text/plain'))
            ->call('save')->assertHasErrors('pdf');
        Livewire::actingAs($user)->test('pages::documents.create')->set('title', 'Teste')
            ->set('pdf', UploadedFile::fake()->createWithContent('good.pdf', "%PDF-1.4\n%%EOF"))
            ->set('signers', [['name' => 'A', 'email' => 'a@example.test'], ['name' => 'B', 'email' => 'A@example.test']])
            ->call('save')->assertHasErrors(['signers.0.email', 'signers.1.email']);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_document_title_is_escaped_and_guests_are_redirected(): void
    {
        $document = Document::factory()->create(['title' => '<script>alert(1)</script>']);
        $this->get(route('documents.show', $document))->assertRedirect(route('login'));
        $this->actingAs($document->user)->get(route('documents.show', $document))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_public_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Guest', 'email' => 'guest@example.test', 'password' => 'password'])->assertNotFound();
    }
}
