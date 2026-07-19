<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class LogisticRevisionTest extends TestCase
{
    public function test_invoice_type_labels_cover_trucking_and_non_trucking(): void
    {
        $trucking = new Invoice(['jenis' => 'TR']);
        $nonTrucking = new Invoice(['jenis' => 'NTR']);

        $this->assertSame('Trucking', $trucking->jenis_label);
        $this->assertSame('Non-Trucking', $nonTrucking->jenis_label);
    }

    public function test_accounting_owns_request_pricing_while_sales_admin_owns_approval(): void
    {
        $accounting = new User(['role' => 'Finance']);
        $salesAdmin = new User(['role' => 'Sales Admin']);

        $this->assertTrue($accounting->canAccess('request_item_pricing'));
        $this->assertTrue($accounting->canAccess('job_details'));
        $this->assertFalse($salesAdmin->canAccess('request_item_pricing'));
        $this->assertTrue($salesAdmin->canAccess('approve_do'));
    }
}
