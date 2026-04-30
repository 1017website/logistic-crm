<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users / Sales
        Schema::create('sales_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('position')->default('Sales Executive');
            $table->string('avatar')->nullable();
            $table->timestamps();
        });

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
            $table->foreignId('sales_user_id')->nullable()->constrained('sales_users')->nullOnDelete();
            $table->date('customer_since')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        // Vendors
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_name');
            $table->string('pic_name');
            $table->string('pic_position')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->enum('vendor_type', ['Shipping Line', 'Trucking', 'Air Freight', 'Others'])->default('Trucking');
            $table->string('service_type')->nullable();
            $table->string('coverage_area')->nullable();
            $table->enum('status', ['Active', 'Non-Active'])->default('Active');
            $table->boolean('is_preferred')->default(false);
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('payment_term')->nullable();
            $table->date('vendor_since')->nullable();
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
            $table->enum('pipeline_stage', ['Identifying', 'Approaching', 'Follow Up', 'Closing', 'Won', 'Lost'])->default('Identifying');
            $table->enum('temperature', ['Hot', 'Warm', 'Cold'])->default('Warm');
            $table->string('service_type')->nullable();
            $table->string('route')->nullable();
            $table->string('commodity')->nullable();
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
            $table->foreignId('sales_user_id')->nullable()->constrained('sales_users')->nullOnDelete();
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
            $table->foreignId('sales_user_id')->nullable()->constrained('sales_users')->nullOnDelete();
            $table->enum('type', ['Call', 'Visit', 'Email', 'Note', 'Others']);
            $table->string('subject');
            $table->text('description')->nullable();
            $table->datetime('activity_at');
            $table->enum('status', ['Done', 'Pending', 'Planned', 'Overdue'])->default('Pending');
            $table->date('next_follow_up')->nullable();
            $table->timestamps();
        });

        // Delivery Orders
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('do_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('service_type');
            $table->string('route');
            $table->decimal('amount', 15, 0)->default(0);
            $table->string('currency')->default('IDR');
            $table->enum('status', ['Done', 'In Progress', 'Cancelled'])->default('In Progress');
            $table->date('order_date');
            $table->timestamps();
        });

        // Quotations
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number')->unique();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('service_type');
            $table->string('route');
            $table->decimal('total_price', 15, 0)->default(0);
            $table->string('currency')->default('IDR');
            $table->date('sent_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->enum('status', ['Draft', 'Sent', 'Accepted', 'Rejected'])->default('Draft');
            $table->timestamps();
        });

        // Vendor Rates
        Schema::create('vendor_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('route');
            $table->string('container_type')->nullable();
            $table->decimal('price', 15, 0)->default(0);
            $table->string('currency')->default('USD');
            $table->date('last_updated')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_rates');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('delivery_orders');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('sales_users');
    }
};
