<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Potensi revenue dapat dicatat langsung dari form Add Activity, sama seperti
 * saat lead pertama kali dibuat, dan ikut memperbarui nilai Rp di Pipeline.
 *
 * Field yang dibiarkan kosong TIDAK menimpa nilai yang sudah ada — form
 * aktivitas tidak boleh mengosongkan potensi revenue tanpa sengaja.
 */
class ActivityPotensiRevenueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_activity_records_revenue_and_updates_the_lead(): void
    {
        [$sales, $lead] = $this->makeLead(currentRevenue: 10_000_000);

        $this->actingAs($sales)
            ->post(route('sales.activity.store'), $this->payload($lead, [
                'potensi_revenue' => 75_000_000,
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('75000000', $lead->fresh()->potensi_revenue);

        $activity = Activity::where('lead_id', $lead->id)->latest('id')->firstOrFail();
        $this->assertSame('75000000', $activity->potensi_revenue);
    }

    public function test_empty_revenue_does_not_overwrite_existing_value(): void
    {
        [$sales, $lead] = $this->makeLead(currentRevenue: 25_000_000);

        $this->actingAs($sales)
            ->post(route('sales.activity.store'), $this->payload($lead, ['potensi_revenue' => '']))
            ->assertSessionHas('success');

        $this->assertSame('25000000', $lead->fresh()->potensi_revenue);
        $this->assertNull(Activity::where('lead_id', $lead->id)->latest('id')->firstOrFail()->potensi_revenue);
    }

    public function test_revenue_field_can_be_omitted_entirely(): void
    {
        [$sales, $lead] = $this->makeLead(currentRevenue: 5_000_000);
        $payload = $this->payload($lead);
        unset($payload['potensi_revenue']);

        $this->actingAs($sales)
            ->post(route('sales.activity.store'), $payload)
            ->assertSessionHas('success');

        $this->assertSame('5000000', $lead->fresh()->potensi_revenue);
    }

    public function test_zero_is_accepted_as_an_explicit_reset(): void
    {
        [$sales, $lead] = $this->makeLead(currentRevenue: 40_000_000);

        $this->actingAs($sales)
            ->post(route('sales.activity.store'), $this->payload($lead, ['potensi_revenue' => 0]))
            ->assertSessionHas('success');

        $this->assertSame('0', $lead->fresh()->potensi_revenue);
    }

    public function test_negative_revenue_is_rejected(): void
    {
        [$sales, $lead] = $this->makeLead(currentRevenue: 12_000_000);

        $this->actingAs($sales)
            ->from(route('sales.activity'))
            ->post(route('sales.activity.store'), $this->payload($lead, ['potensi_revenue' => -500]))
            ->assertSessionHasErrors('potensi_revenue');

        $this->assertSame('12000000', $lead->fresh()->potensi_revenue);
    }

    public function test_activity_on_existing_customer_updates_its_related_lead(): void
    {
        $sales = $this->makeSales();
        $customer = Customer::create([
            'customer_code' => 'CUST-AR-' . uniqid(),
            'company_name' => 'Customer Activity Revenue',
            'pic_name' => 'PIC',
            'phone' => '0800000013',
            'status' => 'Existing',
            'user_id' => $sales->id,
        ]);
        $lead = Lead::create([
            'lead_code' => Lead::generateLeadCode(),
            'customer_id' => $customer->id,
            'company_name' => $customer->company_name,
            'pic_name' => 'PIC',
            'phone' => '0800000013',
            'pipeline_stage' => 'Maintaining',
            'temperature' => 'Warm',
            'user_id' => $sales->id,
            'potensi_revenue' => 0,
        ]);

        $this->actingAs($sales)
            ->post(route('sales.activity.store'), [
                'client_ref' => 'customer:' . $customer->id,
                'type' => 'Call',
                'subject' => 'Nego rate kontrak tahunan',
                'status' => 'Pending',
                'pipeline_stage' => 'Follow Up',
                'potensi_revenue' => 120_000_000,
            ])
            ->assertSessionHas('success');

        $this->assertSame('120000000', $lead->fresh()->potensi_revenue);
    }

    public function test_pipeline_summary_on_activity_page_reflects_the_new_value(): void
    {
        [$sales, $lead] = $this->makeLead(currentRevenue: 0);

        $this->actingAs($sales)
            ->post(route('sales.activity.store'), $this->payload($lead, [
                'pipeline_stage' => 'Follow Up',
                'potensi_revenue' => 90_000_000,
            ]))
            ->assertSessionHas('success');

        $summary = $this->actingAs($sales)
            ->get(route('sales.activity'))
            ->assertOk()
            ->viewData('pipelineSummary');

        $this->assertSame(90_000_000.0, $summary['Follow Up']['value']);
    }

    public function test_add_activity_form_exposes_the_revenue_field(): void
    {
        [$sales] = $this->makeLead(currentRevenue: 0);

        $this->actingAs($sales)
            ->get(route('sales.activity'))
            ->assertOk()
            ->assertSee('Potensi Revenue (Rp)')
            ->assertSee('name="potensi_revenue"', false)
            ->assertSee('data-revenue=', false);
    }

    private function payload(Lead $lead, array $overrides = []): array
    {
        return array_merge([
            'lead_id' => $lead->id,
            'client_ref' => 'lead:' . $lead->id,
            'type' => 'Call',
            'subject' => 'Follow up penawaran',
            'status' => 'Pending',
            'pipeline_stage' => 'Follow Up',
        ], $overrides);
    }

    /** @return array{0:User,1:Lead} */
    private function makeLead(int $currentRevenue): array
    {
        $sales = $this->makeSales();
        $lead = Lead::create([
            'lead_code' => Lead::generateLeadCode(),
            'company_name' => 'Lead Activity Revenue ' . uniqid(),
            'pic_name' => 'PIC',
            'phone' => '0800000014',
            'pipeline_stage' => 'Approaching',
            'temperature' => 'Warm',
            'user_id' => $sales->id,
            'potensi_revenue' => $currentRevenue,
        ]);

        return [$sales, $lead];
    }

    private function makeSales(): User
    {
        return User::create([
            'name' => 'Sales Revenue',
            'email' => 'sales-revenue-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Sales Executive',
            'status' => 'Active',
        ]);
    }
}
