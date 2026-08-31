<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DeletionRequestNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_delete_request_notifies_admin_and_super_admin_only(): void
    {
        $superAdmin = $this->user('Super Admin');
        $admin = $this->user('Admin');
        $salesManager = $this->user('Sales Manager');
        $finance = $this->user('Finance');
        $customer = Customer::create([
            'customer_code' => 'CUST-DELETE-' . uniqid(),
            'company_name' => 'Customer Delete Notification ' . uniqid(),
            'pic_name' => 'PIC Delete Test',
            'phone' => '0800000000',
            'status' => 'Potential',
            'user_id' => $finance->id,
        ]);

        $this->actingAs($finance)->post(route('deletion-requests.store'), [
            'module' => 'customers',
            'model_id' => $customer->id,
            'reason' => 'Data duplikat untuk pengujian.',
        ])->assertSessionHas('success');

        $expectedTitle = 'Permintaan Hapus: Customer';
        foreach ([$superAdmin, $admin] as $recipient) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $recipient->id,
                'type' => 'delete_request',
                'title' => $expectedTitle,
                'is_read' => false,
            ]);
        }
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $salesManager->id,
            'type' => 'delete_request',
            'title' => $expectedTitle,
        ]);

        $this->actingAs($superAdmin)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['title' => $expectedTitle]);

        $deletionRequest = \App\Models\DeletionRequest::where('model_type', Customer::class)
            ->where('model_id', $customer->id)
            ->where('status', 'pending')
            ->sole();
        $this->actingAs($superAdmin)
            ->post(route('deletion-requests.approve', $deletionRequest))
            ->assertSessionHas('success');

        $resultNotification = Notification::where('user_id', $finance->id)
            ->where('title', 'Permintaan Hapus Disetujui')
            ->latest('id')
            ->firstOrFail();
        $this->assertSame(route('customers.index'), $resultNotification->url);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => $role . ' Delete Notification Test',
            'email' => strtolower(str_replace(' ', '-', $role)) . '-delete-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'Active',
        ]);
    }
}
