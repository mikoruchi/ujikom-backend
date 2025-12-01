<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Film;
use App\Models\User;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OwnerDashboardController extends Controller
{
    public function getDashboardStats(Request $request)
    {
        try {
            $period = $request->get('period', 'week');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            
            // Jika custom date range dipilih
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();
                $period = 'custom';
            } else {
                // Default period
                switch ($period) {
                    case 'week':
                        $start = Carbon::now()->startOfWeek();
                        $end = Carbon::now()->endOfWeek();
                        break;
                    case 'month':
                        $start = Carbon::now()->startOfMonth();
                        $end = Carbon::now()->endOfMonth();
                        break;
                    case 'year':
                        $start = Carbon::now()->startOfYear();
                        $end = Carbon::now()->endOfYear();
                        break;
                    default:
                        $start = Carbon::now()->startOfWeek();
                        $end = Carbon::now()->endOfWeek();
                }
            }

            // Stats utama dengan penanganan tanggal yang lebih baik
            $todayRevenue = Payment::where('status', 'success')
                ->whereDate('created_at', Carbon::today())
                ->sum('total_amount');

            $monthlyRevenue = Payment::where('status', 'success')
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum('total_amount');

            // PERBAIKAN: Gunakan whereBetween dengan penanganan timezone
            $periodRevenue = Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            $totalTickets = Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->sum('ticket_count');

            $totalCustomers = Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->distinct('user_id')
                ->count('user_id');

            // Data penjualan untuk chart
            $salesData = $this->getSalesData($start, $end, $period);

            // Film terlaris untuk bar chart
            $topMovies = $this->getTopMovies($start, $end);

            // Data untuk grafik batang film per tanggal
            $movieBarChartData = $this->getMovieBarChartData($start, $end, $period);

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'todayRevenue' => $todayRevenue,
                        'monthlyRevenue' => $monthlyRevenue,
                        'periodRevenue' => $periodRevenue,
                        'totalTickets' => $totalTickets,
                        'totalCustomers' => $totalCustomers,
                        'period' => $period,
                        'startDate' => $start->format('Y-m-d'),
                        'endDate' => $end->format('Y-m-d')
                    ],
                    'salesData' => $salesData,
                    'topMovies' => $topMovies,
                    'movieBarChartData' => $movieBarChartData,
                    'periodLabel' => $this->getPeriodLabel($period, $start, $end)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting dashboard stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dashboard: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getSalesData($start, $end, $period)
    {
        // PERBAIKAN: Gunakan DATE() untuk memastikan perbandingan tanggal tanpa waktu
        if ($period === 'week' || $period === 'custom') {
            // Data harian
            return Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as date, 
                           SUM(total_amount) as revenue, 
                           SUM(ticket_count) as tickets,
                           COUNT(*) as transactions')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(function($item) {
                    return [
                        'date' => $item->date,
                        'revenue' => (float) $item->revenue,
                        'tickets' => (int) $item->tickets,
                        'transactions' => (int) $item->transactions
                    ];
                });
        } elseif ($period === 'month') {
            // Data mingguan dalam bulan
            return Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('YEAR(created_at) as year, 
                           WEEK(created_at, 1) as week,
                           MIN(DATE(created_at)) as start_date,
                           MAX(DATE(created_at)) as end_date,
                           SUM(total_amount) as revenue, 
                           SUM(ticket_count) as tickets,
                           COUNT(*) as transactions')
                ->groupBy('year', 'week')
                ->orderBy('year')
                ->orderBy('week')
                ->get()
                ->map(function($item) {
                    return [
                        'date' => "Minggu {$item->week} ({$item->start_date} - {$item->end_date})",
                        'revenue' => (float) $item->revenue,
                        'tickets' => (int) $item->tickets,
                        'transactions' => (int) $item->transactions
                    ];
                });
        } else {
            // Data bulanan dalam tahun
            return Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('YEAR(created_at) as year, 
                           MONTH(created_at) as month,
                           SUM(total_amount) as revenue, 
                           SUM(ticket_count) as tickets,
                           COUNT(*) as transactions')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function($item) {
                    $monthName = Carbon::create()->month($item->month)->format('F');
                    return [
                        'date' => "{$monthName} {$item->year}",
                        'revenue' => (float) $item->revenue,
                        'tickets' => (int) $item->tickets,
                        'transactions' => (int) $item->transactions
                    ];
                });
        }
    }

    private function getTopMovies($start, $end)
    {
        return Payment::where('payments.status', 'success')
            ->whereBetween('payments.created_at', [$start, $end])
            ->join('films', 'payments.film_id', '=', 'films.id')
            ->selectRaw('films.title, 
                       SUM(payments.ticket_count) as tickets,
                       SUM(payments.total_amount) as revenue,
                       COUNT(*) as transactions')
            ->groupBy('films.id', 'films.title')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'title' => $item->title,
                    'tickets' => (int) $item->tickets,
                    'revenue' => (float) $item->revenue,
                    'transactions' => (int) $item->transactions
                ];
            });
    }

    private function getMovieBarChartData($start, $end, $period)
    {
        if ($period === 'week' || $period === 'custom') {
            // Data harian dengan detail film
            return Payment::where('payments.status', 'success')
                ->whereBetween('payments.created_at', [$start, $end])
                ->join('films', 'payments.film_id', '=', 'films.id')
                ->selectRaw('DATE(payments.created_at) as date,
                           films.title,
                           SUM(payments.total_amount) as revenue,
                           SUM(payments.ticket_count) as tickets')
                ->groupBy('date', 'films.title')
                ->orderBy('date')
                ->orderByDesc('revenue')
                ->get()
                ->groupBy('date')
                ->map(function($dayData, $date) {
                    return [
                        'date' => $date,
                        'movies' => $dayData->take(5)->map(function($movie) {
                            return [
                                'title' => $movie->title,
                                'revenue' => (float) $movie->revenue,
                                'tickets' => (int) $movie->tickets
                            ];
                        })->toArray(),
                        'totalRevenue' => $dayData->sum('revenue')
                    ];
                })->values();
        } else {
            // Untuk bulan dan tahun, kelompokkan per minggu/bulan
            return Payment::where('payments.status', 'success')
                ->whereBetween('payments.created_at', [$start, $end])
                ->join('films', 'payments.film_id', '=', 'films.id')
                ->selectRaw('films.title,
                           SUM(payments.total_amount) as revenue,
                           SUM(payments.ticket_count) as tickets')
                ->groupBy('films.title')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->map(function($item) {
                    return [
                        'title' => $item->title,
                        'revenue' => (float) $item->revenue,
                        'tickets' => (int) $item->tickets
                    ];
                });
        }
    }

    private function getPeriodLabel($period, $start, $end)
    {
        switch ($period) {
            case 'week':
                return "Minggu Ini ({$start->format('d M')} - {$end->format('d M Y')})";
            case 'month':
                return "Bulan Ini ({$start->format('F Y')})";
            case 'year':
                return "Tahun Ini ({$start->format('Y')})";
            case 'custom':
                return "Custom ({$start->format('d M Y')} - {$end->format('d M Y')})";
            default:
                return "Minggu Ini";
        }
    }

    // Method lainnya tetap sama...
    public function getExportData(Request $request)
    {
        try {
            $period = $request->get('period', 'week');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            
            // Logic yang sama dengan getDashboardStats untuk menentukan tanggal
            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate)->startOfDay();
                $end = Carbon::parse($endDate)->endOfDay();
            } else {
                switch ($period) {
                    case 'week':
                        $start = Carbon::now()->startOfWeek();
                        $end = Carbon::now()->endOfWeek();
                        break;
                    case 'month':
                        $start = Carbon::now()->startOfMonth();
                        $end = Carbon::now()->endOfMonth();
                        break;
                    case 'year':
                        $start = Carbon::now()->startOfYear();
                        $end = Carbon::now()->endOfYear();
                        break;
                    default:
                        $start = Carbon::now()->startOfWeek();
                        $end = Carbon::now()->endOfWeek();
                }
            }

            $todayRevenue = Payment::where('status', 'success')
                ->whereDate('created_at', Carbon::today())
                ->sum('total_amount');

            $monthlyRevenue = Payment::where('status', 'success')
                ->whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum('total_amount');

            $yearlyRevenue = Payment::where('status', 'success')
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('total_amount');

            $periodRevenue = Payment::where('status', 'success')
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'periodLabel' => $this->getPeriodLabel($period, $start, $end),
                    'exportTime' => now(),
                    'summary' => [
                        'todayRevenue' => $todayRevenue,
                        'monthlyRevenue' => $monthlyRevenue,
                        'yearlyRevenue' => $yearlyRevenue,
                        'periodRevenue' => $periodRevenue
                    ],
                    'dailyData' => $this->getSalesData($start, $end, 'custom'),
                    'weeklyData' => $this->getSalesData($start, $end, 'month'),
                    'monthlyData' => $this->getSalesData($start, $end, 'year'),
                    'topMovies' => $this->getTopMovies($start, $end)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting export data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data export'
            ], 500);
        }
    }

    public function getDetailedStats(Request $request)
    {
        try {
            $period = $request->get('period', 'week');
            
            $query = Payment::where('status', 'success');
            
            switch ($period) {
                case 'month':
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now()->endOfMonth();
                    break;
                case 'year':
                    $startDate = Carbon::now()->startOfYear();
                    $endDate = Carbon::now()->endOfYear();
                    break;
                default: // week
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
            }
            
            $detailedStats = $query->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as transaction_count'),
                    DB::raw('SUM(total_amount) as total_revenue'),
                    DB::raw('SUM(ticket_count) as total_tickets')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $detailedStats
            ]);

        } catch (\Exception $e) {
            \Log::error('Detailed stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data statistik detail'
            ], 500);
        }
    }
}