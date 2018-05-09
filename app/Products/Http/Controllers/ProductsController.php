<?php

namespace App\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductsController extends Controller
{
    /**
     * Get all products
     *
     * @return mixed
     */
    public function getProducts() {
        $products = Product::where('added_by', Auth::id())->get();
        return response()->json($products, 200);
    }
}