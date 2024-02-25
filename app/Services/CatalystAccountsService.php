<?php

namespace App\Services;

//use App\Models\CatalystApiSetting;
use CodeDredd\Soap\Facades\Soap;
use CodeDredd\Soap\SoapClient;
use GuzzleHttp\RedirectMiddleware;
use Illuminate\Support\Facades\Log;

class CatalystAccountsService
{
    public string $serviceUrl;
    public string $username;
    public string $token;
    public object $branchDetail;
    public array $options;

    public function __construct()
    {
        $this->serviceUrl = 'http://124.109.46.180:7777/ws/CatalystService?wsdl';
        $this->username = 'tntapi';
        $this->options = [
            'allow_redirects' => RedirectMiddleware::$defaultSettings,
            'http_errors' => true,
            'decode_content' => true,
            'verify' => false,
            'cookies' => false,
            'idn_conversion' => false,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ],
            ]),
        ];
    }

    public function isAuthenticate()
    {
        $authenticate = Soap::baseWsdl($this->serviceUrl)
            ->withHeaders([
                'username' => $this->username,
                'password' => '40BD001563085FC35165329EA1FF5C5ECBDBBEEF'
            ])
            ->call('isAuthenticate');
        if ($authenticate->ok()) {
            /*CatalystApiSetting::where('status', 1)->update(['status' => 0]);
            return CatalystApiSetting::create([
                'response_data' => json_encode($authenticate->object()),
                'unique_key' => $authenticate->object()->return->token,
                'status' => 1
            ]);*/
            return (object)[
                'response_data' => json_encode($authenticate->object()),
                'token' => $authenticate->object()->return->token,
            ];
        }

        if ($authenticate->failed()) {
            $exceptionInfo = ['code' => $authenticate->status(), 'message' => $authenticate->body()];
            Log::error($authenticate->body(), $exceptionInfo);
            return (object)$exceptionInfo;
        }

        return (object)[null];
    }

    protected function soapClient(): SoapClient
    {
        /*if (!$setting = CatalystApiSetting::where('status', 1)->first()) {
            $setting = $this->isAuthenticate();
        }*/
        $setting = $this->isAuthenticate();
        $this->token = $setting->token ?? null;
        $this->branchDetail = $setting ? json_decode($setting->response_data)->return->branchWSList[0] : (object)[];

        return Soap::baseWsdl($this->serviceUrl)
            ->withHeaders(['username' => $this->username, 'token' => $this->token]);
    }

    public function getData()
    {
        $response = $this->soapClient()
            ->call('getData', ['arg0' => 'CHARGEFIELD']);

        if ($response->failed()) {
            return $this->handleFailedRequest($response, __FUNCTION__);
        }
        return $response->object();
    }

    public function getSuppliers()
    {
        $response = $this->soapClient()->call('getSuppliers');

        if ($response->failed()) {
            return $this->handleFailedRequest($response, __FUNCTION__);
        }
        return collect($response->object()->return)->transform(function ($supplier) {
            return [
                'id' => (string)$supplier->id,
                'title' => $supplier->title,
                'supplierCode' => $supplier->supplierCode,
            ];
        });
    }

    public function getCustomers()
    {
        $response = $this->soapClient()->call('getCustomers');

        if ($response->failed()) {
            return $this->handleFailedRequest($response, __FUNCTION__);
        }

        return collect($response->object()->return)->transform(function ($customer) {
            return [
                'id' => (string)$customer->id,
                'title' => $customer->title,
                'currentBalance' => $customer->currentBalance,
                'creditLimit' => $customer->creditLimit
            ];
        });
    }

    public function getInvoiceLedger()
    {
        $response = $this->soapClient()
            ->call('getInvoiceLedger', [
                'fromInvoiceDate' => null,
                'toInvoiceDate' => null,
                'invoiceNumber' => null,
                'customerId' => '2100000000000001'
            ]);
        if ($response->failed()) {
            return $this->handleFailedRequest($response, __FUNCTION__);
        }

        return collect($response->object()->return);
    }

    public function getTourInvoice()
    {
        $response = $this->soapClient()->call('getTourInvoice', ['invoiceNumber' => 1]);

        if ($response->failed()) {
            return $this->handleFailedRequest($response, __FUNCTION__);
        }
        return $response->object();
    }

    public function createTourInvoice(): object
    {
        $client = $this->soapClient();
        $dated = now()->getTimestampMs();

        $requestParams = [
            'generalInformationRequest' => [
                'branchXid' => (string)$this->branchDetail->id,
                'invoiceDate' => $dated,
                'adjustmentDate' => $dated,
                'customerId' => "2100000000000001",
                'paymentMode' => '',
                'ourExchangeOrder' => "2018",
                'workingShiftId' => '',#
                'staff' => '',
                'spoXid' => '',#
                'saleInvoiceStatus' => '',
                'visitTypeXid' => '10005',
                'nameOnVoice' => 'Muhammad Arslan Dev',
                'creditCardNumber' => '',
                'clientExchangeOrder' => '',
                'iataNo' => '',#
                'costcenter' => '',#
                'remarks' => '2222',
                'paxList' => [
                    'cnic' => '',
                    'paxName' => 'Muhammad Arslan',
                    'paxTypeXid' => "10001",
                    'passportNumber' => '',
                    'passportIssueDate' => '',
                    'ntn' => '',
                    'nationality' => '',
                    'poi' => '',
                    'xid' => '',
                ],
                'invoiceNumber' => '',
                'xid' => ''
            ],
            'ticketBookingRequest' => [
                'ticketNumber' => '157-1000-423-124',
                'endTicketNumber' => '',
                'airlineCode' => 'QR',
                'supplierId' => "230000000000000001",
                'sector' => "3",
                'issueDate' => $dated,
                'pnr' => '123456',
                'bookingId' => '',
                'paxId' => "0",
                'ticketType' => "1", //domestic
                'ourXo' => "123", //'E',
                'tourCode' => '',
                'documentType' => '3',
                'autoNumber' => '',
                'gds' => '',
                'pnrGroup' => '',
                'isAutoUpdate' => 0,
                'refundXid' => '',
                'bookingCategory' => "10002",
                'stateBankCode' => '',
                'segmentList' => [
                    'startCity' => 'ISB',
                    'endCity' => 'KHI',
                    'date' => $dated,
                    'fareNumber' => '',
                    'flightNumber' => "345",
                    'flightClass' => 'L',
                    'startTime' => '2305',
                    'endTime' => '1541',
                    'airVendor' => 'QR',
                    'status' => ''
                ],
                'lstChargeDiscountStructs' => [
                    'label' => 'FARE',
                    'amount' => "10000",
                ],
                'taxList' => [
                    'chargeCode' => 'XT',
                    'value' => 0
                ],
            ]
        ];

        $response = $client->call('createTourInvoice', $requestParams);

        if ($response->failed()) {
            return $this->handleFailedRequest($response, __FUNCTION__);
        }
        return (object)['response' => $response->object(), 'request' => $requestParams];
    }

    private function handleFailedRequest($response, $callback)
    {
        if ($response->body() === 'Invalid token') {
            $reAuthenticate = $this->isAuthenticate();
            if ($reAuthenticate instanceof CatalystApiSetting) {
                return $this->$callback();
            }
            return $reAuthenticate;
        }

        return (object)['code' => $response->status(), 'message' => $response->body()];
    }

}
