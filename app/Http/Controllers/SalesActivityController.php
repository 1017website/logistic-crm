<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalesActivityController extends Controller
{
    public function index(Request $request)
    {
        $date    = $request->get('date'); // kosong = semua tanggal
        $salesId = $request->get('user_id');
        $type    = $request->get('activity_type');

        $query = Activity::with(['lead', 'customer', 'salesUser']);

        if ($date) {
            $query->whereDate('activity_at', $date);
        }

        if ($salesId && $salesId !== 'all') {
            $query->where('user_id', $salesId);
        }
        if ($type && $type !== 'all') {
            $query->where('type', $type);
        }

        $activities = $query->orderBy('activity_at', 'desc')->paginate(10)->withQueryString();

        $todayReminders = Activity::with(['lead', 'customer'])
            ->whereDate('activity_at', today())->orderBy('activity_at')->get();

        $overdueActivities = Activity::where('status', 'Overdue')
            ->with(['lead', 'customer'])->get();

        $upcomingActivities = Activity::whereDate('activity_at', '>', today())
            ->with(['lead', 'customer'])->orderBy('activity_at')->limit(5)->get();

        $salesUsers = User::orderBy('name')->get();

        $stageRows = Lead::select('pipeline_stage')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(potensi_revenue),0) as value')
            ->groupBy('pipeline_stage')
            ->get()
            ->keyBy('pipeline_stage');
        $pipelineSummary = collect(['Identifying','Approaching','Follow Up','Won','Maintaining'])->mapWithKeys(function ($stage) use ($stageRows) {
            $row = $stageRows->get($stage);
            $label = $stage === 'Won' ? 'Won/Closing' : $stage;
            return [$label => ['count' => (int) optional($row)->total, 'value' => (float) optional($row)->value]];
        })->toArray();

        return view('sales.activity', compact(
            'activities',
            'todayReminders',
            'overdueActivities',
            'upcomingActivities',
            'salesUsers',
            'pipelineSummary',
            'date',
            'salesId',
            'type'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id'        => 'nullable|exists:leads,id',
            'customer_id'    => 'nullable|exists:customers,id',
            'client_ref'     => 'nullable|string|max:50',
            'type'           => 'required|in:Call,Visit,Email,Note,Others',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'nullable|date',
            'status'         => 'required|in:Done,Pending,Planned,Overdue',
            'next_follow_up' => 'nullable|date',
            'pipeline_stage' => 'required|in:Identifying,Approaching,Follow Up,Won,Maintaining',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        if (empty($validated['lead_id']) && empty($validated['customer_id']) && !empty($validated['client_ref'])) {
            [$kind, $id] = array_pad(explode(':', $validated['client_ref'], 2), 2, null);
            if ($kind === 'lead') {
                $validated['lead_id'] = $id;
            } elseif ($kind === 'customer') {
                $validated['customer_id'] = $id;
            }
        }

        $validated['user_id'] = auth()->id();
        $validated['sales_user_id'] = auth()->id();
        $validated['activity_at'] = now();

        $targetLead = null;
        $customer = null;

        if (!empty($validated['lead_id'])) {
            $targetLead = Lead::find($validated['lead_id']);
            if ($targetLead && $targetLead->customer_id) {
                $validated['customer_id'] = $targetLead->customer_id;
            }
        } elseif (!empty($validated['customer_id'])) {
            $customer = Customer::find($validated['customer_id']);
            if ($customer) {
                $targetLead = Lead::where('customer_id', $customer->id)->orderByDesc('updated_at')->first();

                if (!$targetLead) {
                    $targetLead = Lead::create([
                        'lead_code'      => Lead::generateLeadCode(),
                        'customer_id'    => $customer->id,
                        'company_name'   => $customer->company_name,
                        'pic_name'       => $customer->pic_name,
                        'pic_position'   => $customer->pic_position,
                        'phone'          => $customer->phone,
                        'email'          => $customer->email,
                        'address'        => $customer->address,
                        'industry'       => $customer->industry,
                        'location'       => $customer->location,
                        'pipeline_stage' => $customer->status === 'Existing' ? 'Maintaining' : 'Identifying',
                        'temperature'    => 'Warm',
                        'user_id'        => $customer->user_id ?: auth()->id(),
                    ]);
                }
            }
        }

        if ($request->filled('pipeline_stage') && $targetLead) {
            // Customer existing dibatasi: Follow Up, Won, Maintaining.
            // Lead biasa: semua stage (sudah divalidasi).
            $relatedCustomer = $customer;
            if (!$relatedCustomer && $targetLead->customer_id) {
                $relatedCustomer = Customer::find($targetLead->customer_id);
            }
            $isExisting = $relatedCustomer && $relatedCustomer->status === 'Existing';
            $requested  = $request->pipeline_stage;

            if ($isExisting) {
                $allowed  = ['Follow Up', 'Won', 'Maintaining'];
                $newStage = in_array($requested, $allowed, true) ? $requested : 'Maintaining';
            } else {
                $newStage = $requested;
            }

            $targetLead->update(['pipeline_stage' => $newStage]);
            LeadsController::syncToCustomer($targetLead->fresh());
        }

        unset($validated['photo'], $validated['client_ref']);
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $validated['photo'] = self::compressAndStore($request->file('photo'));
        }

        if ($targetLead && empty($validated['lead_id'])) {
            $validated['lead_id'] = $targetLead->id;
        }

        Activity::create($validated);
        return redirect()->back()->with('success', 'Aktivitas berhasil disimpan.');
    }

    /**
     * Compress image menggunakan GD, simpan ke storage/app/public/activity-photos
     * Target: file size ≤ 500KB. Iterasi quality dari 85 turun ke 40.
     */
    private static function compressAndStore(\Illuminate\Http\UploadedFile $file): string
    {
        $maxBytes  = 500 * 1024; // 500 KB
        $mime      = $file->getMimeType();
        $src       = null;

        // Load image sesuai tipe
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $src = @imagecreatefromjpeg($file->getRealPath());
        } elseif ($mime === 'image/png') {
            $src = @imagecreatefrompng($file->getRealPath());
        } elseif ($mime === 'image/webp') {
            $src = @imagecreatefromwebp($file->getRealPath());
        }

        // Fallback: simpan as-is jika GD gagal
        if (!$src) {
            return $file->store('activity-photos', 'public');
        }

        // Resize jika lebar > 1200px (pertahankan rasio)
        $origW = imagesx($src);
        $origH = imagesy($src);
        $maxW  = 1200;
        if ($origW > $maxW) {
            $newH = (int) round($origH * ($maxW / $origW));
            $dst  = imagecreatetruecolor($maxW, $newH);
            // Pertahankan transparansi PNG
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $newH, $origW, $origH);
            imagedestroy($src);
            $src = $dst;
        }

        // Tentukan nama file & path
        $filename  = 'activity-photos/' . \Illuminate\Support\Str::random(40) . '.jpg';
        $storagePath = storage_path('app/public/' . $filename);

        // Pastikan direktori ada
        if (!is_dir(dirname($storagePath))) {
            mkdir(dirname($storagePath), 0755, true);
        }

        // Iterasi quality hingga ukuran ≤ 500KB
        $quality = 85;
        do {
            ob_start();
            imagejpeg($src, null, $quality);
            $data = ob_get_clean();
            $quality -= 10;
        } while (strlen($data) > $maxBytes && $quality >= 30);

        file_put_contents($storagePath, $data);
        imagedestroy($src);

        return $filename;
    }
}
