<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardCustomerDoTest extends TestCase
{
    use DatabaseTransactions;

    private function operator(): User
    {
        return User::create([
            'name' => 'Dashboard Test', 'email' => uniqid().'@example.test',
            'password' => 'password', 'role' => 'Admin', 'status' => 'Active',
        ]);
    }

    private function customer(User $user, string $name): Customer
    {
        return Customer::create([
            'customer_code' => uniqid('DASH-'), 'company_name' => $name,
            'pic_name' => 'PIC', 'phone' => '0800000000', 'status' => 'Existing', 'user_id' => $user->id,
        ]);
    }

    private function delivery(User $user, Customer $customer, string $date): DeliveryOrder
    {
        $order = RequestOrder::create([
            'do_number' => uniqid('RDO-DASH-'), 'customer_id' => $customer->id,
            'user_id' => $user->id, 'order_date' => '2097-01-01', 'currency' => 'IDR',
            'status' => 'In Progress', 'request_status' => 'assigned', 'invoice_status' => 'uninvoiced',
        ]);
        return DeliveryOrder::create([
            'do_number' => uniqid('DO-DASH-'), 'request_order_id' => $order->id,
            'customer_id' => $customer->id, 'user_id' => $user->id, 'do_date' => $date,
            'status' => 'surat_jalan', 'invoice_status' => 'uninvoiced', 'assignment_type' => 'internal',
        ]);
    }

    public function test_summary_counts_final_dos_by_do_date_and_customer_and_excludes_deleted_dos(): void
    {
        $user = $this->operator();
        $a = $this->customer($user, 'Customer A');
        $b = $this->customer($user, 'Customer B');
        $this->delivery($user, $a, '2097-09-01');
        $this->delivery($user, $a, '2097-09-30');
        $this->delivery($user, $b, '2097-09-15');
        $this->delivery($user, $a, '2097-08-31');
        $this->delivery($user, $a, '2097-10-01');
        $this->delivery($user, $a, '2096-09-15');
        $this->delivery($user, $a, '2097-09-15')->delete();
        $b->delete();

        $response = $this->actingAs($user)->get(route('dashboard', ['do_month' => 9, 'do_year' => 2097]));
        $response->assertOk()->assertViewHas('customerDoTotal', 3)->assertViewHas('totalDo', 3)
            ->assertViewHas('customerDoCount', 2)->assertSee('September 2097')
            ->assertSee('Customer B')->assertViewHas('customerDos', function ($rows) use ($a) {
                return $rows->first()->customer_id === $a->id && (int) $rows->first()->do_count === 2;
            });
        $url = route('delivery-orders.index', ['customer_id' => $a->id, 'start_date' => '2097-09-01', 'end_date' => '2097-09-30']);
        $response->assertSee($url);
        $this->get($url)->assertOk()->assertViewHas('dos', fn ($rows) => $rows->total() === 2)
            ->assertViewHas('volumeDo', 2);
    }

    public function test_default_period_empty_state_and_invalid_filters(): void
    {
        $user = $this->operator();
        $this->travelTo(\Carbon\Carbon::parse('2097-12-15'));
        try {
            $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertViewHas('doMonth', 12)->assertViewHas('doYear', 2097)
                ->assertSee('Belum ada Delivery Order pada Desember 2097.');
            $this->getJson(route('dashboard', ['do_month' => 13]))->assertUnprocessable();
            $this->getJson(route('dashboard', ['do_year' => 'abc']))->assertUnprocessable();
        } finally {
            $this->travelBack();
        }
    }

    public function test_top_five_can_expand_and_sales_executive_does_not_get_forbidden_links(): void
    {
        $user = $this->operator();
        for ($i = 1; $i <= 6; $i++) {
            $this->delivery($user, $this->customer($user, 'Summary Customer '.$i), '2097-09-15');
        }
        $params = ['do_month' => 9, 'do_year' => 2097];
        $this->actingAs($user)->get(route('dashboard', $params))->assertOk()
            ->assertViewHas('customerDos', fn ($rows) => $rows->count() === 5)->assertSee('Lihat Semua');
        $this->get(route('dashboard', $params + ['do_all' => 1]))->assertOk()
            ->assertViewHas('customerDos', fn ($rows) => $rows->total() === 6)->assertSee('Tampilkan 5 Teratas');
        $user->update(['role' => 'Sales Executive']);
        $response = $this->actingAs($user->fresh())->get(route('dashboard', $params));
        $response->assertOk()->assertViewHas('customerDoTotal', 6);
        $section = explode('</section>', explode('id="customer-do-summary"', $response->getContent())[1])[0];
        $this->assertStringNotContainsString('/delivery-orders?', $section);
    }
}
