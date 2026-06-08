<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\LeadPic;
use App\Models\CustomerPic;
use App\Models\LeadProduct;
use App\Models\CustomerProduct;

/**
 * Sinkronisasi dua arah PIC & kebutuhan layanan antara Lead <-> Customer.
 *
 * Dipicu lewat model observer pada saat saved/deleted child record.
 * Guard statis ($syncing) mencegah rekursi tak hingga ketika observer
 * di satu sisi memicu perubahan di sisi lain.
 */
class LeadCustomerSync
{
    /** Guard: saat true, observer dilewati (kita sedang menulis mirror). */
    public static bool $syncing = false;

    private static function run(callable $fn): void
    {
        if (self::$syncing) {
            return;
        }
        self::$syncing = true;
        try {
            $fn();
        } finally {
            self::$syncing = false;
        }
    }

    /** Resolusi customer dari sebuah lead (lewat customer_id). */
    private static function customerOfLead(Lead $lead): ?Customer
    {
        return $lead->customer_id ? Customer::find($lead->customer_id) : null;
    }

    /** Resolusi lead "utama" dari sebuah customer. */
    private static function leadOfCustomer(Customer $customer): ?Lead
    {
        return Lead::where('customer_id', $customer->id)->orderByDesc('updated_at')->first();
    }

    /**
     * Mirror field utama customer -> lead terkait (dipanggil saat edit customer).
     * Tidak mengubah pipeline_stage / status lead.
     */
    public static function syncCustomerFieldsToLead(Customer $customer): void
    {
        self::run(function () use ($customer) {
            $lead = self::leadOfCustomer($customer);
            if (!$lead) return;

            $lead->updateQuietly([
                'company_name' => $customer->company_name,
                'pic_name'     => $customer->pic_name,
                'pic_position' => $customer->pic_position,
                'phone'        => $customer->phone,
                'email'        => $customer->email,
                'address'      => $customer->address,
                'industry'     => $customer->industry,
                'location'     => $customer->location,
            ]);
        });
    }

    // ─────────────────────────── PIC ───────────────────────────

    public static function picSaved(LeadPic $pic): void
    {
        self::run(function () use ($pic) {
            $lead = $pic->lead;
            if (!$lead) return;
            $customer = self::customerOfLead($lead);
            if (!$customer) return;

            self::upsertPic($customer->pics(), $pic);
        });
    }

    public static function picSavedFromCustomer(CustomerPic $pic): void
    {
        self::run(function () use ($pic) {
            $customer = $pic->customer;
            if (!$customer) return;
            $lead = self::leadOfCustomer($customer);
            if (!$lead) return;

            self::upsertPic($lead->pics(), $pic);
        });
    }

    public static function picDeleted(LeadPic $pic): void
    {
        self::run(function () use ($pic) {
            $lead = $pic->lead;
            if (!$lead) return;
            $customer = self::customerOfLead($lead);
            if (!$customer) return;

            self::deleteMatchingPic($customer->pics(), $pic->pic_name, $pic->phone);
        });
    }

    public static function picDeletedFromCustomer(CustomerPic $pic): void
    {
        self::run(function () use ($pic) {
            $customer = $pic->customer;
            if (!$customer) return;
            $lead = self::leadOfCustomer($customer);
            if (!$lead) return;

            self::deleteMatchingPic($lead->pics(), $pic->pic_name, $pic->phone);
        });
    }

    /** @param \Illuminate\Database\Eloquent\Relations\HasMany $relation */
    private static function upsertPic($relation, $source): void
    {
        $name = trim((string) $source->pic_name);
        if ($name === '') return;

        $existing = (clone $relation)->where('pic_name', $name)
            ->when($source->phone, fn ($q) => $q->where('phone', $source->phone))
            ->first();

        $payload = [
            'pic_name'     => $name,
            'pic_position' => $source->pic_position,
            'phone'        => $source->phone,
            'email'        => $source->email,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            $relation->create($payload + ['is_primary' => false]);
        }
    }

    private static function deleteMatchingPic($relation, ?string $name, ?string $phone): void
    {
        $name = trim((string) $name);
        if ($name === '') return;

        (clone $relation)->where('pic_name', $name)
            ->when($phone, fn ($q) => $q->where('phone', $phone))
            ->get()
            ->each(fn ($row) => $row->delete());
    }

    // ─────────────────────── Kebutuhan Layanan ───────────────────────

    public static function productSaved(LeadProduct $product): void
    {
        self::run(function () use ($product) {
            $lead = $product->lead;
            if (!$lead) return;
            $customer = self::customerOfLead($lead);
            if (!$customer) return;

            self::upsertProduct($customer->productItems(), $product);
        });
    }

    public static function productSavedFromCustomer(CustomerProduct $product): void
    {
        self::run(function () use ($product) {
            $customer = $product->customer;
            if (!$customer) return;
            $lead = self::leadOfCustomer($customer);
            if (!$lead) return;

            self::upsertProduct($lead->products(), $product);
        });
    }

    public static function productDeleted(LeadProduct $product): void
    {
        self::run(function () use ($product) {
            $lead = $product->lead;
            if (!$lead) return;
            $customer = self::customerOfLead($lead);
            if (!$customer) return;

            self::deleteMatchingProduct($customer->productItems(), $product->display_name);
        });
    }

    public static function productDeletedFromCustomer(CustomerProduct $product): void
    {
        self::run(function () use ($product) {
            $customer = $product->customer;
            if (!$customer) return;
            $lead = self::leadOfCustomer($customer);
            if (!$lead) return;

            self::deleteMatchingProduct($lead->products(), $product->display_name);
        });
    }

    private static function upsertProduct($relation, $source): void
    {
        $name = trim((string) ($source->display_name ?: $source->service_name ?: $source->product_name));
        if ($name === '') return;

        $existing = (clone $relation)
            ->whereRaw('LOWER(product_name) = ?', [mb_strtolower($name)])
            ->first();

        $payload = [
            'service_name'  => $name,
            'product_name'  => $name,
            'unit'          => trim((string) ($source->unit ?? '')),
            'tonnage'       => $source->tonnage ?? null,
            'shipping_zone' => $source->shipping_zone ?? null,
        ];

        if ($existing) {
            // Update agar tonase & zona ikut tersinkron bila berubah.
            $existing->update($payload);
        } else {
            $relation->create($payload);
        }
    }

    private static function deleteMatchingProduct($relation, ?string $name): void
    {
        $name = trim((string) $name);
        if ($name === '') return;

        (clone $relation)
            ->whereRaw('LOWER(product_name) = ?', [mb_strtolower($name)])
            ->get()
            ->each(fn ($row) => $row->delete());
    }
}
