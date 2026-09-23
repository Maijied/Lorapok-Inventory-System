<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sell;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use DateTime;

use Illuminate\Http\Request;

class SellController extends Controller
{
    public function index()
    {
        $sells= Sell::with('sellDetails')->get();
        $products = Product::with('category')->with('brand')->get();
        return view('admin.sells.index', compact('products','sells'));
    }

    public function invoice($id)
    {
        $sell= Sell::with('sellDetails.product')->findOrFail($id);
        return view('admin.sells.invoice', compact('sell'));
    }

    public function all_sells()
    {
        $sells= Sell::with('sellDetails')->get();
        return view('admin.sells.allSells', compact('sells'));
    }

    public function view($id)
    {
        $sell= Sell::with('sellDetails.product')->findOrFail($id);
        return view('admin.sells.view', compact('sell'));
    }

    public function edit($id)
    {
        $sell = Sell::with('sellDetails.product')->findOrFail($id);
        $products = Product::all(); // Fetch all products to populate the dropdown
        return view('admin.sells.edit', compact('sell', 'products'));
    }

    public function store(Request $request)
    {
        $sell = null;
       
    
        DB::transaction(function () use ($request, &$sell) {
            $invoiceNumber = $this->generateInvoiceNumber($request->customer_phone);
            // Save the order details
            $sell = Sell::create([
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_address' => $request->customer_address,
                'grand_total' => $request->grand_total,
                'invoice' => 'INV' . $invoiceNumber,
            ]);
    
            // Save each product's details
            foreach ($request->product_id as $index => $productId) {
                $quantitySold = $request->qty[$index];
    
                // Save each product's sale details
                $sell->sellDetails()->create([
                    'product_id' => $productId,
                    'quantity' => $quantitySold,
                    'selling_price' => $request->total[$index], // Editable price from the form
                    'imei' => $request->imei[$index],
                ]);
    
                // Update product quantity in stock
                $product = Product::findOrFail($productId);
    
                if ($product->qty < $quantitySold) {
                    Alert::error('Insufficient stock', 'Error Message');
                    throw new \Exception('Insufficient stock');
                }
    
                $product->decrement('qty', $quantitySold); // Reduce the stock
            }
        });
    
        if ($sell) {
            Alert::toast('Products sold successfully!', 'success');
            return redirect()->route('sell.view', ['id' => $sell->id]);
        }
    
        return redirect()->route('sell.index');
    }

    public function update(Request $request, $id)
    {
       
        $sell = Sell::with('sellDetails')->findOrFail($id); // Load the sale with details

        // Revert previous stock changes before updating
        foreach ($sell->sellDetails as $details) {
            $product = Product::find($details->product_id);

            $product->increment('qty', $details->quantity); // Restore stock
        }
        $sell->sellDetails()->delete();

        $sell->update([
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_address' => $request->customer_address,
            'grand_total' => $request->grand_total, // Recalculate the grand total
        ]);

        foreach ($request->product_id as $index => $productId) {
            $product = Product::find($productId);

            $newQty = $request->qty[$index];

            $product->decrement('qty', $newQty);

            $sell->sellDetails()->updateOrCreate(
                ['product_id' => $productId],
                [
                    'quantity' => $newQty,
                    'selling_price' => $request->total[$index],
                    'imei' => $request->imei[$index],
                ]
            );
        }
        Alert::toast('Sell Updated successfully!', 'success');
        return redirect()->route('all.sell.index');
    }

    public function delete($id)
    {
        $sell = Sell::findOrFail($id);

        $sell->sellDetails()->delete();
        $sell->delete();

        Alert::toast('Sell Deleted successfully!', 'success');
        return redirect()->route('all.sell.index');
    }

    private function generateInvoiceNumber($phoneNumber) {
        $dateTime = new DateTime();
        $formattedDateTime = $dateTime->format('YmdHis');
        $last4Digits = substr($phoneNumber, -4); 
        $invoiceNumber = $formattedDateTime . $last4Digits;
        return $invoiceNumber;
    }

}
