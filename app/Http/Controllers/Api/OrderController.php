<?php

namespace App\Http\Controllers\Api;
use App\Models\User;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // عرض الطلبات (الأدمن بيشوف كل الطلبات، والمستعير بيشوف طلباته فقط)
    public function index(Request $request)
    {
        $user =$request->user();

        $query = Order::with(['user:id,name,phone', 'items.book:id,title,author,price'])
            ->latest();

        if ($user->role !== 'admin') {
            $query->where('user_id',$user->id);
        }

        return response()->json([
            'status' => true,
            'orders' => $query->get(),
        ]);
    }

    // عرض تفاصيل طلب محدد
    public function show(Request $request,$id)
    {
        $order = Order::with(['user:id,name,phone', 'items.book'])->find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        if ($request->user()->role !== 'admin' && $order->user_id !==$request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بعرض هذا الطلب',
            ], 403);
        }

        return response()->json([
            'status' => true,
            'order' => $order,
        ]);
    }

    // إنشاء طلب استعارة جديد (عدة كتب مع موعد إرجاع لكل كتاب)
    public function store(Request $request)
    {
        $validated =$request->validate([
            'location'            => 'required|string|max:255',
            'payment_info'        => 'nullable|string|max:255',
            'items'               => 'required|array|min:1',
            'items.*.book_id'     => 'required|exists:books,id',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.return_date' => 'required|date|after:today',
        ]);

        try {
            $order = DB::transaction(function () use ($request, $validated) {$totalPrice = 0;
                $orderItemsData = [];

                foreach ($validated['items'] as$item) {
                    $book = Book::findOrFail($item['book_id']);

                    if ($book->quantity <$item['quantity']) {
                        throw new \Exception("الكمية المطلوبة من كتاب '{$book->title}' غير متوفرة حالياً (المتوفر: {$book->quantity})");
                    }

                    $lineTotal = $book->price * $item['quantity'];
                    $totalPrice +=$lineTotal;

                    $orderItemsData[] = [
                        'book_id'     => $book->id,
                        'quantity'    => $item['quantity'],
                        'price'       => $book->price,
                        'return_date' => $item['return_date'],
                    ];
                }

                $order = Order::create([
                    'user_id'        => $request->user()->id,
                    'location'       => $validated['location'],
                    'payment_info'   => $validated['payment_info'] ?? null,
                    'status'         => 'preparing',
                    'payment_status' => 'unpaid',
                    'total_price'    => $totalPrice,
                    'stock_deducted' => false,
                ]);

                $order->items()->createMany($orderItemsData);

                return $order->load('items.book');
            });
            // إرسال إشعار لصاحب المكتبة بطلب استعارة جديد
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                DB::table('notifications')->insert([
                    'id'              => Str::uuid()->toString(),
                    'type'            => 'App\\Notifications\\NewOrder',
                    'notifiable_type' => User::class,
                    'notifiable_id'   => $admin->id,
                    'data'            => json_encode([
                        'title'    => 'طلب استعارة جديد',
                        'body'     => "طلب جديد رقم #{$order->id} من {$request->user()->name}",
                        'order_id' => $order->id,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
            return response()->json([
                'status'  => true,
                'message' => 'تم إنشاء طلب الاستعارة بنجاح',
                'order'   => $order, ], 201); } catch (\Exception$e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // تغيير حالة الطلب (preparing, sent, received) + خصم المخزون أوتوماتيكياً عند sent
    public function updateStatus(Request $request,$id)
    {
        $validated =$request->validate([
            'status' => 'required|in:preparing,sent,received',
        ]);

        $order = Order::with('items')->find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        // السماح للأدمن أو لصاحب الطلب نفسه (حسب نص المتطلبات)
        if ($request->user()->role !== 'admin' && $order->user_id !==$request->user()->id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بتعديل حالة هذا الطلب',
            ], 403);
        }

        try {
            DB::transaction(function () use ($order,$validated) {
                // إذا صارت الحالة "تم الإرسال" والمخزون لسا ما انخصم -> اخصم الكميات أوتوماتيكياً
                if ($validated['status'] === 'sent' && !$order->stock_deducted) {
                    foreach ($order->items as$item) {
                        $book = Book::lockForUpdate()->findOrFail($item->book_id);

                        if ($book->quantity <$item->quantity) {
                            throw new \Exception("لا يوجد كمية كافية في المخزون للكتاب: {$book->title}");
                        }

                        $book->decrement('quantity',$item->quantity);
                    }

                    $order->stock_deducted = true;
                }

                $order->status = $validated['status'];$order->save();
            });

            return response()->json([
                'status'  => true,
                'message' => 'تم تحديث حالة الطلب بنجاح',
                'order'   => $order->fresh()->load('items.book'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // تغيير حالة الدفع (unpaid, paid) - لصاحب المكتبة فقط
    public function updatePaymentStatus(Request $request,$id)
    {
        $validated =$request->validate([
            'payment_status' => 'required|in:unpaid,paid',
        ]);

        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'الطلب غير موجود',
            ], 404);
        }

        $order->update(['payment_status' =>$validated['payment_status']]);

        return response()->json([
            'status'  => true,
            'message' => 'تم تحديث حالة الدفع بنجاح',
            'order'   => $order,
        ]);
    }
}