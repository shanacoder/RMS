<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use DB;
use PDF; // Assuming you are using a PDF library like Dompdf
use Session;
use App\Models\Cart; // Assuming you have a Cart model defined

class HomeController extends Controller
{
    public function index(){
        $menu = DB::table('products')->where('catagory', 'regular')->get();
        $breakfast = DB::table('products')->where('catagory', 'special')->where('session', 0)->get();
        $lunch = DB::table('products')->where('catagory', 'special')->where('session', 1)->get();
        $dinner = DB::table('products')->where('catagory', 'special')->where('session', 2)->get();
        $chefs = DB::table('chefs')->get();
        
        $cart_amount = Auth::check() ? DB::table('carts')->where('user_id', Auth::id())->where('product_order', 'no')->count() : 0;

        $about_us = DB::table('about_us')->get();
        $banners = DB::table('banners')->get();
        return view("home", compact('menu', 'breakfast', 'lunch', 'dinner', 'chefs', 'cart_amount', 'about_us', 'banners'));
    }

    public function redirects(){
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        
        $menu = DB::table('products')->where('catagory', 'regular')->get();
        $breakfast = DB::table('products')->where('catagory', 'special')->where('session', 0)->get();
        $lunch = DB::table('products')->where('catagory', 'special')->where('session', 1)->get();
        $dinner = DB::table('products')->where('catagory', 'special')->where('session', 2)->get();
        $chefs = DB::table('chefs')->get();
        $cart_amount = DB::table('carts')->where('user_id', Auth::id())->where('product_order', 'no')->count();
        $about_us = DB::table('about_us')->get();
        $banners = DB::table('banners')->get();
        $usertype = Auth::user()->usertype;

        if ($usertype != '0') {
            $pending_order = DB::table('carts')->where('product_order', 'yes')->groupBy('invoice_no')->get();
            $processing_order = DB::table('carts')->where('product_order', 'approve')->groupBy('invoice_no')->get();
            $cancel_order = DB::table('carts')->where('product_order', 'cancel')->groupBy('invoice_no')->get();
            $complete_order = DB::table('carts')->where('product_order', 'delivery')->groupBy('invoice_no')->get();
            $total = DB::table('carts')->sum('subtotal');
            $cash_on_payment = DB::table('carts')->where('pay_method', 'Cash On Delivery')->sum('subtotal');
            $online_payment = $total - $cash_on_payment;
            $customer = DB::table('users')->where('usertype', '0')->get();
            $delivery_boy = DB::table('users')->where('usertype', '2')->get();
            $user = DB::table('users')->get();
            $admin = ($user->count()) - ($customer->count() + $delivery_boy->count());
            $rates = DB::table('rates')->get();
            $product = [];
            $voter = [];
            $per_rate = [];

            foreach ($rates as $rate) {
                $product[$rate->product_id] = 0;
                $voter[$rate->product_id] = 0;
                $per_rate[$rate->product_id] = 0;
            }

            foreach ($rates as $rate) {
                $product[$rate->product_id] += $rate->star_value;
                $voter[$rate->product_id] += 1;
                $per_rate[$rate->product_id] = $voter[$rate->product_id] > 0 ? $product[$rate->product_id] / $voter[$rate->product_id] : $product[$rate->product_id];
                $per_rate[$rate->product_id] = number_format($per_rate[$rate->product_id], 1);
            }

            $copy_product = $per_rate;
            arsort($per_rate);
            $product_get = [];

            foreach ($per_rate as $prod) {
                $index_search = array_search($prod, $copy_product);
                $product_get = DB::table('products')->where('id', $index_search)->get();
            }

            $carts = DB::table('carts')->where('product_order', '!=', 'no')->where('product_order', '!=', 'cancel')->get();
            $product_cart = [];

            foreach ($carts as $cart) {
                $product_cart[$cart->product_id] = 0;
            }

            foreach ($carts as $cart) {
                $product_cart[$cart->product_id] += $cart->quantity;
            }

            $copy_cart = $product_cart;
            arsort($product_cart);

            return view('admin.dashboard', compact('pending_order', 'product_cart', 'copy_cart', 'total', 'copy_product', 'per_rate', 'product', 'cash_on_payment', 'online_payment', 'customer', 'delivery_boy', 'admin', 'processing_order', 'cancel_order', 'complete_order'));
        } else {
            return view("home", compact('menu', 'breakfast', 'lunch', 'dinner', 'chefs', 'cart_amount', 'about_us', 'banners'));
        }
    }

    public function reservation_confirm(Request $req)
    {
        $name = $req->name;
        $email = $req->email;
        $phone = $req->phone;
        $no_guest = $req->no_guest;
        $date = $req->date;
        $time = $req->time;
        $message = $req->message;

        $data = [
            'name' => $name,
            'email' => $email,
            'no_guest' => $no_guest,
            'phone' => $phone,
            'date' => $date,
            'time' => $time,
            'message' => $message,
        ];

        $confirm_reservation = DB::table('reservations')->insert($data);

        $details = [
            'title' => 'Mail from RMS Admin',
            'body' => 'Your reservation has been placed successfully',
        ];

        $data["title"] = "From RMS admin";
        $data["body"] = "Your reservation has been placed successfully";

        $pdf = PDF::loadView('mails.Reserve', $data);

        $user = Auth::user();
        if ($user) {
            \Mail::send('mails.Reserve', $data, function($message) use ($data, $pdf, $user) {
                $message->to($user->email)
                        ->subject($data["title"])
                        ->attachData($pdf->output(), "Reservation Copy.pdf");
            });
        }

        return view('Reserve_order');
    }

    public function rate($id)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $already_rate = DB::table('rates')->where('product_id', $id)->where('user_id', Auth::id())->count();
        $find_rate = DB::table('rates')->where('product_id', $id)->where('user_id', Auth::id())->value('star_value');
        $products = DB::table('products')->where('id', $id)->first();
        $total_rate = DB::table('rates')->where('product_id', $id)->sum('star_value');
        $total_voter = DB::table('rates')->where('product_id', $id)->count();
        $per_rate = $total_voter > 0 ? number_format($total_rate / $total_voter, 1) : 0;

        if ($already_rate > 0) {
            return view('rate_view', compact('products', 'find_rate', 'already_rate', 'total_rate', 'total_voter', 'per_rate'));
        }

        return view('rate', compact('products', 'find_rate', 'already_rate', 'total_rate', 'total_voter', 'per_rate'));
    }

    public function store_rate(Request $request)
    {
        $product_id = $request->input('product_id');
        $user_id = Auth::id();
        $star_value = $request->input('star_value');

        $data = [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'star_value' => $star_value,
        ];

        $rate = DB::table('rates')->where('product_id', $product_id)->where('user_id', $user_id)->count();

        if ($rate > 0) {
            DB::table('rates')->where('product_id', $product_id)->where('user_id', $user_id)->update($data);
        } else {
            DB::table('rates')->insert($data);
        }

        Session::flash('message', 'Rating submitted successfully!');

        return redirect()->route('rate', ['id' => $product_id]);
    }

    public function delete_rate($id)
    {
        $user_id = Auth::id();

        DB::table('rates')->where('product_id', $id)->where('user_id', $user_id)->delete();

        Session::flash('message', 'Rating deleted successfully!');

        return redirect()->route('rate', ['id' => $id]);
    }

    public function edit_rate($id)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $find_rate = DB::table('rates')->where('product_id', $id)->where('user_id', Auth::id())->value('star_value');
        $products = DB::table('products')->where('id', $id)->first();
        $total_rate = DB::table('rates')->where('product_id', $id)->sum('star_value');
        $total_voter = DB::table('rates')->where('product_id', $id)->count();
        $per_rate = $total_voter > 0 ? number_format($total_rate / $total_voter, 1) : 0;

        return view('rate', compact('products', 'find_rate', 'total_rate', 'total_voter', 'per_rate'));
    }

    public function top_rated()
    {
        $products = DB::table('rates')
                    ->select('product_id', DB::raw('SUM(star_value) as total_star_value'))
                    ->groupBy('product_id')
                    ->get();

        $data = [];

        foreach ($products as $product) {
            $data[$product->product_id] = $product->total_star_value;
        }

        arsort($data);

        return view('top_rated', compact('data'));
    }
}


