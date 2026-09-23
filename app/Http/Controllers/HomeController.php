<?php

namespace App\Http\Controllers;
use App\Models\Product;
use App\Models\Category;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct()
    // {
    //     $this->middleware('auth');
    // }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // return view('frontend.home.index');
        return view('auth.login');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function adminHome()
    {
        // Get all categories
        $categories = Category::all();
    
        // Prepare an array to store category name and the total product stock (qty) for each category
        $categoriesWithTotalStock = $categories->map(function ($category) {
            // Calculate the total stock by summing the 'qty' column from the related products
            $totalStock = $category->products->sum('qty');
            
            // Return an object or array with the category name and total stock
            return [
                'name' => $category->name,
                'total_stock' => $totalStock,
            ];
        });
    
        // Pass the data to the view
        return view('admin.home.index', ['categoriesWithTotalStock' => $categoriesWithTotalStock]);
    }
    // public function userHome()
    // {
    //     return view('user.home.index');
    // }
}
