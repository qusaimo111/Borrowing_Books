<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    // عرض إشعارات المستخدم الحالي
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->get()->map(function ($n) {
            return [
                'id'         => $n->id,
                'title'      => $n->data['title'] ?? '',
                'body'       => $n->data['body'] ?? '',
                'data'       => $n->data,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        return response()->json([
            'status'        => true,
            'unread_count'  => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    // تحديد إشعار كمقروء
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديد الإشعار كمقروء',
        ]);
    }

    // فحص وإرسال إشعارات المدة المتبقية لإرجاع الكتب (ممكن ينادى من Cron Job أو مباشرة للمعاينة بالمناقشة)
    public function checkReturnReminders(Request $request)
    {
        $today = Carbon::today();
        $items = OrderItem::with(['order.user', 'book'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['sent', 'received']);
            })
            ->whereDate('return_date', '>=', $today)
            ->get();

        $sentCount = 0;

        foreach ($items as $item) {
            $daysLeft = $today->diffInDays(Carbon::parse($item->return_date), false);
            $user = $item->order->user;

            DB::table('notifications')->insert([
                'id'              => Str::uuid()->toString(),
                'type'            => 'App\\Notifications\\ReturnReminder',
                'notifiable_type' => get_class($user),
                'notifiable_id'   => $user->id,
                'data'            => json_encode([
                    'title'       => 'تذكير بموعد إرجاع كتاب',
                    'body'        => "متبقي {$daysLeft} يوم لإرجاع كتاب '{$item->book->title}' (الموعد: {$item->return_date})",
                    'order_id'    => $item->order_id,
                    'book_id'     => $item->book_id,
                    'days_left'   => $daysLeft,
                    'return_date' => $item->return_date,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $sentCount++;
        }

        return response()->json([
            'status'  => true,
            'message' => "تم إرسال {$sentCount} إشعار تذكير بمواعيد الإرجاع",
        ]);
    }
}