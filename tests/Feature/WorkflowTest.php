<?php

namespace Tests\Feature;

use App\Jobs\ProcessImportJob;
use App\Models\Attachment;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\CustomerService;
use App\Services\ImportService;
use App\Services\InvoiceService;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_is_accepted_by_the_customer_and_converted_once(): void
    {
        [$user, $tenant] = $this->workspace();
        $quote = $this->inTenant($tenant, function () {
            $customer = app(CustomerService::class)->create(['name' => 'Acme']);
            $payload = $this->invoicePayload($customer->id);
            $payload['expiry_date'] = now()->addDays(14)->toDateString();
            unset($payload['due_date']);

            return app(QuoteService::class)->send(app(QuoteService::class)->create($payload), false);
        });

        $this->freshRequest();
        $this->post('/quote/public/'.$quote->public_token.'/respond', ['response' => 'accept'])->assertSessionHas('success');
        $this->assertSame('accepted', Quote::withoutGlobalScope('tenant')->find($quote->id)->status);

        $this->freshRequest()->actingAs($user)->post("/quotes/{$quote->id}/convert")->assertRedirect();
        $invoice = Invoice::withoutGlobalScope('tenant')->where('quote_id', $quote->id)->firstOrFail();
        $this->assertEquals(200, (float) $invoice->total);
        $this->assertSame('draft', $invoice->status);

        // A second conversion must not create another invoice
        $this->freshRequest()->actingAs($user)->post("/quotes/{$quote->id}/convert");
        $this->assertSame(1, Invoice::withoutGlobalScope('tenant')->where('quote_id', $quote->id)->count());
    }

    public function test_csv_export_contains_only_this_workspace_and_neutralises_formulas(): void
    {
        [$user, $tenant] = $this->workspace();
        [, $other] = $this->workspace();
        $this->inTenant($tenant, fn () => app(CustomerService::class)->create(['name' => '=HYPERLINK("http://evil.test")', 'email' => 'mine@a.test']));
        $this->inTenant($other, fn () => app(CustomerService::class)->create(['name' => 'Other workspace customer', 'email' => 'theirs@b.test']));
        $this->freshRequest();

        $response = $this->actingAs($user)->get('/exports/download?type=customers&format=csv')->assertOk();
        $csv = $response->getContent();

        $this->assertStringContainsString('mine@a.test', $csv);
        $this->assertStringNotContainsString('theirs@b.test', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'cells starting with = are escaped so spreadsheets do not run them');

        $xlsx = $this->freshRequest()->actingAs($user)->get('/exports/download?type=customers&format=xlsx')->assertOk()->getContent();
        $this->assertStringStartsWith('PK', $xlsx);
        $pdf = $this->freshRequest()->actingAs($user)->get('/exports/download?type=customers&format=pdf')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_csv_import_creates_valid_rows_and_reports_bad_ones(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$user, $tenant] = $this->workspace();

        $csv = "Name,Email,Phone\nAlpha Ltd,alpha@a.test,111\n,missing-name@a.test,222\nBeta Ltd,not-an-email,333\nGamma Ltd,gamma@a.test,444\n";
        $this->actingAs($user)->post('/imports', ['type' => 'customers', 'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv)])->assertRedirect();

        $import = Import::withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame('uploaded', $import->status);

        $this->freshRequest()->actingAs($user)->post("/imports/{$import->id}/start", ['mapping' => $import->mapping])->assertSessionHas('success');
        Queue::assertPushed(ProcessImportJob::class);

        $this->inTenant($tenant, fn () => app(ImportService::class)->process($import->fresh()));
        $import->refresh();

        $this->assertSame(2, $import->imported_rows);
        $this->assertSame(2, $import->failed_rows);
        $this->assertEqualsCanonicalizing(['Alpha Ltd', 'Gamma Ltd'], Customer::withoutGlobalScope('tenant')->pluck('name')->all());
    }

    public function test_uploads_are_validated_stored_privately_and_scoped_to_the_workspace(): void
    {
        Storage::fake('local');
        [$user] = $this->workspace();
        [$stranger] = $this->workspace();

        // Executable and oversized files are refused
        $this->actingAs($user)->post('/documents', ['category' => 'contracts', 'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php')])->assertSessionHasErrors('file');
        $this->freshRequest()->actingAs($user)->post('/documents', ['category' => 'contracts', 'file' => UploadedFile::fake()->create('big.pdf', config('saas.upload.max_kb') + 1, 'application/pdf')])->assertSessionHasErrors('file');
        $this->assertSame(0, Attachment::withoutGlobalScope('tenant')->count());

        $this->freshRequest()->actingAs($user)->post('/documents', ['category' => 'contracts', 'file' => UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf')])->assertSessionHas('success');
        $document = Attachment::withoutGlobalScope('tenant')->firstOrFail();

        $this->assertSame('contract.pdf', $document->original_name);
        $this->assertStringNotContainsString('contract', basename($document->path), 'the stored name is generated, not the uploaded one');
        Storage::disk('local')->assertExists($document->path);

        $this->freshRequest()->actingAs($user)->get("/documents/{$document->id}/download")->assertOk();
        $this->freshRequest()->actingAs($stranger)->get("/documents/{$document->id}/download")->assertNotFound();
    }

    public function test_api_tokens_are_hashed_scoped_and_tenant_bound(): void
    {
        [$user, $tenant] = $this->workspace();
        [, $other] = $this->workspace();
        $this->inTenant($tenant, fn () => app(CustomerService::class)->create(['name' => 'Mine']));
        $this->inTenant($other, fn () => app(CustomerService::class)->create(['name' => 'Theirs']));
        $this->freshRequest();

        $response = $this->actingAs($user)->post('/settings/api-tokens', ['name' => 'Zapier', 'abilities' => ['customers:read']]);
        $plain = $response->getSession()->get('api_token');

        $this->assertStringStartsWith('sk_', $plain);
        $this->assertDatabaseMissing('api_tokens', ['token_hash' => $plain]);
        $this->assertDatabaseHas('api_tokens', ['token_hash' => hash('sha256', $plain)]);

        $this->freshRequest();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/customers')->assertStatus(401);
        $this->freshRequest();
        $this->getJson('/api/v1/customers', ['Authorization' => 'Bearer sk_wrong'])->assertStatus(401);

        $this->freshRequest();
        $list = $this->getJson('/api/v1/customers', ['Authorization' => 'Bearer '.$plain])->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('Mine', $list->getContent());
        $this->assertStringNotContainsString('Theirs', $list->getContent());

        // The token has no write scope and no invoice scope
        $this->freshRequest();
        $this->postJson('/api/v1/customers', ['name' => 'New'], ['Authorization' => 'Bearer '.$plain])->assertStatus(403);
        $this->freshRequest();
        $this->getJson('/api/v1/invoices', ['Authorization' => 'Bearer '.$plain])->assertStatus(403);
    }
}
