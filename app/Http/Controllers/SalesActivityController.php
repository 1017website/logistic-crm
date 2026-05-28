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

        // Pipeline summary for sidebar
        $pipelineSummary = [
            'Identifying' => ['count' => Lead::where('pipeline_stage', 'Identifying')->count(), 'value' => Lead::where('pipeline_stage', 'Identifying')->sum('potensi_revenue')],
            'Approaching' => ['count' => Lead::where('pipeline_stage', 'Approaching')->count(), 'value' => Lead::where('pipeline_stage', 'Approaching')->sum('potensi_revenue')],
            'Follow Up'   => ['count' => Lead::where('pipeline_stage', 'Follow Up')->count(), 'value' => Lead::where('pipeline_stage', 'Follow Up')->sum('potensi_revenue')],
            'Won/Closing' => ['count' => Lead::where('pipeline_stage', 'Won')->count(), 'value' => Lead::where('pipeline_stage', 'Won')->sum('potensi_revenue')],
            'Maintaining' => ['count' => Lead::where('pipeline_stage', 'Maintaining')->count(), 'value' => Lead::where('pipeline_stage', 'Maintaining')->sum('potensi_revenue')],
        ];

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
            'type'           => 'required|in:Call,Visit,Email,Note,Others',
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'activity_at'    => 'required|date',
            'status'         => 'required|in:Done,Pending,Planned,Overdue',
            'next_follow_up' => 'nullable|date',
            'pipeline_stage' => 'nullable|in:Identifying,Approaching,Follow Up,Won,Lost,Maintaining',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        // Selalu pakai auth user
        $validated['user_id']       = auth()->id();
        $validated['sales_user_id'] = $validated['sales_user_id'] ?? auth()->id();

        // Resolusi target: lead langsung, atau lead yang terhubung ke customer.
        // Revisi #3: activity bisa dari (lead / customer potential) & (customer existing).
        $targetLead = null;

        if (!empty($validated['lead_id'])) {
            $targetLead = Lead::find($validated['lead_id']);
        } elseif (!empty($validated['customer_id'])) {
            $customer = Customer::find($validated['customer_id']);
            // Cari lead terbaru yang terhubung ke customer ini untuk update stage.
            if ($customer) {
                $targetLead = Lead::where('customer_id', $customer->id)
                    ->orderByDesc('updated_at')
                    ->first();
            }
        }

        // Update pipeline_stage bila dikirim & ada lead target.
        // Revisi #4 & #5: validasi stage sesuai sumber dilakukan di server.
        if ($request->filled('pipeline_stage') && $targetLead) {
            $requested  = $request->pipeline_stage;
            $isExisting = !empty($validated['customer_id'])
                && optional(Customer::find($validated['customer_id']))->status === 'Existing';

            $allowed = $isExisting
                ? ['Follow Up', 'Won', 'Lost', 'Maintaining']                                  // customer existing
                : ['Identifying', 'Approaching', 'Follow Up', 'Won', 'Lost', 'Maintaining'];   // lead / customer potential

            if (in_array($requested, $allowed, true)) {
                $targetLead->update(['pipeline_stage' => $requested]);
                \App\Http\Controllers\LeadsController::syncToCustomer($targetLead->fresh());
            }
        }

        // Foto: compress ke maks 500KB sebelum simpan
        unset($validated['photo'], $validated['pipeline_stage']);
        if ($request->hasFile('photo') && $request->file('photo')->isValid()) {
            $validated['photo'] = self::compressAndStore($request->file('photo'));
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
