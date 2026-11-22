<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. حساب صاحب المكتبة (Admin)
        User::updateOrCreate(
            ['phone' => '0999999999'],
            [
                'name'     => 'صاحب المكتبة',
                'password' => '123456',
                'role'     => 'admin',
                'location' => 'دمشق',
            ]
        );

        // 2. حساب مستعير تجريبي (User)
        $user = User::updateOrCreate(
            ['phone' => '0988888888'],
            [
                'name'     => 'أحمد المستعير',
                'password' => '123456',
                'role'     => 'user',
                'location' => 'دمشق - المزة',
            ]
        );

        // 3. التصنيفات
        $novels = Category::firstOrCreate(['name_en' => 'Novels'], ['name_ar' => 'روايات']);
        $tech   = Category::firstOrCreate(['name_en' => 'Programming & Tech'], ['name_ar' => 'برمجة وتقنية']);
        $hist   = Category::firstOrCreate(['name_en' => 'History'], ['name_ar' => 'تاريخ']);
        $self   = Category::firstOrCreate(['name_en' => 'Self Development'], ['name_ar' => 'تطوير ذات']);

        // 4. كتب تجريبية
        $book1 = Book::create([
            'category_id' => $novels->id,
            'title'       => 'الخيميائي',
            'author'      => 'باولو كويلو',
            'quantity'    => 10,
            'price'       => 5000,
            'description' => 'رواية فلسفية مشهورة عن تحقيق الأحلام.',
        ]);

        $book2 = Book::create([
            'category_id' => $tech->id,
            'title'       => 'Clean Code',
            'author'      => 'Robert C. Martin',
            'quantity'    => 7,
            'price'       => 12000,
            'description' => 'دليل عملي لكتابة كود برمجي نظيف وقابل للصيانة.',
        ]);

        Book::create([
            'category_id' => $hist->id,
            'title'       => 'مقدمة ابن خلدون',
            'author'      => 'ابن خلدون',
            'quantity'    => 5,
            'price'       => 8000,
            'description' => 'كتاب تأسيسي في علم الاجتماع والتاريخ.',
        ]);

        Book::create([
            'category_id' => $self->id,
            'title'       => 'العادات الذرية',
            'author'      => 'جيمس كلير',
            'quantity'    => 15,
            'price'       => 6500,
            'description' => 'طريقة عملية لبناء عادات إيجابية والتخلص من السلبية.',
        ]);

        // 5. طلب استعارة تجريبي
        $order = Order::create([
            'user_id'        => $user->id,
            'location'       => 'دمشق - المزة',
            'payment_info'   => 'الدفع عند الاستلام',
            'status'         => 'preparing',
            'payment_status' => 'unpaid',
            'total_price'    => 17000,
            'stock_deducted' => false,
        ]);

        $order->items()->createMany([
            [
                'book_id'     => $book1->id,
                'quantity'    => 1,
                'price'       => $book1->price,
                'return_date' => now()->addDays(14)->toDateString(),
            ],
            [
                'book_id'     => $book2->id,
                'quantity'    => 1,
                'price'       => $book2->price,
                'return_date' => now()->addDays(20)->toDateString(),
            ],
        ]);
    }
}