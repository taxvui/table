<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PayOS\PayOS;

class PayOSPaymentController extends Controller
{
    protected $payOS;

    public function __construct()
    {
        $this->payOS = new PayOS(
            config('services.payos.client_id'),
            config('services.payos.api_key'),
            config('services.payos.checksum_key')
        );
    }

    public function initiatePayment(Request $request)
    {
        // Tạo orderCode là số nguyên, có thể lấy từ ID đơn hàng trong CSDL của bạn.
        // Ở đây tạo ngẫu nhiên một mã 6 số để làm ví dụ
        $orderCode = intval(substr(strval(microtime(true) * 10000), -6));

        $data = [
            "orderCode" => $orderCode,
            "amount" => intval($request->input('amount', 20000)), // Số tiền thanh toán (VND)
            "description" => "Thanh toan don hang " . $orderCode,
            "returnUrl" => route('payos.success'),
            "cancelUrl" => route('payos.cancel')
        ];

        try {
            $response = $this->payOS->createPaymentLink($data);
            return redirect($response['checkoutUrl']);
        } catch (\Throwable $th) {
            return back()->with('error', 'Lỗi tạo giao dịch PayOS: ' . $th->getMessage());
        }
    }

    public function paymentSuccess(Request $request)
    {
        // Hiển thị View hoặc chuyển hướng khi người dùng thanh toán thành công
        return redirect()->route('payments.index')->with('success', 'Thanh toán PayOS thành công!');
    }

    public function paymentCancel(Request $request)
    {
        // Hiển thị View hoặc chuyển hướng khi người dùng hủy bỏ thanh toán
        return redirect()->route('payments.index')->with('error', 'Bạn đã hủy giao dịch PayOS!');
    }

    public function handleWebhook(Request $request)
    {
        $body = $request->getContent();
        $signature = $request->header('payos-signature');

        try {
            $data = $this->payOS->verifyPaymentWebhookData($body, $signature);
            
            // TODO: Tìm đơn hàng bằng $data['orderCode'] và cập nhật trạng thái thành 'Đã thanh toán' trong Database

            return response()->json([
                "error" => 0,
                "message" => "Ok",
                "data" => $data
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "error" => 1,
                "message" => $th->getMessage()
            ]);
        }
    }
}