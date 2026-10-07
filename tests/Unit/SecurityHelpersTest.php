<?php

namespace Tests\Unit;

use App\Services\ExportService;
use App\Services\Gateways\StripeGateway;
use App\Services\TwoFactorService;
use App\Services\WebhookService;
use App\Support\SafeUrl;
use App\Support\SimpleXlsx;
use Tests\TestCase;

class SecurityHelpersTest extends TestCase
{
    public function test_totp_matches_the_rfc_6238_test_vector(): void
    {
        $service = new TwoFactorService;
        // RFC 6238 secret "12345678901234567890" in base32, time 59s -> 94287082 (last 6 digits)
        $this->assertSame('287082', $service->codeAt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', intdiv(59, 30)));

        $secret = $service->generateSecret();
        $this->assertTrue($service->verify($secret, $service->codeAt($secret, intdiv(time(), 30))));
        $this->assertFalse($service->verify($secret, '000000') && $service->verify($secret, '111111'));
        $this->assertFalse($service->verify($secret, 'abc'));
    }

    public function test_stripe_signature_validation(): void
    {
        $payload = '{"id":"evt_1"}';
        $secret = 'whsec_test';
        $timestamp = time();
        $header = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        $this->assertTrue(StripeGateway::validSignature($payload, $header, $secret));
        $this->assertFalse(StripeGateway::validSignature($payload.' ', $header, $secret), 'tampered payload');
        $this->assertFalse(StripeGateway::validSignature($payload, $header, 'other'), 'wrong secret');
        $this->assertFalse(StripeGateway::validSignature($payload, '', $secret), 'missing header');

        $old = time() - 3600;
        $this->assertFalse(StripeGateway::validSignature($payload, 't='.$old.',v1='.hash_hmac('sha256', $old.'.'.$payload, $secret), $secret), 'replayed old event');
    }

    public function test_outgoing_webhook_signature_is_reproducible(): void
    {
        $this->assertSame(hash_hmac('sha256', '100.{}', 'secret'), WebhookService::sign('100', '{}', 'secret'));
    }

    public function test_internal_urls_are_not_public(): void
    {
        $this->assertFalse(SafeUrl::isPublic('http://127.0.0.1/hook'));
        $this->assertFalse(SafeUrl::isPublic('http://localhost:8080'));
        $this->assertFalse(SafeUrl::isPublic('http://192.168.1.10/x'));
        $this->assertFalse(SafeUrl::isPublic('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse(SafeUrl::isPublic('ftp://example.com'));
        $this->assertTrue(SafeUrl::isPublic('https://8.8.8.8/hook'));
    }

    public function test_csv_cells_cannot_start_a_formula(): void
    {
        $this->assertSame("'=SUM(A1)", ExportService::safeCell('=SUM(A1)'));
        $this->assertSame("'@cmd", ExportService::safeCell('@cmd'));
        $this->assertSame('-5.00', ExportService::safeCell('-5.00'));
        $this->assertSame('Normal', ExportService::safeCell('Normal'));
    }

    public function test_xlsx_writer_produces_a_readable_workbook(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, SimpleXlsx::build(['Name', 'Amount'], [['Ünïcode & <tags>', 12.5]]));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertNotFalse(simplexml_load_string($sheet), 'sheet is valid XML');
        $this->assertStringContainsString('<v>12.5</v>', $sheet);
        $this->assertStringContainsString('&lt;tags&gt;', $sheet);
    }
}
