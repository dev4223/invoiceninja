<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Subscription;

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Jobs\Util\SubscriptionWebhookHandler;
use App\Jobs\Util\SystemLogger;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientSubscription;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Repositories\InvoiceRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Subscription\ZeroCostProduct;
use App\Utils\Ninja;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SubscriptionHooker;
use Carbon\Carbon;
use GuzzleHttp\RequestOptions;

class SubscriptionService
{
    use MakesHash;
    use CleanLineItems;
    use SubscriptionHooker;

    /** @var subscription */
    private $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /*  
        Performs the initial purchase of a 
        one time or recurring product
    */
    public function completePurchase(PaymentHash $payment_hash)
    {

        if (!property_exists($payment_hash->data, 'billing_context')) {
            throw new \Exception("Illegal entrypoint into method, payload must contain billing context");
        }

        if($payment_hash->data->billing_context->context == 'change_plan') {
            return $this->handlePlanChange($payment_hash);
        };

        // if we have a recurring product - then generate a recurring invoice
        if(strlen($this->subscription->recurring_product_ids) >=1){

            $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash->payment->client_id);
            $recurring_invoice_repo = new RecurringInvoiceRepository();

            $recurring_invoice->next_send_date = now();
            $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
            $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();

            /* Start the recurring service */
            $recurring_invoice->service()
                              ->start()
                              ->save();

            //execute any webhooks

            $context = [
                'context' => 'recurring_purchase',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
                'contact' => auth('contact')->user()->hashed_id,
            ];

            $response = $this->triggerWebhook($context);

            // nlog($response);

            $this->handleRedirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);

        }
        else
        {
            $invoice = Invoice::find($payment_hash->fee_invoice_id);

            $context = [
                'context' => 'single_purchase',
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client'  => $invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
            ];

            //execute any webhooks
            $this->triggerWebhook($context);

            $this->handleRedirect('/client/invoices/'.$this->encodePrimaryKey($payment_hash->fee_invoice_id));

        }
    }

    /* Hits the client endpoint to determine whether the user is able to access this subscription */
    public function isEligible($contact)
    {
        $context = [
            'context' => 'is_eligible',
            'subscription' => $this->subscription->hashed_id,
            'contact' => $contact->hashed_id,
            'contact_email' => $contact->email,
            'client' => $contact->client->hashed_id,
        ];

        $response = $this->triggerWebhook($context);
        nlog($response);
        return $response;
    }

    /* Starts the process to create a trial
        - we create a recurring invoice, which is has its next_send_date as now() + trial_duration
        - we then hit the client API end point to advise the trial payload
        - we then return the user to either a predefined user endpoint, OR we return the user to the recurring invoice page.
    */
    public function startTrial(array $data)
    {
        // Redirects from here work just fine. Livewire will respect it.
        $client_contact = ClientContact::find($data['contact_id']);

        if(!$this->subscription->trial_enabled)
            return new \Exception("Trials are disabled for this product");

        //create recurring invoice with start date = trial_duration + 1 day
        $recurring_invoice_repo = new RecurringInvoiceRepository();

        $recurring_invoice = $this->convertInvoiceToRecurring($client_contact->client_id);
        $recurring_invoice->next_send_date = now()->addSeconds($this->subscription->trial_duration);
        $recurring_invoice->backup = 'is_trial';

        if(array_key_exists('coupon', $data) && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0)
        {
            $recurring_invoice->discount = $this->subscription->promo_discount;
            $recurring_invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        $recurring_invoice = $recurring_invoice_repo->save($data, $recurring_invoice);

        /* Start the recurring service */
        $recurring_invoice->service()
                          ->start()
                          ->save();

            $context = [
                'context' => 'trial',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
            ];

        //execute any webhooks
        $response = $this->triggerWebhook($context);

        if(array_key_exists('return_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['return_url']) >=1){
            return redirect($this->subscription->webhook_configuration['return_url']);
        }

        return redirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);
    }

    public function calculateUpgradePrice(RecurringInvoice $recurring_invoice, Subscription $target) :?float
    {
        //calculate based on daily prices

        $current_amount = $recurring_invoice->amount;
        $currency_frequency = $recurring_invoice->frequency_id;

        $outstanding = $recurring_invoice->invoices()
                                         ->where('is_deleted', 0)
                                         ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                         ->where('balance', '>', 0);

        $outstanding_amounts = $outstanding->sum('balance');
        // $outstanding_invoices = $outstanding->get();
        $outstanding_invoices = $outstanding;

        if ($outstanding->count() == 0){
            //nothing outstanding
            return $target->price;
        }
        elseif ($outstanding->count() == 1){
            //user has multiple amounts outstanding
            return $target->price - $this->calculateProRataRefund($outstanding->first());
        }
        elseif ($outstanding->count() > 1) {
            //user is changing plan mid frequency cycle
            //we cannot handle this if there are more than one invoice outstanding.
            return null;
        }

        return null;

    }

    /**
     * We refund unused days left.
     * 
     * @param  Invoice $invoice 
     * @return float 
     */
    private function calculateProRataRefund($invoice) :float
    {
        
        $start_date = Carbon::parse($invoice->date);

        $current_date = now();

        $days_to_refund = $start_date->diffInDays($current_date);

        $days_in_frequency = $this->getDaysInFrequency();

        $pro_rata_refund = round((($days_in_frequency - $days_to_refund)/$days_in_frequency) * $invoice->amount ,2);
        
        return $pro_rata_refund;
    }

    /**
     * We only charge for the used days
     * 
     * @param  Invoice $invoice 
     * @return float        
     */
    private function calculateProRataCharge($invoice) :float
    {
        
        $start_date = Carbon::parse($invoice->date);

        $current_date = now();

        $days_to_refund = $start_date->diffInDays($current_date);

        $days_in_frequency = $this->getDaysInFrequency();

        $pro_rata_refund = round(($days_to_refund/$days_in_frequency) * $invoice->amount ,2);
        
        return $pro_rata_refund;
    }

    public function createChangePlanInvoice($data)
    {
        $recurring_invoice = $data['recurring_invoice'];
        //Data array structure
        /**
         * [
         * 'recurring_invoice' => RecurringInvoice::class,
         * 'subscription' => Subscription::class,
         * 'target' => Subscription::class
         * ]
         */
        
        // $outstanding_invoice = $recurring_invoice->invoices()
        //                              ->where('is_deleted', 0)
        //                              ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
        //                              ->where('balance', '>', 0)
        //                              ->first();

        $pro_rata_charge_amount = 0;
        $pro_rata_refund_amount = 0;

        // // We calculate the pro rata charge for this invoice.
        // if($outstanding_invoice)
        // {
        // }

        $last_invoice = $recurring_invoice->invoices()
                                         ->where('is_deleted', 0)
                                         ->orderBy('id', 'desc')
                                         ->first();   
        
        //$last_invoice may not be here!

        if(!$last_invoice) {
            $data = [
                'client_id' => $recurring_invoice->client_id,
                'coupon' => '',
            ];

            return $this->createInvoice($data)->service()->markSent()->fillDefaults()->save();

        }
        else if($last_invoice->balance > 0) 
        {
            $pro_rata_charge_amount = $this->calculateProRataCharge($last_invoice);
        }
        else
        {
            $pro_rata_refund_amount = $this->calculateProRataRefund($last_invoice) * -1;
        }

        $total_payable = $pro_rata_refund_amount + $pro_rata_charge_amount + $this->subscription->price;

        if($total_payable > 0)
        {
            return $this->proRataInvoice($pro_rata_refund_amount, $data['subscription'], $data['target']);
        }
        else
        {
            //create credit
        }


        return Invoice::where('status_id', Invoice::STATUS_SENT)->first();
    }

    /**
     * Response from payment service on return from a plan change
     * 
     */
    private function handlePlanChange($payment_hash)
    {

        //payment has been made.
        //
        //new subscription starts today - delete old recurring invoice.
        
        $old_subscription_recurring_invoice = RecurringInvoice::find($payment_hash->data->billing_context->recurring_invoice);
        $old_subscription_recurring_invoice->service()->stop()->save();

        $recurring_invoice_repo = new RecurringInvoiceRepository();
        $recurring_invoice_repo->archive($old_subscription_recurring_invoice);

            $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash->payment->client_id);
            $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
            $recurring_invoice->next_send_date = now();
            $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();

            /* Start the recurring service */
            $recurring_invoice->service()
                              ->start()
                              ->save();

            $context = [
                'context' => 'change_plan',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
                'contact' => auth('contact')->user()->hashed_id,
            ];

            $response = $this->triggerWebhook($context);

            nlog($response);

            if(array_key_exists('post_purchase_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['post_purchase_url']) >=1)
                return redirect($this->subscription->webhook_configuration['post_purchase_url']);

            return redirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);

    }

    public function handlePlanChangeNoPayment()
    {

    }

    /**
     *    'client_id' => 2,
          'date' => '2021-04-13',
          'invitations' => 
          'user_input_promo_code' => NULL,
          'coupon' => '',
          'quantity' => 1,
     */
    private function proRataInvoice($refund_amount, $subscription, $target)
    {
        $subscription_repo = new SubscriptionRepository();
        $invoice_repo = new InvoiceRepository();

        $line_items = $subscription_repo->generateLineItems($target);

        $item = new InvoiceItem;
        $item->quantity = 1;
        $item->product_key = ctrans('texts.refund');
        $item->notes = ctrans('texts.refund') . ":" .$subscription->name;
        $item->cost = $refund_amount;

        $line_items[] = $item;
    
        $data = [
            'client_id' => $subscription->client_id,
            'quantity' => 1,
            'date' => now()->format('Y-m-d'),
        ];

        return $invoice_repo->save($data, $invoice)->service()->markSent()->fillDefaults()->save();

    }


    public function createInvoice($data): ?\App\Models\Invoice
    {

        $invoice_repo = new InvoiceRepository();
        $subscription_repo = new SubscriptionRepository();

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->line_items = $subscription_repo->generateLineItems($this->subscription);
        $invoice->subscription_id = $this->subscription->id;

        if(strlen($data['coupon']) >=1 && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0)
        {
            $invoice->discount = $this->subscription->promo_discount;
            $invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        return $invoice_repo->save($data, $invoice);

    }


    public function convertInvoiceToRecurring($client_id) :RecurringInvoice
    {

        $subscription_repo = new SubscriptionRepository();

        $recurring_invoice = RecurringInvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $recurring_invoice->client_id = $client_id;
        $recurring_invoice->line_items = $subscription_repo->generateLineItems($this->subscription, true);
        $recurring_invoice->subscription_id = $this->subscription->id;
        $recurring_invoice->frequency_id = $this->subscription->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->date = now();
        $recurring_invoice->remaining_cycles = -1;

        return $recurring_invoice;
    }

    public function triggerWebhook($context)
    {
        /* If no webhooks have been set, then just return gracefully */
        if(!array_key_exists('post_purchase_url', $this->subscription->webhook_configuration) || !array_key_exists('post_purchase_rest_method', $this->subscription->webhook_configuration)) {
            return true;
        }

        $response = false;

        $body = array_merge($context, [
            'company_key' => $this->subscription->company->company_key,
            'account_key' => $this->subscription->company->account->key,
            'db' => $this->subscription->company->db,
        ]);

        $response = $this->sendLoad($this->subscription, $body);

        /* Append the response to the system logger body */
        if(is_array($response)){

            $body = $response;

        }
        else {

            $status = $response->getStatusCode();

            //$response_body = $response->getReasonPhrase();
            $body = array_merge($body, ['status' => $status, 'response_body' => $response_body]);

        }

        $client = \App\Models\Client::find($this->decodePrimaryKey($body['client']));

            SystemLogger::dispatch(
                $body,
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_RESPONSE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $client,
            );

        return $response;

    }

    public function fireNotifications()
    {
        //scan for any notification we are required to send
    }

    /**
     * Get the single charge products for the
     * subscription
     *
     * @return ?Product Collection
     */
    public function products()
    {
        return Product::whereIn('id', $this->transformKeys(explode(",", $this->subscription->product_ids)))->get();
    }

    /**
     * Get the recurring products for the
     * subscription
     *
     * @return ?Product Collection
     */
    public function recurring_products()
    {
        return Product::whereIn('id', $this->transformKeys(explode(",", $this->subscription->recurring_product_ids)))->get();
    }

    /**
     * Get available upgrades & downgrades for the plan.
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getPlans()
    {
        return Subscription::query()
            ->where('company_id', $this->subscription->company_id)
            ->where('group_id', $this->subscription->group_id)
            ->where('id', '!=', $this->subscription->id)
            ->get();
    }

    public function handleCancellation()
    {
        dd('Cancelling using SubscriptionService');

        // ..
    }

    private function getDaysInFrequency()
    {

        switch ($this->subscription->frequency_id) {
            case self::FREQUENCY_DAILY:
                return 1;
            case self::FREQUENCY_WEEKLY:
                return 7;
            case self::FREQUENCY_TWO_WEEKS:
                return 14;
            case self::FREQUENCY_FOUR_WEEKS:
                return now()->diffInDays(now()->addWeeks(4));
            case self::FREQUENCY_MONTHLY:
                return now()->diffInDays(now()->addMonthNoOverflow());
            case self::FREQUENCY_TWO_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(2));
            case self::FREQUENCY_THREE_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(3));
            case self::FREQUENCY_FOUR_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(4));
            case self::FREQUENCY_SIX_MONTHS:
                return now()->diffInDays(now()->addMonthNoOverflow(6));
            case self::FREQUENCY_ANNUALLY:
                return now()->diffInDays(now()->addYear());
            case self::FREQUENCY_TWO_YEARS:
                return now()->diffInDays(now()->addYears(2));
            case self::FREQUENCY_THREE_YEARS:
                return now()->diffInDays(now()->addYears(3));
            default:
                return 0;
        }
    
    }
    

    /**
    * 'email' => $this->email ?? $this->contact->email,
    * 'quantity' => $this->quantity,
    * 'contact_id' => $this->contact->id,
    */        
    public function handleNoPaymentRequired(array $data)
    {

        $context = (new ZeroCostProduct($this->subscription, $data))->run();

        // Forward payload to webhook
        if(array_key_exists('context', $context))
            $response = $this->triggerWebhook($context);

        // Hit the redirect
        return $this->handleRedirect($context['redirect_url']);
        
    }

    /**
     * Handles redirecting the user
     */
    private function handleRedirect($default_redirect)
    {

        if(array_key_exists('return_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['return_url']) >=1)
            return redirect($this->subscription->webhook_configuration['return_url']);

        return redirect($default_redirect);
    }    
}
