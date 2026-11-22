<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    // عرض الكتب مع دعم البحث (باسم الكتاب، المؤلف، أو التصنيف) والفلترة حسب التصنيف
    public function index(Request $request)
    {
        $query = Book::with('category');

        // فلترة حسب التصنيف
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // عرض الكتب المتوفرة فقط (اختياري)
        if ($request->boolean('available_only')) {
            $query->where('quantity', '>', 0);
        }

        // البحث باسم الكتاب أو المؤلف أو اسم التصنيف (عربي/إنكليزي)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('author', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($catQuery) use ($search) {
                      $catQuery->where('name_ar', 'like', "%{$search}%")
                               ->orWhere('name_en', 'like', "%{$search}%");
                  });
            });
        }

        $books = $query->latest()->get();

        return response()->json([
            'status' => true,
            'books' => $books,
        ]);
    }

    // عرض تفاصيل كتاب محدد
    public function show($id)
    {
        $book = Book::with('category')->find($id);

        if (!$book) {
            return response()->json([
                'status' => false,
                'message' => 'الكتاب غير موجود',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'book' => $book,
        ]);
    }

    // إضافة كتاب جديد (للأدمن فقط)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title'       => 'required|string|max:255',
            'author'      => 'required|string|max:255',
            'quantity'    => 'required|integer|min:0',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('books', 'public');
        }

        $book = Book::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة الكتاب بنجاح',
            'book' => $book->load('category'),
        ], 201);
    }

    // تعديل كتاب (للأدمن فقط)
    public function update(Request $request, $id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'status' => false,
                'message' => 'الكتاب غير موجود',
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'title'       => 'sometimes|string|max:255',
            'author'      => 'sometimes|string|max:255',
            'quantity'    => 'sometimes|integer|min:0',
            'price'       => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($book->image) {
                Storage::disk('public')->delete($book->image);
            }
            $validated['image'] = $request->file('image')->store('books', 'public');
        }

        $book->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل الكتاب بنجاح',
            'book' => $book->load('category'),
        ]);
    }

    // حذف كتاب (للأدمن فقط)
    public function destroy($id)
    {
        $book = Book::find($id);

        if (!$book) {
            return response()->json([
                'status' => false,
                'message' => 'الكتاب غير موجود',
            ], 404);
        }

        if ($book->image) {
            Storage::disk('public')->delete($book->image);
        }

        $book->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الكتاب بنجاح',
        ]);
    }
}