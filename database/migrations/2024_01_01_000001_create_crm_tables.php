<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('pic_name');
            $table->string('pic_position')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('industry')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['Existing', 'Potential'])->default('Potential');
            $table->enum('value_tag', ['High Value', 'Normal'])->default('Normal');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('customer_since')->nullable();
            $table->string('logo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->string('pic_name');
            $table->string('pic_position')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->enum('source_type', ['Local', 'Import'])->default('Local');
            $table->string('product_category')->nullable();
            $table->string('origin_country')->nullable();
            $table->enum('status', ['Active', 'Non-Active'])->default('Active');
            $table->enum('relationship_status', ['Potential', 'Existing'])->default('Potential');
            $table->boolean('is_preferred')->default(false);
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('payment_term')->nullable();
            $table->date('supplier_since')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        // Leads
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_code')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('company_name');
            $table->string('pic_name');
            $table->string('pic_position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('industry')->nullable();
            $table->enum('pipeline_stage', ['Identifying','Approaching','Follow Up','Closing','Won','Lost','Maintaining'])->default('Identifying');
            $table->enum('temperature', ['Hot','Warm','Cold'])->nullable()->default('Warm');
            $table->string('product_interest')->nullable();
            $table->string('volume_estimate')->nullable();
            $table->string('timeline')->nullable();
            $table->text('notes_kebutuhan')->nullable();
            $table->text('catatan_internal')->nullable();
            $table->decimal('potensi_revenue', 15, 0)->default(0);
            $table->integer('probability')->default(0);
            $table->decimal('lead_score', 5, 1)->default(0);
            $table->string('lead_source')->nullable();
            $table->string('competitor')->nullable();
            $table->date('expected_closing')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_follow_up')->nullable();
            $table->time('next_follow_up_time')->nullable();
            $table->string('next_follow_up_notes')->nullable();
            $table->timestamps();
        });

        // Activities
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['Call', 'Visit', 'Email', 'Note', 'Others']);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->datetime('activity_at');
            $table->enum('status', ['Done', 'Pending', 'Planned', 'Overdue'])->default('Pending');
            $table->date('next_follow_up')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        // Purchase Orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('currency')->default('IDR');
            $table->enum('status', ['Done', 'In Progress', 'Cancelled'])->default('In Progress');
            $table->date('order_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Purchase Order Items
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('product_name');
            $table->string('unit')->default('kg');
            $table->decimal('qty', 15, 3)->default(0);
            $table->decimal('buy_price', 15, 0)->default(0);
            $table->decimal('sell_price', 15, 0)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Task Reminders
        Schema::create('task_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('due_date');
            $table->enum('priority', ['High', 'Medium', 'Low'])->default('Medium');
            $table->enum('status', ['Pending', 'Done', 'Overdue'])->default('Pending');
            $table->timestamps();
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('info');
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // Settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('task_reminders');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('settings');
    }
};
