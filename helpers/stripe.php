<?php

declare(strict_types=1);

use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\Customer;
use Stripe\Collection;
use Stripe\SearchResult;

/**
 * StripeHelper class for handling Stripe API operations
 */
class StripeHelper
{
    public StripeClient $client;

    /**
     * Constructor for StripeHelper
     *
     * @param string $api_key The Stripe API key
     * @param string $api_version The Stripe API version to use
     */
    public function __construct(string $api_key, string $api_version)
    {
        $this->client = new StripeClient([
            "api_key" => $api_key,
            "stripe_version" => $api_version,
        ]);
    }

    /**
     * Get user subscriptions
     *
     * @param string $customerId The Stripe customer ID
     * @param bool $activeOnly Whether to retrieve only active subscriptions
     * @return Collection A collection of user subscriptions
     */
    public function getUserSubscriptions(string $customerId, bool $activeOnly = true): Collection
    {
        $payload = [
            'customer' => $customerId,
            'limit' => 100,
        ];
        if ($activeOnly) {
            $payload['status'] = 'active';
        }
        return $this->client->subscriptions->all($payload);
    }

    /**
     * Get a specific subscription by ID
     *
     * @param string $id The subscription ID
     * @return Subscription The retrieved subscription
     */
    public function getSubscription(string $id): Subscription
    {
        return $this->client->subscriptions->retrieve($id);
    }

    /**
     * Get a customer by ID
     *
     * @param string $customerId The customer ID
     * @return Customer The retrieved customer
     */
    public function getCustomer(string $customerId): Customer
    {
        return $this->client->customers->retrieve($customerId);
    }

    /**
     * Get a customer by email
     *
     * @param string $email The customer's email
     * @return Customer|array The retrieved customer or an empty array if not found
     * @throws ApiErrorException
     */
    public function getCustomerByEmail(string $email): Customer|array
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
     * Create or update a customer
     *
     * @param array $cust Customer data
     * @return Customer The created or updated customer
     * @throws ApiErrorException
     */
    public function createUpdateCustomer(array $cust): Customer
    {
        $customers = $this->client->customers->all([
            'email' => $cust['email'],
            'limit' => 1,
        ]);

        if (count($customers->data) == 1) {
            return $customers->data[0];
        }

        return $this->client->customers->create($cust);
    }

    /**
     * Get a payment method by ID
     *
     * @param string $paymentMethodId The payment method ID
     * @return \Stripe\PaymentMethod The retrieved payment method
     * @throws ApiErrorException
     */
    public function getPaymentMethod(string $paymentMethodId): \Stripe\PaymentMethod
    {
        return $this->client->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * Update a payment method
     *
     * @param string $paymentMethodId The payment method ID
     * @param array $data The data to update
     * @return \Stripe\PaymentMethod The updated payment method
     * @throws ApiErrorException
     */
    public function updatePaymentMethod(string $paymentMethodId, array $data): \Stripe\PaymentMethod
    {
        return $this->client->paymentMethods->update(
            $paymentMethodId,
            $data
        );
    }

    /**
     * Attach a coupon to a customer
     *
     * @param string $customerId The customer ID
     * @param string $couponId The coupon ID
     * @return Customer The updated customer
     * @throws ApiErrorException
     */
    public function attachCustomerCoupon(string $customerId, string $couponId): Customer
    {
        return $this->client->customers->update($customerId, [
            'coupon' => $couponId,
        ]);
    }

    /**
     * Attach a coupon to a subscription
     *
     * @param string $subscriptionsId The subscription ID
     * @param string $couponId The coupon ID
     * @return Subscription The updated subscription
     * @throws ApiErrorException
     */
    public function attachSubscriptionCoupon(string $subscriptionsId, string $couponId): Subscription
    {
        return $this->client->subscriptions->update($subscriptionsId, [
            'coupon' => $couponId,
        ]);
    }

    /**
     * Attach a payment method to a customer
     *
     * @param string $customerId The customer ID
     * @param string $paymentMethodId The payment method ID
     * @param bool $setDefault Whether to set this as the default payment method
     * @return array The result of the operation
     * @throws ApiErrorException
     */
    public function attachPaymentMethod(string $customerId, string $paymentMethodId, bool $setDefault = true): array
    {
        try {
            $payment_method = $this->client->paymentMethods->retrieve($paymentMethodId);
            $res = $payment_method->attach(['customer' => $customerId]);

            if ($setDefault) {
                $this->client->customers->update($customerId, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId,
                    ],
                ]);
            }

            return ['data' => $res];
        } catch (CardException $e) {
            return ['error' => $e->getError()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Attach a subscription to a customer
     *
     * @param string $customerId The customer ID
     * @param string $priceId The price ID
     * @param int|null $anchorDate The billing cycle anchor date
     * @param string $prorate The proration behavior
     * @param string|null $coupon The coupon to apply
     * @param array $metadata Additional metadata
     * @return Subscription The created subscription
     * @throws ApiErrorException
     */
    public function attachSubscription(
        string $customerId,
        string $priceId,
        ?int $anchorDate = null,
        string $prorate = 'create_prorations',
        ?string $coupon = null,
        array $metadata = []
    ): Subscription {
        $payload = [
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'metadata' => $metadata,
        ];

        if ($anchorDate !== null) {
            $payload['billing_cycle_anchor'] = $anchorDate;
            $payload['proration_behavior'] = $prorate;
        }

        if ($coupon !== null) {
            $payload['coupon'] = $coupon;
        }

        return $this->client->subscriptions->create($payload);
    }

    /**
     * Switch a subscription to a new plan
     *
     * @param string $subscriptionId The subscription ID
     * @param string $priceId The new price ID
     * @param string|null $coupon The coupon to apply
     * @param string $prorate The proration behavior
     * @param int|null $trialEnd The trial end date
     * @return Subscription The updated subscription
     * @throws ApiErrorException
     */
    public function switchSubscriptionPlan(
        string $subscriptionId,
        string $priceId,
        ?string $coupon = null,
        string $prorate = 'create_prorations',
        ?int $trialEnd = null
    ): Subscription {
        $subscription = $this->client->subscriptions->retrieve($subscriptionId);

        $payload = [
            'items' => [
                [
                    'id' => $subscription->items->data[0]->id,
                    'price' => $priceId,
                ],
            ],
            'proration_behavior' => $prorate,
        ];

        if ($trialEnd !== null) {
            $payload['trial_end'] = $trialEnd;
        }

        if ($subscription->discount) {
            $this->client->subscriptions->deleteDiscount($subscriptionId);
        }

        if ($coupon !== null) {
            $payload['coupon'] = $coupon;
        }

        return $this->client->subscriptions->update($subscriptionId, $payload);
    }

    /**
     * Attach items to a subscription
     *
     * @param string $subscriptionId The subscription ID
     * @param array $priceIds The price IDs to attach
     * @param string|null $coupon The coupon to apply
     * @return Subscription The updated subscription
     * @throws ApiErrorException
     */
    public function attachItemsToSubscription(string $subscriptionId, array $priceIds, ?string $coupon = null): Subscription
    {
        $payload = [
            'items' => array_map(fn($priceId) => ['price' => $priceId], $priceIds)
        ];

        if ($coupon !== null) {
            $payload['coupon'] = $coupon;
        }

        return $this->client->subscriptions->update($subscriptionId, $payload);
    }

    /**
     * Cancel all subscriptions for a customer
     *
     * @param string $customerId The customer ID
     * @param bool $prorate Whether to prorate the cancellation
     * @throws ApiErrorException
     */
    public function cancelCustomerSubscriptions(string $customerId, bool $prorate = true): void
    {
        $subscriptions = $this->getUserSubscriptions($customerId, true);
        foreach ($subscriptions->data as $subscription) {
            $this->client->subscriptions->cancel($subscription->id, ['prorate' => $prorate]);
        }
    }

    /**
     * Cancel specific subscriptions
     *
     * @param array|string $subscriptionIds The subscription ID(s) to cancel
     * @param bool $immediately Whether to cancel immediately or at the end of the billing period
     * @throws ApiErrorException
     */
    public function cancelSubscriptions(array|string $subscriptionIds, bool $immediately = false): void
    {
        $subscriptionIds = (array) $subscriptionIds;
        $action = $immediately
            ? fn($id) => $this->client->subscriptions->cancel($id, ['prorate' => true])
            : fn($id) => $this->client->subscriptions->update($id, ['cancel_at_period_end' => true]);

        array_map($action, $subscriptionIds);
    }

    /**
     * Uncancel subscriptions
     *
     * @param array|string $subscriptionIds The subscription ID(s) to uncancel
     * @throws ApiErrorException
     */
    public function unCancelSubscriptions(array|string $subscriptionIds): void
    {
        $subscriptionIds = (array) $subscriptionIds;
        array_map(
            fn($id) => $this->client->subscriptions->update($id, ['cancel_at_period_end' => false]),
            $subscriptionIds
        );
    }

    /**
     * Create a meter event
     *
     * @param string $eventName The event name
     * @param string $customerId The customer ID
     * @param int $value The event value
     * @param string|null $identifier An optional identifier
     * @param int|null $timestamp An optional timestamp
     */
    public function createMeterEvent(
        string $eventName,
        string $customerId,
        int $value,
        ?string $identifier = null,
        ?int $timestamp = null
    ) {
        $payload = [
            'event_name' => $eventName,
            'payload' => [
                'value' => $value,
                'stripe_customer_id' => $customerId,
            ]
        ];
        if ($identifier !== null) {
            $payload['identifier'] = $identifier;
        }
        if ($timestamp !== null) {
            $payload['timestamp'] = $timestamp;
        }

        return $this->client->billing->meterEvents->create($payload);
    }
}
