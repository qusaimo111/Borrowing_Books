<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // عرض قائمة الكتب المفضلة للمستخدم
    public function index(Request $request)
    {
        $favorites =$request->user()
            ->favorites()
            ->with('category')
            ->get();

        return response()->json([
            'status' => true,
            'favorites' => $favorites,
        ]);
    }

    // إضافة أو إزالة كتاب من المفضلة (Toggle)
    public function toggle(Request $request,$bookId)
    {
        $book = Book::find($bookId);

        if (!$book) {
            return response()->json([
                'status' => false,
                'message' => 'الكتاب غير موجود',
            ], 404);
        }

        $result = $request->user()->favorites()->toggle($book->id);
        $isFavorited = count($result['attached']) > 0;

        return response()->json([
            'status' => true,
            'message' => $isFavorited ? 'تمت الإضافة إلى المفضلة' : 'تمت الإزالة من المفضلة',
            'is_favorited' => $isFavorited,
        ]);
    }
}