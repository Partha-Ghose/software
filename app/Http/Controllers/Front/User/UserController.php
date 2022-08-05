<?php

namespace App\Http\Controllers\Front\User;

use App\Http\Controllers\Controller;
use App\Model\Coupon\Coupon;
use App\Model\Coupon\UserCoupon;
use App\Model\Order\Order;
use App\Model\Order\OrderDetails;
use App\Model\Order\TrialProduct;
use App\Model\Setting\ShopSetting;
use App\User;
use Auth;
use Hash;
use Illuminate\Http\Request;
use Image;
use PDF;
use Session;
use \Mail;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('front.user.dashboard');
    }

    public function dashboardData()
    {

        $order           = Order::where('user_id', '=', Auth::user()->id)->count();
        $total_discount  = Order::where('user_id', '=', Auth::user()->id)->sum('discount');
        $coupon_discount = Order::where('user_id', '=', Auth::user()->id)->sum('coupon_discount');
        $product         = OrderDetails::where('user_id', '=', Auth::user()->id)->sum('quantity');

        return response()->json([
            'total_order'    => $order,
            'total_product'  => $product,
            'total_discount' => $total_discount + $coupon_discount,
        ]);

    }

    public function profileUpdate()
    {
        return view('front.user.update_profile');
    }

    public function order()
    {
        return view('front.user.orders');
    }

    public function getUserOrder()
    {
        $orders = Order::with('shipping_area:id,city')
            ->orderBy('id', 'desc')
            ->where('user_id', Auth::id())
            ->paginate(10);
        return $orders;
    }

    public function getUserOrderDetails($id)
    {

        $orderdetails  = OrderDetails::with('product:id,product_name,product_image,quantity_unit')->where('order_id', '=', $id)->get();
        $trial_product = TrialProduct::with('product:id,product_name,product_image,quantity_unit')->where('order_id', '=', $id)->get();
        return response()->json(['order_details' => $orderdetails, 'trial_product' => $trial_product]);
    }

    public function OrderDetailsPdf($id)
    {
        $orderdetails = OrderDetails::with('product:id,product_name,product_image')->where('order_id', '=', $id)->get();
        $order        = Order::with(['user:id,name,email', 'order_details.product'])->find($id);
        // return view('front.user.pdf.orderdetailspdf',['orderdetails' => $orderdetails, 'order' => $order]);

        $pdf = PDF::loadView('front.user.pdf.orderdetailspdf', ['orderdetails' => $orderdetails, 'order' => $order]);

        $pdf->setPaper('A4', 'landscape');
        return $pdf->download("orderdetails-Pdf-" . $id . ".pdf");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {}

    public function authenticateUser()
    {
        $user = Auth::user();

        return ['user' => $user, 'location' => getLocationData()];
    }

    public function storeUpdateProfile(Request $request)
    {

        $request->validate([
            'name'  => 'required',
            'phone' => 'nullable|unique:users,phone,' . $request->id,
            'email' => 'nullable|unique:users,email,' . $request->id,
            'image' => 'nullable|image64:jpeg,png,gif,jpg,webp,bmp',
        ], [
            'image.image64' => 'image must have to be jpeg,png,gif,jpg,webp,bmp',
        ]);

        try {

            $user              = User::find(Auth::user()->id);
            $user->name        = $request->name;
            $user->email       = $request->email;
            $user->phone       = $request->phone;
            $user->location_id = $request->location_id;
            $user->address     = $request->address;

            $imageData = $request->get('image');

            if ($imageData) {

                if (file_exists('images/avatar/' . $user->avatar) && !empty($user->avatar)) {

                    unlink('images/avatar/' . $user->avatar);
                }

                $fileName = str_replace(' ', '-', $user->name) . '_' . uniqid() . '.' . explode('/', explode(':', substr($imageData, 0, strpos($imageData, ';')))[1])[1];
                Image::make($request->get('image'))->resize(300, 300)->save('images/avatar/' . $fileName);

                $user->avatar = $fileName;

            }

            $user->update();

            return response()->json(['status' => 'success', 'message' => 'Profile Updated']);

        } catch (\Exception $e) {
            return $e;
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);

        }
    }
    public function passwordChange()
    {
        return view('front.user.changePassword');
    }

    public function storeNewPassword(Request $request)
    {
        $request->validate([
            'oldpassword' => 'required',
            'password'    => 'required|confirmed|min:6',
        ]);

        $hasPassword = Auth::user()->password;

        if (Hash::check($request->oldpassword, $hasPassword)) {
            $user           = User::find(Auth::id());
            $user->password = Hash::make($request->password);
            $user->save();
            Auth::logout();
            Session::flash('success', 'Password Changed Successfully!');
            return redirect()->route('login');
        } else {
            Session::flash('error', 'Current Password is Invalid!');
            return redirect()->back();
        }
    }

    
    public function sendEmailLink(Request $request)
    {
        $request->validate([
            'phone' => 'required',
        ]);
        
        $DOMAIN = "https://smsplus.sslwireless.com";
        $url = trim($DOMAIN, '/')."/api/v3/send-sms";
        
        // dd($new_url);
        // https://laravelpackages.net/sslwireless/sslwireless-sms
        
        // $SslWirelessSms = new SslWirelessSms('LuckyEnt','Sm$12345', 'LUCKYNONAPI');
        
        // // $SslWirelessSms = new SslWirelessSms('LuckyEnt','Sm$12345', 'LuckyEnt');
        // // You can change the api url if needed. i.e.
        // // $SslWirelessSms->setUrl($new_url);
        
        //  $output = $SslWirelessSms->send('123456789','This is a test message.');

        // dd($output);
        
        
        // $csmsId = "dhf7fsd7fsdfsdfsdfsdfshgdshf43fc";
        $csmsId = "2934fe343";
        $API_TOKEN = "gtrluppz-daaljndk-tenkojqx-voj3ekvd-85pkei15";
        
        $otp = rand(10000,99999);
        $messageBody = "Your OTP is : ". $otp;
        
        $params = [
            "api_token" => $API_TOKEN,
            "sid" => 'LUCKYNONAPI',
            "msisdn" => $request->phone,
            "sms" => $messageBody,
            "csms_id" => $csmsId
        ];
        // $url = trim(DOMAIN, '/')."/api/v3/send-sms";
        $params = json_encode($params);
        
        // dd($params);
    
        // callApi($url, $params);
        
        $ch = curl_init(); // Initialize cURL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($params),
            'accept:application/json'
        ));
    
        $response = curl_exec($ch);
    
        curl_close($ch);
    
        return $response;
        
        


        return redirect()->back();
    }

    function callApi($url, $params)
{
    $ch = curl_init(); // Initialize cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($params),
        'accept:application/json'
    ));

    $response = curl_exec($ch);

    curl_close($ch);

    return $response;
}

    public function viewUserResetPage(Request $request)
    {
        $result = User::where('remember_token', $request->token)->first();
        if ($result->count() > 0) {
            return view('auth.passwords.confirm', ['token' => $request->token]);
        } else {
            Session::flash('error', 'Please, try again!!');
            return redirect()->back();
        }
    }

    public function storeResetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|confirmed|min:6',
        ]);
        $user           = User::where('remember_token', $request->identity)->first();
        $user->password = Hash::make($request->password);
        $user->update();
        Auth::logout();
        Session::flash('success', 'Password is Changed Successfully!');
        return redirect()->route('login');
    }

    public function logout()
    {
        Auth::logout();
        Session::flash('success', 'You are Logout!');
        return redirect()->to('/');
    }

    public function myCoupon()
    {
        $current_date = date('Y-m-d');
        $user_code    = UserCoupon::where('is_applied', '=', 0)
            ->where('is_used', '=', 0)
            ->where('valid_date', '>=', $current_date)
            ->where('user_id', '=', Auth::user()->id)
            ->pluck('coupon_code');

        $coupon = Coupon::whereIn('coupon_code', $user_code)->get();

        $coupon;

        return view('front.user.coupon', ['coupon' => $coupon]);

    }
}
