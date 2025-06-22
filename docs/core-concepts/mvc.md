[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# MVC Architecture in Tiny

Tiny implements a modern, performance-optimized Model-View-Controller architecture that emphasizes convention over configuration while providing the flexibility needed for complex applications. This architecture has been battle-tested in high-traffic production environments.

## Architecture Philosophy

**Separation of Concerns**: Each component has a single, well-defined responsibility:
- **Models**: Data access, business logic, and validation
- **Views**: Presentation logic and user interface
- **Controllers**: Request coordination and response handling

**Performance by Design**: Every aspect is optimized for speed:
- Lazy loading of components
- Efficient object pooling
- Optimized database connections
- Smart caching strategies

## Request Lifecycle

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ 1. HTTP Request  │───▶│ 2. Router/Middleware │───▶│ 3. Controller    │
│   (User Action)  │    │   (Authentication) │    │   (Coordination) │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                         │
                                                         ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ 6. HTTP Response │◄───│ 5. View Rendering  │◄───│ 4. Model Logic   │
│   (Final Output) │    │   (Presentation)   │    │   (Data/Business) │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Detailed Request Flow

1. **HTTP Request**: User action triggers a request (GET /billing/transactions)
2. **Router**: Maps URL to controller, applies middleware (auth, CSRF)
3. **Controller**: Instantiates models, processes request, coordinates response
4. **Model**: Handles business logic, data validation, database operations
5. **View**: Renders presentation using data provided by controller
6. **Response**: Final output sent to client (HTML, JSON, redirect, etc.)

## MVC Components Deep Dive

### Models (`app/models/`) - Data & Business Logic Layer

Models in Tiny are responsible for:
- **Data Access**: Database queries and external API calls
- **Business Logic**: Domain-specific rules and calculations
- **Validation**: Input sanitization and rule enforcement
- **Relationships**: Managing data associations

#### Advanced Model Example: Billing System

```php
<?php

declare(strict_types=1);

class BillingModel extends TinyModel
{
    // Validation schemas for different operations
    public array $schemas = [
        'billing_account' => [
            'name' => 'required|string:255',
            'email' => 'required|email:255',
            'phone' => 'nullable|phone',
            'address1' => 'required|string:255',
            'city' => 'required|string:100',
            'country' => 'required|string:2',
            'allow_overage' => 'boolean'
        ],
        'credit_adjustment' => [
            'credits' => 'required|integer|min:1',
            'reason' => 'required|string:255',
            'notes' => 'nullable|string:1000'
        ]
    ];

    /**
     * Get billing account with related data
     */
    public function getBillingAccount(int $accountId): ?object
    {
        // Use caching for frequently accessed data
        $cacheKey = "billing_account_{$accountId}";
        $cached = tiny::cache()->get($cacheKey);
        
        if ($cached) {
            return $cached;
        }

        $account = tiny::db()->getOneQuery("
            SELECT ba.*, 
                   COUNT(bas.subscription_id) as subscription_count,
                   SUM(bas.subscription_credits) as total_subscription_credits
            FROM billing_accounts ba
            LEFT JOIN billing_accounts_subscriptions bas ON ba.billing_account_id = bas.billing_account_id
            WHERE ba.billing_account_id = ?
            GROUP BY ba.billing_account_id
        ", [$accountId]);

        if ($account) {
            // Cache for 5 minutes
            tiny::cache()->set($cacheKey, $account, 300);
        }

        return $account;
    }

    /**
     * Adjust credits with full transaction support
     */
    public function adjustCredits(int $billingAccountId, int $credits, string $reason, ?string $notes = null): array
    {
        // Validate input
        $data = compact('credits', 'reason', 'notes');
        if (!$this->isValid($data, $this->schemas['credit_adjustment'])) {
            throw new InvalidArgumentException('Invalid credit adjustment data');
        }

        // Start database transaction
        tiny::db()->getPdo()->beginTransaction();
        
        try {
            $billingAccount = $this->getBillingAccount($billingAccountId);
            if (!$billingAccount) {
                throw new RuntimeException('Billing account not found');
            }

            // Calculate new balances using waterfall method
            $balance = $this->calculateBalance($billingAccount, $credits);

            // Validate overage rules
            if ($balance['new']['overage'] < 0 && !$billingAccount->allow_overage) {
                throw new RuntimeException('Account does not allow overage');
            }

            // Log to ClickHouse for analytics
            $this->logCredits(
                $billingAccount->owner_account_id,
                $credits,
                $reason,
                0, // object_id
                time(),
                $notes
            );

            // Record settlement
            $this->addSettlement(
                $billingAccountId,
                date('Y-m-d H:i:s'),
                $balance['new']['subscription'],
                $balance['new']['prepaid'],
                $balance['new']['overage'],
                $balance['new']['total'],
                'completed'
            );

            // Update billing account
            $this->updateBillingAccountCredits(
                $billingAccountId,
                $balance['new']['subscription'],
                $balance['new']['prepaid'],
                $balance['new']['overage'],
                $balance['new']['total']
            );

            // Commit transaction
            tiny::db()->getPdo()->commit();
            
            // Clear cache
            tiny::cache()->delete("billing_account_{$billingAccountId}");

            return $balance;
            
        } catch (Exception $e) {
            tiny::db()->getPdo()->rollback();
            throw $e;
        }
    }

    /**
     * Calculate credit balance using waterfall method
     */
    private function calculateBalance(object $account, int $credits): array
    {
        $current = [
            'subscription' => $account->subscription_credits,
            'prepaid' => $account->prepaid_credits,
            'overage' => $account->overage_credits,
            'total' => $account->total_credits
        ];

        $new = $current;
        $remaining = $credits;

        // Waterfall allocation: subscription → prepaid → overage
        if ($remaining > 0) {
            // Adding credits
            $new['subscription'] += $remaining;
        } else {
            // Removing credits
            $toRemove = abs($remaining);
            
            // Remove from subscription first
            $fromSubscription = min($toRemove, $new['subscription']);
            $new['subscription'] -= $fromSubscription;
            $toRemove -= $fromSubscription;
            
            // Then from prepaid
            if ($toRemove > 0) {
                $fromPrepaid = min($toRemove, $new['prepaid']);
                $new['prepaid'] -= $fromPrepaid;
                $toRemove -= $fromPrepaid;
            }
            
            // Finally from overage (can go negative)
            if ($toRemove > 0) {
                $new['overage'] -= $toRemove;
            }
        }

        $new['total'] = $new['subscription'] + $new['prepaid'] + $new['overage'];

        return compact('current', 'new', 'credits');
    }

    /**
     * Log credits to ClickHouse for analytics
     */
    private function logCredits(int $accountId, int $credits, string $reason, int $objectId, int $timestamp, ?string $notes): void
    {
        tiny::clickhouse()->insert('buffer_credit_usage_logs', [
            'account_id' => $accountId,
            'credits' => $credits,
            'reason' => $reason,
            'object_id' => $objectId,
            'dts' => date('Y-m-d H:i:s', $timestamp),
            'note' => $notes ?? ''
        ]);
    }
}
```

#### Model Best Practices

1. **Use Validation Schemas**: Define clear validation rules for all input
2. **Implement Caching**: Cache frequently accessed data with appropriate TTL
3. **Transaction Support**: Use database transactions for complex operations
4. **Error Handling**: Throw meaningful exceptions with context
5. **Performance**: Optimize queries and use appropriate indexes
6. **Testing**: Write unit tests for all business logic

### Views (`app/views/`) - Presentation Layer

Views in Tiny provide a powerful, secure templating system with:
- **Automatic Escaping**: XSS protection by default
- **Component System**: Reusable UI elements with props
- **Layout Inheritance**: Consistent page structure
- **Data Binding**: Clean separation between logic and presentation

#### Advanced View Example: Billing Dashboard

```php
<!-- app/views/billing/index.php -->
<?php 
$billingAccount = tiny::data()->billingAccount;
$transactions = tiny::data()->transactions;
$canManageBilling = tiny::user()->billing_account_is_owner;
?>

<div class="billing-dashboard" x-data="billingDashboard()">
    <header class="dashboard-header">
        <h1>Billing Overview</h1>
        <?php if ($canManageBilling): ?>
            <div class="action-buttons">
                <a href="/billing/credits" class="btn btn-primary">Purchase Credits</a>
                <a href="/billing/subscription" class="btn btn-secondary">Manage Subscription</a>
            </div>
        <?php endif; ?>
    </header>

    <!-- Credit Balance Component -->
    <?php tiny::component('CreditBalance', [
        'subscription_credits' => $billingAccount->subscription_credits,
        'prepaid_credits' => $billingAccount->prepaid_credits,
        'overage_credits' => $billingAccount->overage_credits,
        'total_credits' => $billingAccount->total_credits,
        'allow_overage' => $billingAccount->allow_overage,
        'can_manage' => $canManageBilling
    ]); ?>

    <!-- Subscription Status -->
    <?php if ($billingAccount->subscription_count > 0): ?>
        <?php tiny::component('SubscriptionStatus', [
            'subscriptions' => tiny::data()->subscriptions,
            'can_manage' => $canManageBilling
        ]); ?>
    <?php else: ?>
        <?php tiny::component('EmptyState', [
            'title' => 'No Active Subscription',
            'message' => 'Upgrade to a subscription plan to get monthly credits and additional features.',
            'action_url' => '/billing/subscribe',
            'action_text' => 'Choose a Plan',
            'show_action' => $canManageBilling
        ]); ?>
    <?php endif; ?>

    <!-- Transaction History -->
    <section class="transaction-history">
        <h2>Recent Transactions</h2>
        <?php if (!empty($transactions)): ?>
            <div class="transactions-list">
                <?php foreach ($transactions as $transaction): ?>
                    <?php tiny::component('TransactionRow', [
                        'transaction' => $transaction,
                        'show_details' => $canManageBilling
                    ]); ?>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($transactions) >= 10): ?>
                <div class="load-more">
                    <a href="/billing/transactions" class="btn btn-outline">View All Transactions</a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php tiny::component('EmptyState', [
                'title' => 'No Transactions',
                'message' => 'No billing transactions found for this account.',
                'icon' => 'receipt'
            ]); ?>
        <?php endif; ?>
    </section>
</div>

<!-- Alpine.js Component for Interactivity -->
<script>
function billingDashboard() {
    return {
        refreshing: false,
        
        async refreshData() {
            this.refreshing = true;
            try {
                const response = await fetch('/api/billing/dashboard', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    // Update data and refresh components
                    location.reload();
                }
            } catch (error) {
                console.error('Failed to refresh billing data:', error);
            } finally {
                this.refreshing = false;
            }
        }
    }
}
</script>
```

#### Component Example: CreditBalance

```php
<!-- app/views/components/CreditBalance.php -->
<?php
$subscription = $props['subscription_credits'] ?? 0;
$prepaid = $props['prepaid_credits'] ?? 0;
$overage = $props['overage_credits'] ?? 0;
$total = $props['total_credits'] ?? 0;
$allowOverage = $props['allow_overage'] ?? false;
$canManage = $props['can_manage'] ?? false;

$hasLowCredits = $total < 1000;
$hasNegativeCredits = $total < 0;
?>

<div class="credit-balance-card <?= $hasNegativeCredits ? 'negative' : ($hasLowCredits ? 'low' : '') ?>">
    <div class="card-header">
        <h3>Credit Balance</h3>
        <?php if ($canManage): ?>
            <button class="btn-icon" onclick="location.href='/billing/credits'" title="Manage Credits">
                <svg class="icon"><!-- gear icon --></svg>
            </button>
        <?php endif; ?>
    </div>
    
    <div class="credit-breakdown">
        <div class="total-credits">
            <span class="amount"><?= number_format($total) ?></span>
            <span class="label">Total Credits</span>
        </div>
        
        <div class="credit-types">
            <div class="credit-type subscription">
                <span class="amount"><?= number_format($subscription) ?></span>
                <span class="label">Subscription</span>
            </div>
            <div class="credit-type prepaid">
                <span class="amount"><?= number_format($prepaid) ?></span>
                <span class="label">Prepaid</span>
            </div>
            <?php if ($overage != 0): ?>
                <div class="credit-type overage <?= $overage < 0 ? 'negative' : 'positive' ?>">
                    <span class="amount"><?= number_format($overage) ?></span>
                    <span class="label">Overage</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($hasNegativeCredits && !$allowOverage): ?>
        <div class="alert alert-error">
            <strong>Account Suspended:</strong> Your account has exceeded its credit limit.
            <?php if ($canManage): ?>
                <a href="/billing/credits">Purchase credits</a> to restore service.
            <?php else: ?>
                Please contact your account administrator.
            <?php endif; ?>
        </div>
    <?php elseif ($hasLowCredits): ?>
        <div class="alert alert-warning">
            <strong>Low Credits:</strong> Consider purchasing additional credits.
            <?php if ($canManage): ?>
                <a href="/billing/credits">Purchase now</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
```

#### View Best Practices

1. **Security First**: Always escape output using `htmlspecialchars()` or framework helpers
2. **Component Reuse**: Break complex UIs into reusable components
3. **Conditional Rendering**: Use PHP conditionals for dynamic content
4. **Progressive Enhancement**: Use Alpine.js for interactivity without breaking basic functionality
5. **Accessibility**: Include proper ARIA labels and semantic HTML
6. **Performance**: Minimize inline styles and scripts

### Controllers (`app/controllers/`) - Request Coordination Layer

Controllers orchestrate the request/response cycle by:
- **Request Processing**: Handling HTTP methods and validating input
- **Business Logic Coordination**: Calling appropriate models and services
- **Response Formation**: Preparing data for views or API responses
- **Error Handling**: Managing exceptions and validation errors

#### Advanced Controller Example: Billing Management

```php
<?php

declare(strict_types=1);

class BillingController extends TinyController
{
    private BillingModel $billingModel;
    private UserModel $userModel;
    
    public function __construct()
    {
        parent::__construct();
        
        // Dependency injection
        $this->billingModel = tiny::model('billing');
        $this->userModel = tiny::model('user');
        
        // Ensure user is authenticated
        if (!tiny::user()->authenticated) {
            tiny::response()->redirect('/login');
            exit;
        }
    }
    
    /**
     * GET /billing - Display billing dashboard
     */
    public function get($request, $response)
    {
        try {
            $userId = (int)tiny::user()->user_id;
            $billingAccount = $this->billingModel->getBillingAccount($userId);
            
            if (!$billingAccount) {
                return $this->renderEmptyState($response, 'No billing account found');
            }
            
            // Check if user is the billing account owner
            $isOwner = $billingAccount->owner_account_id === $userId;
            tiny::user()->billing_account_is_owner = $isOwner;
            
            // Gather dashboard data
            $data = [
                'billingAccount' => $billingAccount,
                'transactions' => $this->billingModel->getRecentTransactions($billingAccount->billing_account_id, 10),
                'subscriptions' => $this->billingModel->getActiveSubscriptions($billingAccount->billing_account_id),
                'usage' => $this->billingModel->getCurrentMonthUsage($billingAccount->billing_account_id)
            ];
            
            // Pass data to view
            tiny::data()->merge($data);
            
            $response->render();
            
        } catch (Exception $e) {
            error_log("Billing dashboard error: " . $e->getMessage());
            return $this->renderError($response, 'Unable to load billing information');
        }
    }
    
    /**
     * POST /billing/credits - Purchase credits
     */
    public function post($request, $response)
    {
        // Validate CSRF token
        if (!$request->isValidCSRF()) {
            return $this->jsonError($response, 'Invalid security token', 403);
        }
        
        // Verify ownership
        if (!tiny::user()->billing_account_is_owner) {
            return $this->jsonError($response, 'Insufficient permissions', 403);
        }
        
        try {
            $requestData = $request->body(true);
            
            // Validate input
            $rules = [
                'credits' => 'required|integer|min:100|max:100000',
                'payment_method_id' => 'required|string',
                'password' => 'required|string'
            ];
            
            if (!$this->validateInput($requestData, $rules)) {
                return $this->jsonError($response, 'Invalid input data', 400);
            }
            
            // Verify password
            if (!$this->userModel->verifyPassword($requestData['password'], tiny::user()->user_id)) {
                return $this->jsonError($response, 'Invalid password', 401);
            }
            
            // Process credit purchase
            $result = $this->billingModel->purchaseCredits(
                tiny::user()->billing_account_id,
                $requestData['credits'],
                $requestData['payment_method_id']
            );
            
            if ($result['success']) {
                // Set success message
                tiny::flash('toast')->set([
                    'level' => 'success',
                    'message' => "Successfully purchased {$requestData['credits']} credits"
                ]);
                
                return $response->sendJSON([
                    'success' => true,
                    'message' => 'Credits purchased successfully',
                    'new_balance' => $result['new_balance']
                ]);
            } else {
                return $this->jsonError($response, $result['error'] ?? 'Purchase failed', 400);
            }
            
        } catch (Exception $e) {
            error_log("Credit purchase error: " . $e->getMessage());
            return $this->jsonError($response, 'Transaction failed', 500);
        }
    }
    
    /**
     * PATCH /billing/account - Update billing account
     */
    public function patch($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $this->jsonError($response, 'Invalid security token', 403);
        }
        
        if (!tiny::user()->billing_account_is_owner) {
            return $this->jsonError($response, 'Insufficient permissions', 403);
        }
        
        try {
            $requestData = $request->body(true);
            
            // Validate against billing account schema
            if (!$this->billingModel->validateBillingAccount($requestData)) {
                tiny::data()->errors = $this->billingModel->getValidationErrors();
                return $response->render();
            }
            
            // Update billing account
            $success = $this->billingModel->updateBillingAccount(
                tiny::user()->billing_account_id,
                $requestData
            );
            
            if ($success) {
                tiny::flash('toast')->set([
                    'level' => 'success',
                    'message' => 'Billing information updated successfully'
                ]);
                
                return $response->redirect('/billing');
            } else {
                tiny::data()->errors = ['general' => 'Failed to update billing information'];
                return $response->render();
            }
            
        } catch (Exception $e) {
            error_log("Billing update error: " . $e->getMessage());
            tiny::data()->errors = ['general' => 'An error occurred while updating your information'];
            return $response->render();
        }
    }
    
    /**
     * DELETE /billing/subscription/{id} - Cancel subscription
     */
    public function delete($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $this->jsonError($response, 'Invalid security token', 403);
        }
        
        if (!tiny::user()->billing_account_is_owner) {
            return $this->jsonError($response, 'Insufficient permissions', 403);
        }
        
        try {
            $subscriptionId = $request->path->slug; // Gets ID from URL
            
            $result = $this->billingModel->cancelSubscription(
                tiny::user()->billing_account_id,
                $subscriptionId
            );
            
            if ($result['success']) {
                tiny::flash('toast')->set([
                    'level' => 'success',
                    'message' => 'Subscription cancelled successfully'
                ]);
                
                return $response->sendJSON(['success' => true]);
            } else {
                return $this->jsonError($response, $result['error'], 400);
            }
            
        } catch (Exception $e) {
            error_log("Subscription cancellation error: " . $e->getMessage());
            return $this->jsonError($response, 'Cancellation failed', 500);
        }
    }
    
    /**
     * Helper: Validate input against rules
     */
    private function validateInput(array $data, array $rules): bool
    {
        foreach ($rules as $field => $rule) {
            $constraints = explode('|', $rule);
            $value = $data[$field] ?? null;
            
            foreach ($constraints as $constraint) {
                if ($constraint === 'required' && empty($value)) {
                    return false;
                }
                
                if (str_starts_with($constraint, 'min:')) {
                    $min = (int)substr($constraint, 4);
                    if (is_numeric($value) && $value < $min) {
                        return false;
                    }
                }
                
                if (str_starts_with($constraint, 'max:')) {
                    $max = (int)substr($constraint, 4);
                    if (is_numeric($value) && $value > $max) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Helper: Render error page
     */
    private function renderError($response, string $message): void
    {
        tiny::data()->error_message = $message;
        $response->render('errors/billing');
    }
    
    /**
     * Helper: Send JSON error response
     */
    private function jsonError($response, string $message, int $code = 400)
    {
        return $response->sendJSON([
            'success' => false,
            'error' => $message
        ], $code);
    }
    
    /**
     * Helper: Render empty state
     */
    private function renderEmptyState($response, string $message): void
    {
        tiny::data()->empty_message = $message;
        $response->render('billing/empty');
    }
}
```

#### Controller Best Practices

1. **Thin Controllers**: Keep business logic in models, coordination in controllers
2. **Input Validation**: Always validate and sanitize user input
3. **Error Handling**: Implement comprehensive error handling with proper logging
4. **Security**: Verify CSRF tokens, check permissions, validate ownership
5. **Response Types**: Use appropriate HTTP status codes and response formats
6. **Dependency Injection**: Inject dependencies in constructor for testability
7. **Single Responsibility**: Each method should handle one specific operation

## Advanced Data Flow Patterns

### 1. Request Processing Pipeline

```php
// Example: POST /billing/credits (Credit Purchase)

1. HTTP Request
   ↓
2. Middleware Stack
   ├── Authentication (verify user session)
   ├── Authorization (check billing permissions) 
   ├── CSRF Protection (validate token)
   └── Rate Limiting (prevent abuse)
   ↓
3. Router
   ├── URL Analysis: /billing/credits
   ├── Controller Resolution: BillingController
   ├── Method Resolution: post()
   └── Parameter Extraction
   ↓
4. Controller Execution
   ├── Input Validation (credits, payment method)
   ├── Business Logic Coordination
   └── Response Preparation
   ↓
5. Model Operations (in transaction)
   ├── Stripe Payment Processing
   ├── Credit Balance Update
   ├── Transaction Recording
   └── Cache Invalidation
   ↓
6. Response Generation
   ├── Success: JSON + Flash Message
   ├── Error: JSON Error + HTTP Status
   └── Redirect: Location Header
```

### 2. Error Handling Flow

```php
// Exception handling at each layer

Try {
    // 1. Controller validation
    if (!$this->validateInput($data)) {
        throw new ValidationException('Invalid input');
    }
    
    // 2. Model operations
    $result = $this->model->processPayment($data);
    
    // 3. External API calls
    $stripeResult = $this->stripe->createCharge($result);
    
} catch (ValidationException $e) {
    // Return user-friendly error
    return $this->jsonError($response, $e->getMessage(), 400);
    
} catch (PaymentException $e) {
    // Log financial error, return generic message
    error_log("Payment error: " . $e->getMessage());
    return $this->jsonError($response, 'Payment processing failed', 500);
    
} catch (Exception $e) {
    // Log unexpected error, return generic message
    error_log("Unexpected error: " . $e->getMessage());
    return $this->jsonError($response, 'An error occurred', 500);
}
```

### 3. Caching Strategy

```php
// Multi-layer caching for optimal performance

1. OPcache (Bytecode)
   └── Automatically caches compiled PHP
   
2. APCu (Application Data)
   ├── User sessions and authentication
   ├── Database query results
   └── Computed values
   
3. Memcached (Distributed)
   ├── Cross-server shared data
   ├── Large datasets
   └── Complex query results
   
4. Database Query Cache
   ├── Frequently accessed records
   ├── Configuration data
   └── Lookup tables
```

### 4. Real-World Data Flow Example

**Scenario**: User purchases 5,000 credits via credit card

```php
// 1. User submits form (client-side)
fetch('/billing/credits', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({
        credits: 5000,
        payment_method_id: 'pm_1234567890',
        password: userPassword
    })
});

// 2. Server processing (Tiny framework)
class BillingController {
    public function post($request, $response) {
        // a. Validate CSRF
        if (!$request->isValidCSRF()) {
            return $this->jsonError($response, 'Invalid token', 403);
        }
        
        // b. Validate ownership
        if (!tiny::user()->billing_account_is_owner) {
            return $this->jsonError($response, 'Unauthorized', 403);
        }
        
        // c. Validate input
        $data = $request->body(true);
        if (!$this->validatePurchaseData($data)) {
            return $this->jsonError($response, 'Invalid data', 400);
        }
        
        // d. Verify password
        if (!$this->userModel->verifyPassword($data['password'])) {
            return $this->jsonError($response, 'Invalid password', 401);
        }
        
        // e. Process payment (transactional)
        try {
            $result = $this->billingModel->purchaseCredits(
                $data['credits'],
                $data['payment_method_id']
            );
            
            return $response->sendJSON([
                'success' => true,
                'new_balance' => $result['balance'],
                'transaction_id' => $result['transaction_id']
            ]);
            
        } catch (Exception $e) {
            return $this->jsonError($response, 'Purchase failed', 500);
        }
    }
}

// 3. Model operations (BillingModel)
public function purchaseCredits(int $credits, string $paymentMethodId): array {
    tiny::db()->beginTransaction();
    
    try {
        // a. Create Stripe payment intent
        $amount = $credits * $this->getCreditCost();
        $paymentIntent = $this->stripe->createPaymentIntent([
            'amount' => $amount,
            'currency' => 'usd',
            'payment_method' => $paymentMethodId,
            'confirm' => true
        ]);
        
        // b. Record transaction
        $transactionId = $this->recordTransaction([
            'billing_account_id' => tiny::user()->billing_account_id,
            'amount' => $amount,
            'credits' => $credits,
            'stripe_payment_intent_id' => $paymentIntent->id,
            'status' => 'completed'
        ]);
        
        // c. Update credit balance
        $newBalance = $this->addCredits(
            tiny::user()->billing_account_id,
            $credits,
            'purchase',
            "Credit purchase - Transaction {$transactionId}"
        );
        
        // d. Log to ClickHouse for analytics
        $this->logCreditUsage([
            'account_id' => tiny::user()->user_id,
            'credits' => $credits,
            'reason' => 'purchase',
            'transaction_id' => $transactionId,
            'timestamp' => time()
        ]);
        
        // e. Clear relevant caches
        $this->clearBillingCache(tiny::user()->billing_account_id);
        
        tiny::db()->commit();
        
        return [
            'success' => true,
            'balance' => $newBalance,
            'transaction_id' => $transactionId
        ];
        
    } catch (Exception $e) {
        tiny::db()->rollback();
        throw $e;
    }
}

// 4. Response handling (client-side)
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Update UI with new balance
        updateCreditBalance(data.new_balance);
        
        // Show success message
        showToast('Credits purchased successfully!', 'success');
        
        // Refresh transaction history
        refreshTransactionHistory();
    } else {
        showToast(data.error, 'error');
    }
})
.catch(error => {
    showToast('Network error occurred', 'error');
});
```

## Production Best Practices

### Model Layer Excellence

1. **Data Integrity**
   ```php
   // Always use database transactions for multi-step operations
   public function transferCredits(int $fromAccount, int $toAccount, int $credits): bool {
       tiny::db()->beginTransaction();
       
       try {
           $this->deductCredits($fromAccount, $credits);
           $this->addCredits($toAccount, $credits);
           $this->logTransfer($fromAccount, $toAccount, $credits);
           
           tiny::db()->commit();
           return true;
       } catch (Exception $e) {
           tiny::db()->rollback();
           throw $e;
       }
   }
   ```

2. **Validation Schemas**
   ```php
   // Define comprehensive validation rules
   public array $schemas = [
       'billing_account' => [
           'name' => 'required|string:255|sanitize',
           'email' => 'required|email:255|unique:billing_accounts',
           'phone' => 'nullable|phone|format:international',
           'credits' => 'integer|min:0|max:1000000'
       ]
   ];
   ```

3. **Performance Optimization**
   ```php
   // Use caching for expensive operations
   public function getBillingStats(int $accountId): array {
       $cacheKey = "billing_stats_{$accountId}";
       $cached = tiny::cache()->get($cacheKey);
       
       if ($cached) return $cached;
       
       $stats = $this->calculateComplexStats($accountId);
       tiny::cache()->set($cacheKey, $stats, 300); // 5 minutes
       
       return $stats;
   }
   ```

### View Layer Security

1. **Output Escaping**
   ```php
   <!-- Always escape user data -->
   <h1><?= htmlspecialchars($billingAccount->name, ENT_QUOTES, 'UTF-8') ?></h1>
   
   <!-- Use framework helpers for common cases -->
   <p><?= tiny::escape($user->bio) ?></p>
   
   <!-- Be extra careful with JSON data -->
   <script>
   const userData = <?= json_encode($user, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
   </script>
   ```

2. **Component Isolation**
   ```php
   <!-- Components should validate their props -->
   <?php
   $credits = (int)($props['credits'] ?? 0);
   $allowOverage = (bool)($props['allow_overage'] ?? false);
   $accountName = htmlspecialchars($props['account_name'] ?? 'Unknown', ENT_QUOTES);
   ?>
   ```

### Controller Layer Robustness

1. **Input Validation Pipeline**
   ```php
   public function post($request, $response) {
       // 1. CSRF Protection
       if (!$request->isValidCSRF()) {
           return $this->securityError($response, 'Invalid CSRF token');
       }
       
       // 2. Authorization
       if (!$this->hasPermission('billing.manage')) {
           return $this->accessDenied($response);
       }
       
       // 3. Rate Limiting
       if (!$this->checkRateLimit($request)) {
           return $this->rateLimited($response);
       }
       
       // 4. Input Validation
       $data = $this->validateAndSanitize($request->body(true));
       if (!$data) {
           return $this->validationError($response);
       }
       
       // 5. Business Logic
       try {
           $result = $this->processRequest($data);
           return $this->successResponse($response, $result);
       } catch (Exception $e) {
           return $this->handleException($response, $e);
       }
   }
   ```

2. **Error Handling Strategy**
   ```php
   private function handleException($response, Exception $e): void {
       // Log all exceptions with context
       error_log(sprintf(
           "Controller error: %s in %s:%d\nUser: %d\nRequest: %s",
           $e->getMessage(),
           $e->getFile(),
           $e->getLine(),
           tiny::user()->user_id ?? 0,
           json_encode($_REQUEST)
       ));
       
       // Different responses based on exception type
       if ($e instanceof ValidationException) {
           return $this->validationError($response, $e->getErrors());
       } elseif ($e instanceof PaymentException) {
           return $this->paymentError($response, $e->getMessage());
       } else {
           return $this->genericError($response);
       }
   }
   ```

### Performance & Scalability

1. **Database Optimization**
   ```php
   // Use prepared statements for repeated queries
   private $preparedQueries = [];
   
   public function getUserCredits(int $userId): int {
       if (!isset($this->preparedQueries['user_credits'])) {
           $this->preparedQueries['user_credits'] = tiny::db()->prepare(
               "SELECT total_credits FROM billing_accounts WHERE owner_account_id = ?"
           );
       }
       
       return $this->preparedQueries['user_credits']->execute([$userId])->fetchColumn();
   }
   ```

2. **Memory Management**
   ```php
   // Process large datasets in chunks
   public function processAllAccounts(): void {
       $offset = 0;
       $limit = 100;
       
       while (true) {
           $accounts = $this->getAccountsBatch($offset, $limit);
           if (empty($accounts)) break;
           
           foreach ($accounts as $account) {
               $this->processAccount($account);
           }
           
           $offset += $limit;
           
           // Free memory
           unset($accounts);
           gc_collect_cycles();
       }
   }
   ```

### Testing Strategy

1. **Unit Testing Models**
   ```php
   public function testCreditAdjustment(): void {
       $model = new BillingModel();
       
       // Test validation
       $this->assertFalse($model->adjustCredits(1, 0, 'test')); // Zero credits
       $this->assertFalse($model->adjustCredits(1, -1000, 'test')); // Overage violation
       
       // Test success case
       $result = $model->adjustCredits(1, 1000, 'test purchase');
       $this->assertTrue($result['success']);
       $this->assertEquals(1000, $result['new_balance']['prepaid']);
   }
   ```

2. **Integration Testing Controllers**
   ```php
   public function testCreditPurchase(): void {
       // Setup test data
       $user = $this->createTestUser();
       $this->actingAs($user);
       
       // Mock external services
       $this->mockStripeSuccess();
       
       // Test the endpoint
       $response = $this->post('/billing/credits', [
           'credits' => 1000,
           'payment_method_id' => 'pm_test',
           'password' => 'test123'
       ]);
       
       $response->assertSuccessful();
       $response->assertJson(['success' => true]);
       
       // Verify database changes
       $this->assertDatabaseHas('billing_accounts_transactions', [
           'billing_account_id' => $user->billing_account_id,
           'amount' => 800, // 1000 credits * $0.0008
           'transaction_type' => 'credits_purchase'
       ]);
   }
   ```

### Security Checklist

- ✅ **CSRF Protection**: All state-changing operations validate CSRF tokens
- ✅ **Input Validation**: All user input is validated and sanitized
- ✅ **Output Escaping**: All output is properly escaped for context
- ✅ **SQL Injection**: All database queries use parameterized statements
- ✅ **XSS Prevention**: User content is escaped before rendering
- ✅ **Authentication**: Sensitive operations require re-authentication
- ✅ **Authorization**: Permission checks on all protected resources
- ✅ **Rate Limiting**: API endpoints are protected from abuse
- ✅ **Error Handling**: Errors don't leak sensitive information
- ✅ **Logging**: Security events are logged for monitoring

---

## Next Steps in Your MVC Journey

1. **Master [Routing](routing.md)**: Learn URL mapping and parameter handling
2. **Build [Controllers](controllers.md)**: Create robust request handlers
3. **Design [Views](views.md)**: Build dynamic, secure user interfaces
4. **Model [Data Access](database.md)**: Optimize database operations
5. **Implement [Middleware](middleware.md)**: Add cross-cutting concerns

Each section builds upon the MVC foundation with practical examples and production-ready patterns.
