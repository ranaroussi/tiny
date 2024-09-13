<?php
// https://stripe.com/docs/billing/subscriptions/metered

/**
 * global $stripe;
 * function initStripe()
 * {
 *     global $stripe;
 *     $stripe = new \Stripe\StripeClient([
 *         "api_key" => STRIPE_SK,
 *         "stripe_version" => STRIPE_VERSION,
 *     ]);
 *     return $stripe;
 * }
 */

use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class StripeHelper
{
    public StripeClient $client;

    public function __construct($api_key, $api_version)
    {
        $this->client = new StripeClient([
            "api_key" => $api_key,
            "stripe_version" => $api_version,
        ]);
    }

    public function getUserSubscriptions($customerId, $active_only = true)
    {
        $payload = [
            'customer' => $customerId,
            'limit' => 100,
        ];
        if ($active_only) {
            $payload['status'] = 'active';
        }
        return $this->client->subscriptions->all($payload);
    }

    public function searchSubscriptionMetadata($key, $value)
    {
        return $this->client->subscriptions->search([
            'query' => "status:'active' AND metadata['$key']:'$value'",
        ]);
    }

    public function getSubscription($id)
    {
        return $this->client->subscriptions->retrieve($id);
    }

    public function getCustomer($customerId)
    {
        return $this->client->customers->retrieve($customerId);
    }

    /**
     * @throws ApiErrorException
     */
    public function getCustomerByEmail($email)
    {
        $customer = $this->client->customers->all([
            'email' => $email,
            'limit' => 1,
        ]);

        if (count($customer->data) == 1) {
            return $customer->data[0];
        }
        return [];
    }

    /**
     * @throws ApiErrorException
     */
    public function createUpdateCustomer($cust)
    {

        $customer = [];

        // find stripe customer
        $customer = $this->client->customers->all([
            'email' => $cust['email'],
            'limit' => 1,
        ]);

        if (count($customer->data) == 1) {
            $customer = $customer->data[0];
        } else {
            // create stripe customer
            $customer = $this->client->customers->create($cust);
            if ($customer) {
                return $customer;
            }
        }
        return $customer;
    }

    /**
     * @throws ApiErrorException
     */
    public function getPaymentMethod($paymentMethodId)
    {
        return $this->client->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * @throws ApiErrorException
     */
    public function updatePaymentMethod($paymentMethodId, $data)
    {
        return $this->client->paymentMethods->update(
            $paymentMethodId,
            $data
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function attachCustomerCoupon($customerId, $couponId)
    {
        return $this->client->customers->update($customerId, [
            'coupon' => $couponId,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function attachSubscriptionCoupon($subscriptionsId, $couponId)
    {
        return $this->client->subscriptions->update($subscriptionsId, [
            'coupon' => $couponId,
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function attachPaymentMethod($customerId, $paymentMethodId, $setDefault = true)
    {

        try {
            $payment_method = $this->client->paymentMethods->retrieve($paymentMethodId);
            $res = $payment_method->attach([
                'customer' => $customerId,
            ]);
        } catch (CardException $e) {
            return ['error' => $e->getError()];
        } catch (Exception $e) {
            return ['error' => json_encode($e)];
        }

        if ($setDefault) {
            $user = $this->client->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);
        }

        return ['data' => $res];
    }

    /**
     * @throws ApiErrorException
     */
    public function attachSubscription($customerId, $priceId)
    {

        // Create the subscription
        return $this->client->subscriptions->create([
            'customer' => $customerId,
            'items' => [
                ['price' => $priceId],
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ]);
    }

    /**
     * @throws ApiErrorException
     */
    public function switchSubscriptionPlan($subscriptionId, $priceId, $coupon = null, $prorate = 'create_prorations', $trial_end = null)
    {
        // create usage items
        // tiny::debug($subscriptionId);
        $subscription = $this->getSubscription($subscriptionId);
        $payload = [
            'cancel_at_period_end' => false,
            'proration_behavior' => $prorate,
            'items' => [
                [
                    'id' => $subscription->items->data[0]->id,
                    'price' => $priceId,
                ],
            ],
        ];

        if ($trial_end != null) {
            $payload['trial_end'] = $trial_end;
        }

        if ($subscription->discount) {
            $this->client->subscriptions->deleteDiscount($subscriptionId);
        }

        if ($coupon != null) {
            $payload['coupon'] = $coupon;
        }

        // attach item to subscription
        return $this->client->subscriptions->update(
            $subscriptionId,
            $payload
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function attachItemsToSubscription($subscriptionId, $priceIds, $coupon = null)
    {
        if (!is_array($priceIds)) {
            $priceIds = [$priceIds];
        }

        // create usage items
        $items = [];
        foreach ($priceIds as $priceId) {
            $items[] = ['price' => $priceId];
        }

        $payload = ['items' => $items];

        if ($coupon != null) {
            $payload['coupon'] = $coupon;
        }

        // attach item to subscription
        return $this->client->subscriptions->update(
            $subscriptionId,
            $payload
        );
    }

    /**
     * @throws ApiErrorException
     */
    public function cancelCustomerSubscriptions($customerId, $prorate = true)
    {
        $subscriptions = $this->getUserSubscriptions($customerId, $active_only = true);
        foreach ($subscriptions->data as $subscription) {
            $this->client->subscriptions->cancel($subscription->id, ['prorate' => $prorate]);
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function cancelSubscriptions($subscriptionIds = [], $immediately = false)
    {
        if (!is_array($subscriptionIds)) {
            $subscriptionIds = [$subscriptionIds];
        }
        if ($immediately) {
            foreach ($subscriptionIds as $id) {
                $this->client->subscriptions->cancel($id, ['prorate' => true]);
            }
        } else {
            foreach ($subscriptionIds as $id) {
                $this->client->subscriptions->update($id, ['cancel_at_period_end' => true]);
            }
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function unCancelSubscriptions($subscriptionIds = [])
    {
        if (!is_array($subscriptionIds)) {
            $subscriptionIds = [$subscriptionIds];
        }

        foreach ($subscriptionIds as $id) {
            $this->client->subscriptions->update($id, ['cancel_at_period_end' => false]);
        }
    }

    /**
     * @throws ApiErrorException
     */
    public function attachSubscriptionBundle($customerId, $priceIds, $coupon = null, $skip_recurring = false, $metadata = [])
    {
        // attach both plan and metered
        if ($skip_recurring) {
            unset($priceIds['plan']);
        }

        // create subscription plan
        if (@$priceIds['plan']) {
            // recurring subscriptions must be created seperatly
            $planPriceId = @$priceIds['plan'];
            $payload = [
                'customer' => $customerId,
                'items' => [['price' => $planPriceId]],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => $metadata,
            ];
            // tiny::debug($payload);
            $plan_subscription = $this->client->subscriptions->create($payload);
            unset($priceIds['plan']);
        }

        // create usage items
        $items = [];
        foreach ($priceIds as $priceId) {
            $items[] = ['price' => $priceId];
        }

        // only plan?
        if (empty($items)) {
            return $plan_subscription ?? null;
        }

        $payload = [
            'customer' => $customerId,
            'items' => $items,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $metadata,
        ];

        if ($coupon != null) {
            $payload['coupon'] = $coupon;
        }

        // tiny::debug($payload);

        // Create the subscription
        $subscription = $this->client->subscriptions->create($payload);

        // attach recurring subscription to response
        if (isset($plan_subscription)) {
            $subscription->items->data[] = $plan_subscription->items->data[0];
        }

        return $subscription;
    }

    public function createMeteredSubscription($plan, $cycle = 'monthly')
    {
        $plan = mb_strtolower(tiny::trim(str_replace(' ', '-', $plan)));
        $items = [];
        foreach (@$_SERVER['STRIPE_CONFIG'][$plan] as $key => $value) {
            if (!in_array($key, ['subscriptions', 'coupons'])) {
                $items[$key] = $value['price_id'];
            }
        }

        if ($plan != 'free') {
            // add research
            foreach (['balanced', 'cpu', 'memory', 'gpu'] as $category) {
                foreach (@$_SERVER['STRIPE_CONFIG']['research'][$category]['price_ids'] as $key => $value) {
                    $items['research_' . $category . '_' . $key] = $value;
                }
            }
        }

        $coupons = array_values(@@$_SERVER['STRIPE_CONFIG'][$plan]['coupons']);
        $coupon = ($coupons) ? $coupons[0] : null;

        return [
            'items' => $items,
            'coupon' => $coupon,
        ];
    }

    public function attachMeteredSubscription($customerId, $priceIds, $coupon = null, $metadata = [])
    {
        // attach metered only

        if (empty($priceIds)) {
            return null;
        }

        // create usage items
        $items = [];
        foreach ($priceIds as $priceId) {
            $items[] = ['price' => $priceId];
        }

        $payload = [
            'customer' => $customerId,
            'items' => $items,
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $metadata,
        ];

        if ($coupon != null) {
            $payload['coupon'] = $coupon;
        }

        return $this->client->subscriptions->create($payload);
    }

    /**
     * @throws ApiErrorException
     */
    public function attachPlanSubscription($customerId, $priceId, $coupon = null, $metadata = [])
    {
        // attach plan only
        $payload = [
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $metadata,
        ];
        // tiny::debug($payload);
        return $this->client->subscriptions->create($payload);
    }

    /**
     * @throws ApiErrorException
     */
    public function getUpcomingInvoice($customerId, $for_display = false)
    {
        $upcoming_invoices = [];

        $upcoming_invoices[] = $this->client->invoices->upcoming([
            'customer' => $customerId,
        ]);
        // tiny::debug($upcoming_invoices);

        // get all invoices
        $subs = $this->getUserSubscriptions($customerId);
        foreach ($subs->data as $subs) {
            $upcoming_invoices[] = $this->client->invoices->upcoming([
                'subscription' => $subs->id,
            ]);
        }

        // tiny::debug($upcoming_invoices);

        $lineitems = [];
        $lineitems_template = [
            'subscription' => ['items' => []],
            'metered' => ['items' => []],
        ];

        $amounts = [];
        $amount_paid = 0;
        $amount_due = 0;
        $amount_remaining = 0;
        $coupons = [];
        $discounts = [];

        foreach ($upcoming_invoices as $invoice) {

            if (!empty($invoice->discount)) {
                $coupons[$invoice->discount->id] = $invoice->discount->coupon;
            }
            $amount_due += $invoice->amount_due ?: 0;
            $amount_paid += $invoice->amount_paid ?: 0;
            $amount_remaining += $invoice->amount_remaining ?: 0;

            foreach ($invoice->lines->autoPagingIterator() as $line) {
                if ($line->metadata->plan) {
                    if (!isset($lineitems[$line->metadata->plan])) {
                        $lineitems[$line->metadata->plan] = $lineitems_template;
                    }
                    $lineitems[$line->metadata->plan][$line->metadata->type]['items'][$line->id] = $line;

                    if (!isset($amounts[$line->metadata->plan])) {
                        $amounts = [$line->metadata->plan => []];
                    }
                    $amounts[$line->metadata->plan][$line->id] = $line->amount;

                    if (!isset($discounts[$line->metadata->plan])) {
                        $discounts = [$line->metadata->plan => []];
                    }
                    $discounts[$line->metadata->plan][$line->id] = @$line->discount_amounts[0]->discount;
                }
            }
        }
        // die();

        // uniquify
        foreach ($discounts as $id => $plan) {
            $discounts[$id] = array_unique(array_values($plan));
        }

        // prep return
        $combo = [
            'coupons' => $coupons,
            'subscriptions' => [],
        ];

        $lineitems = tiny::cleanObjectTypes($lineitems);
        foreach ($lineitems as $plan => $items) {
            if (!empty($items['subscription']['items'])) {
                $subkey = array_keys($lineitems[$plan]['subscription']['items'])[0];
                $subscription = $this->lineItemSummary($items['subscription']['items'][$subkey]);

                $metered = [];
                foreach ($items['metered']['items'] as $key => $item) {
                    $metered[] = $this->lineItemSummary($item);
                }

                $combo['subscriptions'][$plan] = [
                    'name' => @$_SERVER['PLANS'][$plan]['name'],
                    'description' => @$_SERVER['PLANS'][$plan]['desc'],
                    'properties' => @$_SERVER['PLANS'][$plan],
                    'amount' => @array_sum($amounts[$plan]) ?: 0,
                    'discount' => @array_sum($discounts[$plan]) ?: 0,
                    'plan' => $subscription,
                    'metered' => $metered,
                ];

                $combo['subscriptions'][$plan]['properties']['actions'] *= 1000000;
                unset($combo['subscriptions'][$plan]['properties']['name']);
                unset($combo['subscriptions'][$plan]['properties']['desc']);
                unset($combo['subscriptions'][$plan]['properties']['active']);
                unset($combo['subscriptions'][$plan]['properties']['onboarding']);
                unset($combo['subscriptions'][$plan]['properties']['extra_user']);
            }
        }

        $combo['amount_paid'] = $amount_paid;
        $combo['amount_due'] = $amount_due;
        $combo['amount_remaining'] = $amount_remaining;
        $combo = tiny::arrayToObject($combo);

        if ($for_display) {
            $combo = $this->displayUpcomingInvoice($combo);
        }
        return $combo;
    }

    private function lineItemSummary($line_item)
    {
        // return $line_item;
        $category = explode(' (', tiny::trim(explode(' × ', $line_item['description'])[1], ')'));
        if (!empty($category)) {
            $category = ucwords($category[0]);
            $subcategory = '';

            $item = [
                'category' => $category,
                'subcategory' => $subcategory,
                'nickname' => $line_item['price']['nickname'],
                'description' => $line_item['description'],
                'period' => $line_item['period'],
                'subscription' => $line_item['subscription'],
                'subscription_item' => $line_item['subscription_item'],
                'quantity' => $line_item['quantity'] ?: 0,
                'discounts' => $line_item['discount_amounts'],
                'tier' => tiny::trim(tiny::trim(@explode('(Tier', $line_item['description'])[1], ')')),
                'price' => [
                    'amount' => $line_item['amount'],
                    'unit_amount' => $line_item['price']['unit_amount'] ?: 0,
                    'currency' => mb_strtoupper($line_item['price']['currency']),
                    'usage_type' => ucwords($line_item['plan']['usage_type']),
                    'billing_interval' => ucwords($line_item['plan']['interval']),
                    'billing_interval_count' => $line_item['plan']['interval_count'],
                    'billing_scheme' => ucwords($line_item['price']['billing_scheme']),

                ],
            ];

            if ($category == 'Research Instances') {
                $subcategory = @[
                    'b' => 'Balanced',
                    'c' => 'CPU-Optimized',
                    'm' => 'Memory-Optimized',
                    'g' => 'GPU-Optimized',
                ][explode('.', $item['nickname'])[0]];
            }
            $item['subcategory'] = $subcategory;

            return $item;
        }
    }

    private function displayUpcomingInvoice($upcoming_invoice)
    {
        // tiny::debug($upcoming_invoice);
        $descriptions = [
            'Cloud Actions' => 'Overage charge for actions consumed beyond your plan-covered quota',
            'Block Storage' => 'Charge for persistamnce block storage beyond your plan-covered quota',
            'Live Trading' => 'Charge for broker-executed trading turnover beyond your plan-covered quota',
            'Research Instances' => 'Browser-accesible R&amp;D machines hrs beyond your plan-covered quota',
            'Balanced' => 'Virtual machines that offers a good balance of memory and vCPUs',
            'CPU-Optimized' => 'Compute-optimized VMs with dedicated CPU for workloads that rely on CPU more than RAM',
            'Memory-Optimized' => 'Virtual machines with 8GB of memory for each vCPU for RAM-intensive research',
            'GPU-Optimized' => 'GPU compute-optimized VMs for AI/ML-focused research and machine learning model training',
        ];

        $keys = [
            'Cloud Actions' => 'actions',
            'Block Storage' => 'volumes',
            'Live Trading' => 'trading',
            'Research Instances' => 'research',
            'Balanced' => 'balanced',
            'CPU-Optimized' => 'cpu',
            'Memory-Optimized' => 'memory',
            'GPU-Optimized' => 'gpu',
        ];

        $total = 0;
        $dues = [];
        $usage = [];

        $coupons = $upcoming_invoice->coupons;
        foreach ($upcoming_invoice->subscriptions as $key => $group) {
            $total += $group->plan->price->amount ? $group->plan->price->amount / 100 : 0;
            $dues[] = $group->plan->period->start;
            $usage[$key] = [
                'plan' => [
                    'name' => $group->plan->nickname ? $group->plan->nickname : ucfirst($group->name),
                    'description' => $group->description,
                    'amount' => $group->plan->price->amount ? $group->plan->price->amount / 100 : 0,
                    'period' => date('M d, Y', $group->plan->period->start) . ' - ' . date('M d, Y', $group->plan->period->end),
                    'discount' => '',
                    'structure' => $group->plan->price->billing_interval . 'ly Subscription',
                    'cycle' => $group->plan->price->billing_interval_count . '-' . mb_strtolower($group->plan->price->billing_interval) . ($group->plan->price->billing_interval_count > 1 ? 's' : ''),
                ],
            ];

            if ($group->plan->price->billing_interval_count > 1) {
                $usage[$key]['plan']['structure'] = 'Every ' . $group->plan->price->billing_interval_count . ' ' . mb_strtolower($group->plan->price->billing_interval) . 's';
            }

            $couponId = @$group->plan->discounts[0]->discount;
            $discount = @$upcoming_invoice->coupons->$couponId;

            if (@$discount->valid == 1) {
                unset($coupons->$couponId);
                $usage[$key]['plan']['discount'] = (@$discount->percent_off) ? number_format($discount->percent_off, 0) . '% off' : '$' . number_format($discount->amount_off, 2) . ' off';
            }

            $research = [];
            foreach ($group->metered as $item) {
                $amount = $item->price->amount ? $item->price->amount / 100 : 0;
                $total += $amount;
                $data = [
                    'name' => $item->category, //$name,
                    'description' => @$descriptions[$item->category],
                    'period' => date('M d, Y', $item->period->start) . ' - ' . date('M d, Y', $item->period->end),
                    'quantity' => $item->quantity,
                    'tier' => $item->tier,
                    'amount' => $amount,
                    'structure' => $item->price->usage_type . ' (' . $item->price->billing_scheme . '), ' . $item->price->billing_interval . 'ly',
                ];
                $category = @$keys[$item->category];
                if ($item->subcategory) {
                    $usage[$key]['metered'][$category]['name'] = $data['name'];
                    $usage[$key]['metered'][$category]['period'] = $data['period'];
                    $usage[$key]['metered'][$category]['structure'] = $data['structure'];
                    $usage[$key]['metered'][$category]['description'] = $data['description'];
                    $usage[$key]['metered'][$category]['types'][$item->subcategory]['description'] = @$descriptions[$item->subcategory];

                    if (!isset($usage[$key]['metered'][$category]['amount'])) {
                        $usage[$key]['metered'][$category]['amount'] = 0;
                    }
                    $usage[$key]['metered'][$category]['amount'] += $amount;

                    if (!isset($usage[$key]['metered'][$category]['quantity'])) {
                        $usage[$key]['metered'][$category]['quantity'] = 0;
                    }
                    $usage[$key]['metered'][$category]['quantity'] += $data['quantity'];

                    if (!isset($usage[$key]['metered'][$category]['types'][$item->subcategory]['amount'])) {
                        $usage[$key]['metered'][$category]['types'][$item->subcategory]['amount'] = 0;
                    }
                    $usage[$key]['metered'][$category]['types'][$item->subcategory]['amount'] += $amount;

                    if (!isset($usage[$key]['metered'][$category]['types'][$item->subcategory]['quantity'])) {
                        $usage[$key]['metered'][$category]['types'][$item->subcategory]['quantity'] = 0;
                    }
                    $usage[$key]['metered'][$category]['types'][$item->subcategory]['quantity'] += $data['quantity'];

                    $data['name'] = $item->nickname;
                    $data['period'] = '';
                    $data['description'] = '';
                    $data['structure'] = '';
                    $usage[$key]['metered'][$category]['types'][$item->subcategory]['items'][] = $data;
                } else {
                    $usage[$key]['metered'][$category] = $data;
                }
            }
        }

        $coupons = empty($coupons) ? [] : array_values(get_object_vars($coupons));
        $coupons = (object)$coupons;
        $active_coupons = [];
        foreach ($coupons as $coupon) {
            if (@$coupon->valid == 1) {
                $name = explode(': ', $coupon->name);
                $name = (count($name) > 1) ? $name[1] : $name[0];
                $coupon = [
                    'name' => ucfirst($name),
                    'off' => (@$coupon->percent_off) ? number_format($coupon->percent_off, 0) . '% off' : '$' . number_format($coupon->amount_off, 2) . ' off',
                ];
                $active_coupons[] = $coupon;
            }
        }

        // tiny::debug($payload);
        return [
            'due_date' => date('F d, Y', min($dues)),
            'amount' => $total,
            'amount_due' => $upcoming_invoice->amount_due,
            'amount_paid' => $upcoming_invoice->amount_paid,
            'amount_remaining' => $upcoming_invoice->amount_remaining,
            'coupons' => $active_coupons,
            'subscriptions' => $usage,
        ];
    }

    /**
     * @throws ApiErrorException
     */
    public function getBillingHistory($customerId, $last_invoiceId = null, $last_chargeId = null)
    {
        $stripe_invoice_filter = $stripe_charge_filter = [
            // 'limit' => 100,
            'customer' => $customerId,
        ];

        if ($last_invoiceId) {
            $stripe_invoice_filter['ending_before'] = $last_invoiceId;
        }
        if ($last_chargeId) {
            $stripe_charge_filter['ending_before'] = $last_chargeId;
        }

        $invoices = [];

        $raw_invoices = $this->client->invoices->all($stripe_invoice_filter);
        foreach ($raw_invoices->autoPagingIterator() as $invoice) {
            $invoices[$invoice->id] = [
                'stripe_customer_id' => $invoice->customer,
                'stripe_invoice_id' => $invoice->id,
                'stripe_charge_id' => $invoice->charge,
                'invoice_created' => 'to_timestamp(' . $invoice->created . ')',
                'amount_due' => ($invoice->amount_due) ? $invoice->amount_due : 0,
                'amount_paid' => ($invoice->amount_paid) ? $invoice->amount_paid : 0,
                'amount_remaining' => $invoice->amount_remaining,
                'status' => $invoice->status,
                'invoice_url' => $invoice->hosted_invoice_url,
                'invoice_pdf' => $invoice->invoice_pdf,
                'last_finalization_error' => $invoice->last_finalization_error,

                // placeholders
                'charge_created' => 'to_timestamp(' . $invoice->created . ')',
                'amount_refunded' => 0,
                'receipt_url' => null,
            ];
        }
        // tiny::debug($invoices);

        $raw_charges = $this->client->charges->all($stripe_charge_filter);
        // tiny::debug($raw_charges);
        foreach ($raw_charges->autoPagingIterator() as $charge) {
            if (isset($invoices[$charge->invoice])) {
                $invoices[$charge->invoice]['charge_created'] = 'to_timestamp(' . $charge->created . ')';
                $invoices[$charge->invoice]['amount_refunded'] = ($charge->refunded) ? $charge->refunded : 0;
                $invoices[$charge->invoice]['receipt_url'] = $charge->receipt_url;
            }
        }

        // tiny::debug($invoices, 0);
        return $invoices;
    }
}
