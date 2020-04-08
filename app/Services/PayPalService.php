<?php
namespace App\Services;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Amount;
use PayPal\Api\Transaction;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Payment;
use PayPal\Api\Payer;
use PayPal\Api\PaymentExecution;
use Request;
class PayPalService {
    private $listItems;
    private $totalAmout;
    private $transaction;
    private $apiContext;
    private $paymentCurrrncy;
    private $returnUrl;
    private $cancelUrl;
    public function __construct(){
        $paypalConfig=config('paypal');
        $apiContext=new ApiContext(new OAuthTokenCredential(
            $paypalConfig['client_id'],$paypalConfig['secret_key']
        ));
        $this->setPaymentCurrrncy('USD');
        $this->totalAmout=0;
    }
    public function createPayment($transactionDescription){
        //Tao kieu thanh toan
        $payer=new Payer();
        $payer->setPaymentMethod('paypal');
        //Tao Amout
        $amout=new Amount();
        $amout->setCurrency($this->getPaymentCurrrncy());
        $amout->setTotal($this->getTotalAmout());
        //Tao ListItem
        $itemList=new ItemList();
        $itemList->setItems($this->getListItems());
        //Tao duong dan xu ly thanh toan thanh cong
        $redirectUrls=new RedirectUrls();
        if(is_null($this->cancelUrl)){
            $this->setCancelUrl($this->getReturnUrl());
        }
        $redirectUrls->setCancelUrl($this->getCancelUrl());
        $redirectUrls->setReturnUrl($this->getReturnUrl());
        //Tao Transaction
        $transaction=new Transaction();
        $transaction->setAmount($amout)->setItemList($itemList)->setDescription($transactionDescription);
        //Tao Payment
        $payment =new Payment();
        $payment->setIntent('Sale')->setPayer($payer)->setTransaction($transaction)->setRedirectUrls($redirectUrls);
        try{
            $payment->create($this->getApiContext());

        }catch (\PayPal\Exception\PPConnectionException $paypalException) {
            throw new \Exception($paypalException->getMessage());
        }
        // Nếu việc thanh tạo một payment thành công. Chúng ta sẽ nhận
        // được một danh sách các đường dẫn liên quan đến việc
        // thanh toán trên PayPal
        foreach ($payment->getLinks() as $link) {
        	// Duyệt từng link và lấy link nào có rel
            // là approval_url rồi gán nó vào $checkoutUrl
            // để chuyển hướng người dùng đến đó.
            if ($link->getRel() == 'approval_url') {
                $checkoutUrl = $link->getHref();
				// Lưu payment ID vào session để kiểm tra
                // thanh toán ở function khác
                session(['paypal_payment_id' => $payment->getId()]);

                break;
            }
        }

		// Trả về url thanh toán để thực hiện chuyển hướng
        return $checkoutUrl;


    }
    public function getPaymentSatus(){
        //Khoi tao lay thong tin tu PayPal
        $request=Request::all();
        //Lay payment id tu session luu tu createPayment
        $payment_id=session('paypal_payment_id');
        // Xóa payment ID đã lưu trong session
        session()->forget('paypal_payment_id');
        
        // Kiểm tra xem URL trả về từ PayPal có chứa
        // các query cần thiết của một thanh toán thành công
        // hay không.
        if (empty($request['PayerID']) || empty($request['token'])) {
            return false;
        }
        //khoi tao payment
        $payment=Payment::get($payment_id,$this->getApiContext());
        // Thực thi payment và lấy payment detail
        $paymentExecution = new PaymentExecution();
        $paymentExecution->setPayerId($request['PayerID']);

        $paymentStatus = $payment->execute($paymentExecution, $this->apiContext);

        return $paymentStatus;

    }
    /**
     * Get payment list
     *
     * @param int $limit Limit number payment
     * @param int $offset Start index payment
     * @return mixed Object payment list
     */
    public function getPaymentList($limit,$offset){
        $params=[
            'count'=>$limit,
            'start_index'=>$offset
        ];
        try{
            $return =Payment::all($params,$this->getApiContext());
        }
        catch (\PayPal\Exception\PPConnectionException $paypalException) {
            throw new \Exception($paypalException->getMessage());
        }
        return $return;
    }
    /**
     * Get payment details
     *
     * @param string $paymentId PayPal payment Id
     * @return mixed Object payment details
     */
    public function getPaymentDetails($paymentId)
    {
        try {
            $paymentDetails = Payment::get($paymentId, $this->apiContext);
        } catch (\PayPal\Exception\PPConnectionException $paypalException) {
            throw new \Exception($paypalException->getMessage());
        }

        return $paymentDetails;
    }

    /**
     * Get the value of listItems
     */ 
    public function getListItems()
    {
        return $this->listItems;
    }

    /**
     * Set the value of listItems
     *
     * @return  self
     */ 
    public function setListItems($listItems)
    {
        if(count($listItems==count($listItems,COUNT_RECURSIVE))){
            $listItems=[$listItems];
        }
        foreach($listItems as $value){
            $item=new Item();
            $item->setName($data['name'])->setCurrency($data['currency'])->setPrice($data['price'])->setSku($data['sku'])->setQuantity($data['quantity']);
            $this->listItems[]=$item;
            $this->totalAmout+=$item->getPrice()*$item->getQuantity();
        }
        
        return $this;
    }

    /**
     * Get the value of transaction
     */ 
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * Set the value of transaction
     *
     * @return  self
     */ 
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;

        return $this;
    }

    /**
     * Get the value of cancelUrl
     */ 
    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    /**
     * Set the value of cancelUrl
     *
     * @return  self
     */ 
    public function setCancelUrl($cancelUrl)
    {
        $this->cancelUrl = $cancelUrl;

        return $this;
    }

    /**
     * Get the value of returnUrl
     */ 
    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    /**
     * Set the value of returnUrl
     *
     * @return  self
     */ 
    public function setReturnUrl($returnUrl)
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    /**
     * Get the value of paymentCurrrncy
     */ 
    public function getPaymentCurrrncy()
    {
        return $this->paymentCurrrncy;
    }

    /**
     * Set the value of paymentCurrrncy
     *
     * @return  self
     */ 
    public function setPaymentCurrrncy($paymentCurrrncy)
    {
        $this->paymentCurrrncy = $paymentCurrrncy;

        return $this;
    }

    /**
     * Get the value of apiContext
     */ 
    public function getApiContext()
    {
        return $this->apiContext;
    }

    /**
     * Set the value of apiContext
     *
     * @return  self
     */ 
    public function setApiContext($apiContext)
    {
        $this->apiContext = $apiContext;

        return $this;
    }

    /**
     * Get the value of totalAmout
     */ 
    public function getTotalAmout()
    {
        return $this->totalAmout;
    }
}