<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DatabaseExcelImportTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_customer_database_can_be_imported_from_xlsx(): void
    {
        $user = $this->makeAdmin();
        $invoiceCode = 'IMP'.strtoupper(substr(uniqid(), -8));
        $file = $this->xlsxUpload([
            ['Company Name', 'PIC Name', 'Phone', 'Email', 'Kode Invoice', 'Customer Since', 'Service Name', 'Unit', 'Tonnage'],
            ['PT Import Customer Test', 'Customer PIC', '081234567890', 'pic@customer.test', $invoiceCode, 46023, 'Trucking', 'trip', 12.5],
        ], 'customers.xlsx');

        $response = $this->actingAs($user)->post(route('customers.import'), ['file' => $file]);

        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('success', 'Berhasil import 1 customer.');

        $customer = Customer::where('invoice_code', $invoiceCode)->firstOrFail();
        $this->assertSame('Existing', $customer->status);
        $this->assertSame('2026-01-01', $customer->customer_since?->format('Y-m-d'));
        $this->assertDatabaseHas('customer_products', [
            'customer_id' => $customer->id,
            'product_name' => 'Trucking',
        ]);
        $this->assertDatabaseHas('leads', [
            'customer_id' => $customer->id,
            'pipeline_stage' => 'Maintaining',
        ]);
    }

    public function test_vendor_database_can_be_imported_from_xlsx(): void
    {
        $user = $this->makeAdmin();
        $vendorName = 'PT Import Vendor '.uniqid();
        $file = $this->xlsxUpload([
            ['Vendor Name', 'PIC Name', 'Phone', 'Vendor Type', 'Status', 'Relationship', 'Preferred', 'Rating', 'Service Name', 'Tariff'],
            [$vendorName, 'Vendor PIC', '081298765432', 'external', 'active', 'existing', 'Yes', 4.5, 'Trucking FCL', 3500000],
        ], 'vendors.xlsx');

        $response = $this->actingAs($user)->post(route('vendors.import'), ['file' => $file]);

        $response->assertRedirect(route('vendors.index'));
        $response->assertSessionHas('success', 'Berhasil import 1 vendor.');

        $vendor = Vendor::where('vendor_name', $vendorName)->firstOrFail();
        $this->assertSame('External', $vendor->vendor_type);
        $this->assertSame('Existing', $vendor->relationship_status);
        $this->assertTrue($vendor->is_preferred);
        $this->assertDatabaseHas('vendor_services', [
            'vendor_id' => $vendor->id,
            'service_name' => 'Trucking FCL',
            'tariff' => 3500000,
        ]);
    }

    public function test_import_rejects_a_file_without_required_headers(): void
    {
        $file = $this->xlsxUpload([
            ['Company Name', 'Email'],
            ['PT Header Invalid', 'invalid@example.test'],
        ], 'invalid-customers.xlsx');

        $response = $this->actingAs($this->makeAdmin())->post(route('customers.import'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('customers', ['company_name' => 'PT Header Invalid']);
    }

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Import Test Admin',
            'email' => uniqid('import-test-', true).'@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function xlsxUpload(array $rows, string $originalName): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);

        $path = tempnam(sys_get_temp_dir(), 'crm-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->temporaryFiles[] = $path;

        return new UploadedFile(
            $path,
            $originalName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
