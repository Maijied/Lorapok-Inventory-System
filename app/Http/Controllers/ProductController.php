<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use RealRashid\SweetAlert\Facades\Alert;

class ProductController extends Controller
{
    public function index()
    {
        $brands= Brand::get();
        $categories= Category::get();
        $products = Product::with('category')->with('brand')->get();
        return view('admin.products.index', compact('products','brands','categories'));
    }

    public function brand_index()
    {
        $brands = Brand::get();
        return view('admin.brands.index', compact('brands'));
    }

    public function edit($id)
    {
        $brands= Brand::get();
        $categories= Category::get();
        $product = Product::with('category')->with('brand')->findOrFail($id);
        return view('admin.products.edit', compact('product','brands','categories'));
    }


    public function brand_edit($id)
    {
        $brand = Brand::findOrFail($id);
        return view('admin.brands.edit', compact('brand'));
    }

    public function store(Request $request)
    {
        $product = new Product();
        $product->product_name = $request->product_name ?? null;
        $product->brand_id = $request->brand_id ?? null;
        $product->category_id = $request->category_id ?? null;
        $product->qty = $request->qty ?? null;
        $product->price = $request->price;
        $product->details = $request->details;
        $product->save();

        Alert::toast('Product Added Successfully!', 'success');
        return redirect()->back();
    }

    public function brand_store(Request $request)
    {
        $brand = new Brand();
        $brand->name = $request->name;
        $brand->save();

        Alert::toast('Brand Added Successfully!', 'success');
        return redirect()->back();
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        // Update product details
        $product->update($request->only(['product_name', 'brand_id', 'category_id', 'qty', 'price', 'details']));


        Alert::toast('Product Updated Successfully!', 'success');
        return redirect()->route('product.index');
    }


    public function brand_update(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->update($request->only(['name']));

        Alert::toast('Brand Updated Successfully!', 'success');
        return redirect()->route('brand.index');
    }

    public function delete($id)
    {
        // Find the product
        $product = Product::findOrFail($id);

        // Delete the product
        $product->delete();

        Alert::toast('Product Deleted Successfully!', 'success');
        return redirect()->route('product.index');
    }

    public function brand_delete($id)
    {
        $brand = Brand::findOrFail($id);

        $brand->delete();

        Alert::toast('Brand Deleted Successfully!', 'success');
        return redirect()->route('brand.index');
    }
    
}