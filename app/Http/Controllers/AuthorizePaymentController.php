<?php

namespace App\Http\Controllers;


use App\Traits\Processor;
use Illuminate\Http\Request;
use App\Models\PaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Foundation\Application;
use net\authorize\api\controller\CreateTransactionController;
use net\authorize\api\contract\v1 as AnetAPI;
class AuthorizePaymentController extends Controller
{
    use Processor;

    private $config_values;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('authorize', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }
        $this->payment = $payment;
    }

    public function index(Request $request): View|Factory|JsonResponse|Application
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $config = $this->config_values;

        return view('payment-views.authorize', compact('data', 'config'));
    }

    public function payment_process_3d(Request $request): JsonResponse
    {
        // Retrieve the payment request data
        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $payment_amount = $data['payment_amount'];

        // Set up the merchant authentication
        $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
        $merchantAuthentication->setName($this->config_values->api_login_id);
        $merchantAuthentication->setTransactionKey($this->config_values->transaction_key);

        $refId = 'ref' . time();

        // Create the payment transaction request
        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("authCaptureTransaction");
        $transactionRequestType->setAmount($payment_amount);
        $transactionRequestType->setPayment($this->getPaymentType($request));

        // Create the transaction request
        $apiRequest = new AnetAPI\CreateTransactionRequest();
        $apiRequest->setMerchantAuthentication($merchantAuthentication);
        $apiRequest->setRefId($refId);
        $apiRequest->setTransactionRequest($transactionRequestType);

        // Execute the transaction
        $controller = new CreateTransactionController($apiRequest);
        $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);

        if ($response != null) {
            // Check the result code of the response
            if ($response->getMessages()->getResultCode() == "Ok") {
                $transactionResponse = $response->getTransactionResponse();

                if ($transactionResponse != null && $transactionResponse->getMessages() != null) {
                    // Update payment status in the database
                    $this->payment::where(['id' => $data->id])->update([
                        'payment_method' => 'authorize',
                        'is_paid' => 1,
                        'transaction_id' => $transactionResponse->getTransId(),
                    ]);

                    return response()->json(['id' => $transactionResponse->getTransId()]);
                } else {
                    // Handle transaction response error
                    $errorMessages = $transactionResponse->getErrors();
                    return response()->json(['error' => 'Transaction failed', 'messages' => $errorMessages], 400);
                }
            } else {
                // Handle API response error
                $errorMessages = $response->getMessages()->getMessage();
                return response()->json(['error' => 'Payment failed', 'messages' => $errorMessages], 400);
            }
        } else {
            // Handle null response
            return response()->json(['error' => 'No response from payment gateway'], 500);
        }
    }


    private function getPaymentType(Request $request): AnetAPI\PaymentType
    {
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($request->get('card_number'));
        $creditCard->setExpirationDate($request->get('expiration_date'));
        $creditCard->setCardCode($request->get('card_code'));

        $paymentOne = new AnetAPI\PaymentType();
        $paymentOne->setCreditCard($creditCard);

        return $paymentOne;
    }

    public function success(Request $request)
    {
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->success_hook)) {
            call_user_func($payment_data->success_hook, $payment_data);
        }

        return $this->payment_response($payment_data, 'success');
    }
}
