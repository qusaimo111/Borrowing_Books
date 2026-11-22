<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // التقرير الأول: ملخص الطلبيات وعدد الكتب المستعارة خلال فترة زمنية
    public function ordersSummary(Request $request)
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $user = $request->user();
        $from = $validated['from_date'] . ' 00:00:00';
        $to   = $validated['to_date'] . ' 23:59:59';

        $ordersQuery = Order::whereBetween('created_at', [$from, $to]);

        if ($user->role !== 'admin') {
            $ordersQuery->where('user_id', $user->id);
        }

        $orderIds = (clone $ordersQuery)->pluck('id');

        $totalOrders = $orderIds->count();
        $totalBooksBorrowed = OrderItem::whereIn('order_id', $orderIds)->sum('quantity');
        $totalRevenue = (clone $ordersQuery)->sum('total_price');

        $statusBreakdown = (clone $ordersQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'status' => true,
            'period' => [
                'from' => $validated['from_date'],
                'to'   => $validated['to_date'],
            ],
            'report' => [
                'total_orders'         => $totalOrders,
                'total_books_borrowed' => (int) $totalBooksBorrowed,
                'total_revenue'        => (float) $totalRevenue,
                'status_breakdown'     => $statusBreakdown,
            ],
        ]);
    }

    // التقرير الثاني: تفاصيل الكتب المستعارة والأكثر طلباً خلال فترة زمنية
    public function topBooks(Request $request)
    {
        $validated = $request->validate([
            'from_date' => 'required|date',
            'to_date'   => 'required|date|after_or_equal:from_date',
        ]);

        $user = $request->user();
        $from = $validated['from_date'] . ' 00:00:00';
        $to   = $validated['to_date'] . ' 23:59:59';

        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('books', 'order_items.book_id', '=', 'books.id')
            ->whereBetween('orders.created_at', [$from, $to]);

        if ($user->role !== 'admin') {
            $query->where('orders.user_id', $user->id);
        }

        $books = $query->select(
                'books.id',
                'books.title',
                'books.author',
                DB::raw('SUM(order_items.quantity) as total_borrowed_quantity'),
                DB::raw('COUNT(DISTINCT orders.id) as orders_count')
            )
            ->groupBy('books.id', 'books.title', 'books.author')
            ->orderByDesc('total_borrowed_quantity')
            ->get();

        return response()->json([
            'status' => true,
            'period' => [
                'from' => $validated['from_date'],
                'to'   => $validated['to_date'],
            ],
            'books' => $books,
        ]);
    }
}