# Stripe Helper

The Stripe helper provides a simple interface for integrating Stripe payment processing into your application.

## Configuration

Configure Stripe in your `.env` file:

```env
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

## Basic Usage

### Payments

```php
// Create a payment intent
$intent = tiny::stripe()->createIntent([
    'amount' => 2000, // $20.00
    'currency' => 'usd',
    'payment_method_types' => ['card']
]);

// Process a charge
$charge = tiny::stripe()->charge([
    'amount' => 2000,
    'currency' => 'usd',
    'source' => $tokenId,
    'description' => 'Test Charge'
]);
```

### Customers

```php
// Create customer
$customer = tiny::stripe()->createCustomer([
    'email' => 'customer@example.com',
    'source' => $tokenId
]);

// Update customer
tiny::stripe()->updateCustomer($customerId, [
    'metadata' => ['order_id' => '123']
]);

// Get customer
$customer = tiny::stripe()->getCustomer($customerId);
```

### Subscriptions

```php
// Create subscription
$subscription = tiny::stripe()->subscribe($customerId, [
    'price' => 'price_H2ZlLQs9w0cp',
    'trial_period_days' => 14
]);

// Update subscription
tiny::stripe()->updateSubscription($subscriptionId, [
    'price' => 'price_new_plan'
]);

// Cancel subscription
tiny::stripe()->cancelSubscription($subscriptionId, [
    'at_period_end' => true
]);
```

### Webhooks

```php
// Handle webhook
tiny::stripe()->handleWebhook(function($event) {
    switch ($event->type) {
        case 'payment_intent.succeeded':
            // Handle successful payment
            break;
        case 'customer.subscription.deleted':
            // Handle subscription cancellation
            break;
    }
});
```

## Advanced Features

### Payment Methods

```php
// Attach payment method
tiny::stripe()->attachPaymentMethod($paymentMethodId, $customerId);

// List payment methods
$methods = tiny::stripe()->listPaymentMethods($customerId, [
    'type' => 'card'
]);

// Set default payment method
tiny::stripe()->setDefaultPaymentMethod($customerId, $paymentMethodId);
```

### Invoices

```php
// Create invoice
$invoice = tiny::stripe()->createInvoice($customerId);

// Pay invoice
tiny::stripe()->payInvoice($invoiceId);

// Get invoice PDF
$pdf = tiny::stripe()->getInvoicePDF($invoiceId);
```

## Error Handling

```php
try {
    $charge = tiny::stripe()->charge($params);
} catch (TinyStripeException $e) {
    // Handle Stripe errors
    $error = $e->getMessage();
    $code = $e->getStripeCode();
}
```

## Best Practices

1. **Security**
   - Use webhook signatures
   - Validate amounts
   - Handle errors gracefully
   - Log transactions

2. **Testing**
   - Use test API keys
   - Test webhook handling
   - Simulate errors
   - Verify webhooks locally

3. **User Experience**
   - Handle card declines
   - Show clear error messages
   - Implement retry logic
   - Send email receipts
