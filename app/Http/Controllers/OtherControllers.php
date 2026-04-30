<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\Vendor;
use App\Models\VendorRate;
use App\Models\DeliveryOrder;
use App\Models\SalesUser;
use App\Models\Activity;
use Illuminate\Http\Request;
use Carbon\Carbon;

// =========================================
class PipelineController extends Controller
{
    public function index(Request $request)
    {
        $stages = ['Identifying', 'Approaching', 'Follow Up', 'Closing', 'Won'];
        $pipeline = [];
        foreach ($stages as $stage) {
            $pipeline[$stage] = Lead::where('pipeline_stage', $stage)
                ->with(['customer', 'salesUser'])
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        $totalValue     = Lead::whereNotIn('pipeline_stage', ['Lost'])->sum('potensi_revenue');
        $totalLeads     = Lead::count();
        $potentialDeals = Lead::whereIn('pipeline_stage', ['Follow Up', 'Closing'])->count();
        $winRate        = $totalLeads > 0 ? round((Lead::where('pipeline_stage', 'Won')->count() / $totalLeads) * 100, 1) : 0;
        $expectedRevenue = Lead::where('pipeline_stage', 'Won')->sum('potensi_revenue');

        $topSales = SalesUser::withCount(['leads as expected_revenue' => function ($q) {
            $q->selectRaw('sum(potensi_revenue)');
        }])->get()->sortByDesc('expected_revenue')->take(5);

        return view('pipeline.index', compact(
            'pipeline', 'totalValue', 'totalLeads', 'potentialDeals', 'winRate', 'expectedRevenue', 'topSales'
        ));
    }
}

// =========================================
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        $industry = $request->get('industry');
        $search = $request->get('search');

        $query = Customer::with(['salesUser', 'deliveryOrders', 'activities']);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($industry && $industry !== 'all') $query->where('industry', $industry);
        if ($search) $query->where(function ($q) use ($search) {
            $q->where('company_name', 'like', "%$search%")
              ->orWhere('pic_name', 'like', "%$search%");
        });

        $customers = $query->orderBy('company_name')->paginate(10);

        $totalCustomer   = Customer::count();
        $potentialCustomer = Customer::where('status', 'Potential')->count();
        $existingCustomer  = Customer::where('status', 'Existing')->count();
        $industries = Customer::distinct()->pluck('industry');

        $selectedCustomer = $request->get('selected_id')
            ? Customer::with(['salesUser', 'deliveryOrders', 'activities', 'leads'])->find($request->get('selected_id'))
            : Customer::with(['salesUser', 'deliveryOrders', 'activities', 'leads'])->first();

        return view('customers.index', compact(
            'customers', 'totalCustomer', 'potentialCustomer', 'existingCustomer',
            'industries', 'selectedCustomer', 'status', 'industry', 'search'
        ));
    }

    public function show(Customer $customer)
    {
        $customer->load(['salesUser', 'deliveryOrders', 'activities.salesUser', 'leads', 'quotations']);
        return view('customers.show', compact('customer'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name'  => 'required|string|max:255',
            'pic_name'      => 'required|string|max:255',
            'phone'         => 'required|string',
            'email'         => 'nullable|email',
            'industry'      => 'nullable|string',
            'location'      => 'nullable|string',
            'status'        => 'required|in:Existing,Potential',
            'sales_user_id' => 'required|exists:sales_users,id',
        ]);
        Customer::create($validated);
        return redirect()->route('customers.index')->with('success', 'Customer berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($request->validate([
            'company_name'  => 'sometimes|string|max:255',
            'pic_name'      => 'sometimes|string|max:255',
            'phone'         => 'sometimes|string',
            'status'        => 'sometimes|in:Existing,Potential',
        ]));
        return redirect()->back()->with('success', 'Data customer diupdate.');
    }
}

// =========================================
class VendorController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->get('vendor_type');
        $status = $request->get('status');
        $search = $request->get('search');

        $query = Vendor::with(['deliveryOrders', 'rates']);
        if ($type && $type !== 'all') $query->where('vendor_type', $type);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($search) $query->where(function ($q) use ($search) {
            $q->where('vendor_name', 'like', "%$search%")
              ->orWhere('pic_name', 'like', "%$search%");
        });

        $vendors = $query->orderBy('is_preferred', 'desc')->orderBy('rating', 'desc')->paginate(10);

        $totalVendor     = Vendor::count();
        $activeVendor    = Vendor::where('status', 'Active')->count();
        $nonActiveVendor = Vendor::where('status', 'Non-Active')->count();
        $preferredVendor = Vendor::where('is_preferred', true)->count();

        $selectedVendor = $request->get('selected_id')
            ? Vendor::with(['deliveryOrders', 'rates'])->find($request->get('selected_id'))
            : Vendor::with(['deliveryOrders', 'rates'])->first();

        return view('vendors.index', compact(
            'vendors', 'totalVendor', 'activeVendor', 'nonActiveVendor',
            'preferredVendor', 'selectedVendor', 'type', 'status', 'search'
        ));
    }
}

// =========================================
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', now()->endOfMonth()->toDateString());

        $revenue      = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR')->sum('amount');
        $grossProfit  = $revenue * 0.32;
        $nettProfit   = $revenue * 0.19;
        $dealsClosed  = Lead::where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $totalLeads   = Lead::count();
        $conversionRate = $totalLeads > 0 ? round(($dealsClosed / $totalLeads) * 100, 1) : 0;

        // Revenue by service
        $revenueByService = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('currency', 'IDR')
            ->selectRaw('service_type, SUM(amount) as total')
            ->groupBy('service_type')->get();

        // Pipeline funnel
        $funnel = [
            'Identifying' => Lead::where('pipeline_stage', 'Identifying')->count(),
            'Approaching' => Lead::where('pipeline_stage', 'Approaching')->count(),
            'Follow Up'   => Lead::where('pipeline_stage', 'Follow Up')->count(),
            'Closing'     => Lead::where('pipeline_stage', 'Closing')->count(),
            'Won'         => Lead::where('pipeline_stage', 'Won')->count(),
        ];

        $salesPerformance = SalesUser::withCount([
            'leads as deals_closed' => fn($q) => $q->where('pipeline_stage', 'Won'),
        ])->get()->map(function ($s) {
            $s->revenue = Lead::where('sales_user_id', $s->id)->where('pipeline_stage', 'Won')->sum('potensi_revenue');
            $s->conversion = Lead::where('sales_user_id', $s->id)->count() > 0
                ? round(($s->deals_closed / Lead::where('sales_user_id', $s->id)->count()) * 100, 1)
                : 0;
            return $s;
        })->sortByDesc('revenue');

        $topCustomers = Customer::with('deliveryOrders')->get()
            ->map(fn($c) => ['customer' => $c, 'revenue' => $c->total_revenue, 'deals' => $c->deliveryOrders->count()])
            ->sortByDesc('revenue')->take(6);

        $leadSources = Lead::selectRaw('lead_source, COUNT(*) as count')->groupBy('lead_source')->get();

        $revenueByRoute = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('currency', 'IDR')
            ->selectRaw('route, SUM(amount) as total')
            ->groupBy('route')->orderByDesc('total')->limit(5)->get();

        return view('analytics.index', compact(
            'revenue', 'grossProfit', 'nettProfit', 'dealsClosed', 'conversionRate',
            'revenueByService', 'funnel', 'salesPerformance', 'topCustomers',
            'leadSources', 'revenueByRoute', 'startDate', 'endDate'
        ));
    }
}

// =========================================
class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $startDate  = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate    = $request->get('end_date', now()->endOfMonth()->toDateString());
        $reportType = $request->get('report_type', 'Sales Report');
        $salesId    = $request->get('sales_user_id');

        $query = Lead::with(['customer', 'salesUser'])->whereBetween('created_at', [$startDate, $endDate]);
        if ($salesId) $query->where('sales_user_id', $salesId);
        $reportData = $query->orderBy('created_at', 'desc')->paginate(10);

        $revenue       = DeliveryOrder::whereBetween('order_date', [$startDate, $endDate])->where('currency', 'IDR')->sum('amount');
        $totalDeals    = Lead::where('pipeline_stage', 'Won')->whereBetween('updated_at', [$startDate, $endDate])->count();
        $avgDealValue  = $totalDeals > 0 ? $revenue / $totalDeals : 0;
        $totalLeads    = Lead::whereBetween('created_at', [$startDate, $endDate])->count();
        $conversionRate = $totalLeads > 0 ? round(($totalDeals / $totalLeads) * 100, 1) : 0;
        $winRate       = Lead::count() > 0 ? round((Lead::where('pipeline_stage', 'Won')->count() / Lead::count()) * 100, 1) : 0;

        $salesUsers = SalesUser::all();

        return view('reports.index', compact(
            'reportData', 'revenue', 'totalDeals', 'avgDealValue', 'conversionRate', 'winRate',
            'salesUsers', 'startDate', 'endDate', 'reportType', 'salesId'
        ));
    }
}
