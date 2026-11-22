<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // عرض كل التصنيفات
    public function index()
    {
        $categories = Category::withCount('books')->get();

        return response()->json([
            'status' => true,
            'categories' => $categories,
        ]);
    }

    // إضافة تصنيف جديد (للأدمن فقط)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
        ]);

        $category = Category::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة التصنيف بنجاح',
            'category' => $category,
        ], 201);
    }

    // عرض الكتب المتوفرة ضمن تصنيف معين (الطلب الإجباري رقم 4)
    public function books($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'التصنيف غير موجود',
            ], 404);
        }

        $books = $category->books()
            ->where('quantity', '>', 0)
            ->get();

        return response()->json([
            'status' => true,
            'category' => $category,
            'books' => $books,
        ]);
    }
}