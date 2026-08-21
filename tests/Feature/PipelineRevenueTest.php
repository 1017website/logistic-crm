<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PipelineRevenueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_potential_revenue_can_be_entered_on_a_lead_and_is_shown_in_pipeline(): void
    {
        $user = User::create([
            'name' => 'Admin Pipeline Revenue',
            'email' => 'pipeline-revenue-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        $companyName = 'PT Pipeline Revenue ' . uniqid();
        $pipelineRevenueBefore = (float) Lead::whereIn('pipeline_stage', ['Identifying', 'Approaching', 'Follow Up'])
            ->sum('potensi_revenue');

        $this->actingAs($user)
            ->post(route('leads.store'), [
                'company_name' => $companyName,
                'pic_name' => 'PIC Revenue',
                'pipeline_stage' => 'Identifying',
                'potensi_revenue' => 987654321,
                'user_id' => $user->id,
            ])
            ->assertSessionHasNoErrors();

        $lead = Lead::where('company_name', $companyName)->sole();
        $this->assertSame('987654321', $lead->potensi_revenue);

        $pipelineResponse = $this->actingAs($user)->get(route('pipeline.index'));
        $pipelineResponse
            ->assertOk()
            ->assertSee($companyName)
            ->assertSee('Rp 987,7 Jt')
            ->assertSee('Perbandingan Revenue Pipeline vs Closing')
            ->assertSee('Potensi Pipeline Aktif')
            ->assertSee('Revenue Closing Aktual')
            ->assertViewHas('pipelineRevenue', fn ($value) => (float) $value === $pipelineRevenueBefore + 987654321)
            ->assertViewHas('closingRevenue');

        $this->actingAs($user)
            ->put(route('leads.update', $lead), [
                'potensi_revenue' => 123456789,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('123456789', $lead->fresh()->potensi_revenue);
    }

    public function test_lead_forms_explain_that_potential_revenue_feeds_pipeline(): void
    {
        $user = User::create([
            'name' => 'Admin Pipeline Form',
            'email' => 'pipeline-form-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        $this->actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Potensi Revenue (Rp)')
            ->assertSee('Ditampilkan sebagai nilai Rp pada kartu dan total Pipeline.');
    }
}
