<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierPic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $sourceType         = $request->get('source_type');
        $status             = $request->get('status');
        $relationshipStatus = $request->get('relationship_status');
        $search             = $request->get('search');

        $query = Supplier::with(['purchaseOrders', 'products', 'pics']);
        if ($sourceType         && $sourceType         !== 'all') $query->where('source_type', $sourceType);
        if ($status             && $status             !== 'all') $query->where('status', $status);
        if ($relationshipStatus && $relationshipStatus !== 'all') $query->where('relationship_status', $relationshipStatus);
        if ($search) {
            $query->where(fn($q) => $q
                ->where('supplier_name', 'like', "%$search%")
                ->orWhere('pic_name',    'like', "%$search%")
                ->orWhere('phone',       'like', "%$search%")
                ->orWhere('product_category', 'like', "%$search%")
                ->orWhereHas('products', fn($p) => $p->where('product_name', 'like', "%$search%"))
            );
        }

        $suppliers           = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->paginate(10)->withQueryString();
        $totalSupplier       = Supplier::count();
        $localSupplier       = Supplier::where('source_type', 'Local')->count();
        $importSupplier      = Supplier::where('source_type', 'Import')->count();
        $existingSupplier    = Supplier::where('relationship_status', 'Existing')->count();
        $potentialSupplier   = Supplier::where('relationship_status', 'Potential')->count();

        $selectedSupplier = $request->get('selected_id')
            ? Supplier::with(['purchaseOrders', 'products', 'pics'])->find($request->get('selected_id'))
            : null;

        return view('suppliers.index', compact(
            'suppliers','totalSupplier','localSupplier','importSupplier',
            'existingSupplier','potentialSupplier','selectedSupplier',
            'sourceType','status','relationshipStatus','search'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_name'       => 'required|string|max:255',
            'source_type'         => 'required|in:Local,Import',
            'pic_name'            => 'required|string|max:255',
            'pic_position'        => 'nullable|string|max:100',
            'phone'               => 'required|string|max:20',
            'email'               => 'nullable|email|max:255',
            'address'             => 'nullable|string',
            'product_category'    => 'nullable|string|max:255',
            'origin_country'      => 'nullable|string|max:100',
            'payment_term'        => 'nullable|string|max:100',
            'status'              => 'required|in:Active,Non-Active',
            'relationship_status' => 'required|in:Potential,Existing',
            'is_preferred'        => 'boolean',
            'rating'              => 'nullable|numeric|min:0|max:5',
            'supplier_since'      => 'nullable|date',
            // inline pics & products
            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',
            'products'            => 'nullable|array',
            'products.*.product_name' => 'nullable|string|max:255',
            'products.*.unit'     => 'nullable|string|max:50',
            'products.*.description' => 'nullable|string',
        ]);

        $validated['is_preferred'] = $request->boolean('is_preferred');
        $validated['rating']       = $validated['rating'] ?? 0;

        DB::transaction(function () use ($request, $validated) {
            $pics     = $validated['pics'] ?? [];
            $products = $validated['products'] ?? [];
            unset($validated['pics'], $validated['products']);

            $supplier = Supplier::create($validated);

            $picIndex = 0;
            foreach ($pics as $pic) {
                $picName = trim($pic['pic_name'] ?? '');
                if ($picName === '') {
                    continue;
                }

                $supplier->pics()->create([
                    'pic_name'     => $picName,
                    'pic_position' => $pic['pic_position'] ?? null,
                    'phone'        => $pic['phone'] ?? null,
                    'email'        => $pic['email'] ?? null,
                    'is_primary'   => $picIndex === 0,
                ]);

                $picIndex++;
            }

            foreach ($products as $prod) {
                $productName = trim($prod['product_name'] ?? '');
                if ($productName === '') {
                    continue;
                }

                $supplier->products()->create([
                    'product_name' => $productName,
                    'unit'         => $prod['unit'] ?? 'ton',
                    'description'  => $prod['description'] ?? null,
                ]);
            }
        });

        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'supplier_name'       => 'sometimes|string|max:255',
            'source_type'         => 'sometimes|in:Local,Import',
            'pic_name'            => 'sometimes|string|max:255',
            'pic_position'        => 'nullable|string|max:100',
            'phone'               => 'nullable|string|max:20',
            'email'               => 'nullable|email|max:255',
            'address'             => 'nullable|string',
            'product_category'    => 'nullable|string|max:255',
            'origin_country'      => 'nullable|string|max:100',
            'payment_term'        => 'nullable|string|max:100',
            'status'              => 'sometimes|in:Active,Non-Active',
            'relationship_status' => 'sometimes|in:Potential,Existing',
            'is_preferred'        => 'boolean',
            'rating'              => 'nullable|numeric|min:0|max:5',
            'pics'                => 'nullable|array',
            'pics.*.pic_name'     => 'nullable|string|max:255',
            'pics.*.pic_position' => 'nullable|string|max:100',
            'pics.*.phone'        => 'nullable|string|max:20',
            'pics.*.email'        => 'nullable|email|max:255',
            'products'            => 'nullable|array',
            'products.*.product_name' => 'nullable|string|max:255',
            'products.*.unit'     => 'nullable|string|max:50',
            'products.*.description' => 'nullable|string',
        ]);

        $validated['is_preferred'] = $request->boolean('is_preferred');
        if (array_key_exists('rating', $validated)) $validated['rating'] = $validated['rating'] ?? 0;

        DB::transaction(function () use ($request, $validated, $supplier) {
            $pics     = $validated['pics'] ?? [];
            $products = $validated['products'] ?? [];
            unset($validated['pics'], $validated['products']);

            $supplier->update($validated);

            // Jika edit modal mengirim marker, row PIC dianggap sebagai data final.
            if ($request->has('pics_submitted')) {
                $supplier->pics()->delete();

                $picIndex = 0;
                foreach ($pics as $pic) {
                    $picName = trim($pic['pic_name'] ?? '');
                    if ($picName === '') {
                        continue;
                    }

                    $supplier->pics()->create([
                        'pic_name'     => $picName,
                        'pic_position' => $pic['pic_position'] ?? null,
                        'phone'        => $pic['phone'] ?? null,
                        'email'        => $pic['email'] ?? null,
                        'is_primary'   => $picIndex === 0,
                    ]);

                    $picIndex++;
                }
            }

            // Jika edit modal mengirim marker, row produk dianggap sebagai data final.
            if ($request->has('products_submitted')) {
                $supplier->products()->delete();

                foreach ($products as $prod) {
                    $productName = trim($prod['product_name'] ?? '');
                    if ($productName === '') {
                        continue;
                    }

                    $supplier->products()->create([
                        'product_name' => $productName,
                        'unit'         => $prod['unit'] ?? 'ton',
                        'description'  => $prod['description'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil dihapus.');
    }

    // ── Supplier PICs (via detail panel, tetap ada untuk backward compat) ──
    public function storePic(Request $request, Supplier $supplier)
    {
        $request->validate([
            'pic_name'     => 'required|string|max:255',
            'pic_position' => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'email'        => 'nullable|email|max:255',
        ]);
        $supplier->pics()->create([
            'pic_name'     => $request->pic_name,
            'pic_position' => $request->pic_position,
            'phone'        => $request->phone,
            'email'        => $request->email,
            'is_primary'   => $supplier->pics()->count() === 0,
        ]);
        return redirect()->back()->with('success', 'PIC ditambahkan.');
    }

    public function destroyPic(Supplier $supplier, SupplierPic $pic)
    {
        abort_if((int) $pic->supplier_id !== (int) $supplier->id, 404);
        $pic->delete();
        return redirect()->back()->with('success', 'PIC dihapus.');
    }

    // ── Supplier Products ──
    public function storeProduct(Request $request, Supplier $supplier)
    {
        $request->validate([
            'product_name' => 'required|string|max:255',
            'unit'         => 'required|string|max:50',
            'description'  => 'nullable|string',
        ]);
        $supplier->products()->create([
            'product_name' => $request->product_name,
            'unit'         => $request->unit,
            'description'  => $request->description,
        ]);
        return redirect()->back()->with('success', 'Produk ditambahkan.');
    }

    public function destroyProduct(Supplier $supplier, SupplierProduct $product)
    {
        abort_if((int) $product->supplier_id !== (int) $supplier->id, 404);
        $product->delete();
        return redirect()->back()->with('success', 'Produk dihapus.');
    }

    public function export(Request $request)
    {
        $sourceType         = $request->get('source_type');
        $status             = $request->get('status');
        $relationshipStatus = $request->get('relationship_status');
        $search             = $request->get('search');

        $query = Supplier::with('products');

        if ($sourceType && $sourceType !== 'all') {
            $query->where('source_type', $sourceType);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($relationshipStatus && $relationshipStatus !== 'all') {
            $query->where('relationship_status', $relationshipStatus);
        }

        if ($search) {
            $query->where(fn($q) => $q
                ->where('supplier_name', 'like', "%$search%")
                ->orWhere('pic_name', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%")
                ->orWhere('product_category', 'like', "%$search%")
                ->orWhereHas('products', fn($p) => $p->where('product_name', 'like', "%$search%"))
            );
        }

        $suppliers = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->get();

        $headers = ['Supplier Name', 'Source Type', 'PIC', 'Phone', 'Email', 'Product Category', 'Produk Supplier', 'Origin Country', 'Relationship', 'Status', 'Preferred', 'Rating'];
        $rows = $suppliers->map(fn($s) => [
            $s->supplier_name,
            $s->source_type,
            $s->pic_name,
            $s->phone,
            $s->email,
            $s->product_category,
            $s->products->map(fn($p) => trim($p->product_name . ($p->unit ? ' (' . $p->unit . ')' : '')))->implode(', '),
            $s->origin_country,
            $s->relationship_status,
            $s->status,
            $s->is_preferred ? 'Yes' : 'No',
            $s->rating,
        ])->toArray();

        return \App\Helpers\ExcelExport::download('suppliers-' . date('Ymd'), $headers, $rows, 'Suppliers');
    }

}
