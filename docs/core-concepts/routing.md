[Home](../readme.md) | [Getting Started](../getting-started) | [Core Concepts](../core-concepts) | [Helpers](../helpers) | [Extensions](../extensions) | [Repo](https://github.com/ranaroussi/tiny)

# Smart Routing in Tiny

Tiny's routing system combines convention-over-configuration simplicity with the flexibility needed for complex applications. It automatically maps URLs to controllers based on file structure while providing powerful customization options for advanced use cases.

## Routing Philosophy

**Zero Configuration**: Routes work out of the box based on your controller file structure
**Performance Optimized**: Route resolution is cached and optimized for high-traffic applications
**RESTful by Design**: Natural HTTP method mapping with semantic URL structures
**Developer Friendly**: Intuitive parameter access and flexible response handling

## Convention-Based Auto-Routing

Tiny automatically generates routes from your controller file structure, eliminating the need for manual route definitions in most cases:

### Basic File-to-Route Mapping

```
app/controllers/
├── home.php              -> GET/POST/PATCH/DELETE /
├── about.php             -> GET/POST/PATCH/DELETE /about
├── blog.php              -> GET/POST/PATCH/DELETE /blog
├── contact.php           -> GET/POST/PATCH/DELETE /contact
└── api/
    ├── index.php         -> GET/POST/PATCH/DELETE /api
    ├── users.php         -> GET/POST/PATCH/DELETE /api/users
    └── billing/
        ├── index.php     -> GET/POST/PATCH/DELETE /api/billing
        ├── credits.php   -> GET/POST/PATCH/DELETE /api/billing/credits
        └── invoices.php  -> GET/POST/PATCH/DELETE /api/billing/invoices
```

### Advanced Directory Structures

```
app/controllers/
├── account/
│   ├── index.php         -> /account (account dashboard)
│   ├── profile.php       -> /account/profile
│   ├── security.php      -> /account/security
│   └── billing/
│       ├── index.php     -> /account/billing (billing overview)
│       ├── subscription.php -> /account/billing/subscription
│       ├── transactions.php -> /account/billing/transactions
│       ├── credits.php   -> /account/billing/credits
│       └── invoices.php  -> /account/billing/invoices
├── admin/
│   ├── index.php         -> /admin (admin dashboard)
│   ├── users.php         -> /admin/users
│   ├── billing.php       -> /admin/billing
│   └── reports/
│       ├── index.php     -> /admin/reports
│       ├── usage.php     -> /admin/reports/usage
│       └── financial.php -> /admin/reports/financial
└── webhooks/
    ├── stripe.php        -> /webhooks/stripe
    ├── mailgun.php       -> /webhooks/mailgun
    └── twilio.php        -> /webhooks/twilio
```

### Special Route Handling

```
app/controllers/
├── 404.php              -> Custom 404 error handler
├── 500.php              -> Custom 500 error handler
├── api.php              -> /api (if no api/index.php exists)
└── go.php               -> /go (URL shortener/redirector)
```

## HTTP Method Routing

Each controller automatically handles multiple HTTP methods through dedicated methods:

### Complete HTTP Method Support

```php
<?php
// app/controllers/billing/credits.php

class BillingCredits extends TinyController
{
    /**
     * GET /billing/credits - Show credit purchase page
     */
    public function get($request, $response)
    {
        // Display credit purchase options and current balance
        $billingAccount = tiny::model('billing')->getBillingAccount(tiny::user()->billing_account_id);
        $creditPacks = tiny::model('billing')->getCreditPacks();
        
        $response->render('billing/credits', [
            'billingAccount' => $billingAccount,
            'creditPacks' => $creditPacks,
            'currentBalance' => $billingAccount->total_credits
        ]);
    }

    /**
     * POST /billing/credits - Purchase credits
     */
    public function post($request, $response)
    {
        // Validate CSRF token
        if (!$request->isValidCSRF()) {
            return $this->csrfError($response);
        }
        
        // Process credit purchase
        $data = $request->body(true);
        $result = tiny::model('billing')->purchaseCredits(
            tiny::user()->billing_account_id,
            $data['credits'],
            $data['payment_method_id']
        );
        
        if ($result['success']) {
            tiny::flash('toast')->set([
                'level' => 'success',
                'message' => "Successfully purchased {$data['credits']} credits"
            ]);
            return $response->redirect('/billing/credits');
        } else {
            tiny::data()->error = $result['error'];
            return $this->get($request, $response);
        }
    }

    /**
     * PATCH /billing/credits - Update auto-topup settings
     */
    public function patch($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $this->csrfError($response);
        }
        
        $data = $request->body(true);
        $success = tiny::model('billing')->updateTopupSettings(
            tiny::user()->billing_account_id,
            [
                'topup_threshold' => (int)$data['threshold'],
                'topup_amount' => (int)$data['amount'],
                'topup_enabled' => (bool)$data['enabled']
            ]
        );
        
        return $response->sendJSON([
            'success' => $success,
            'message' => $success ? 'Auto-topup settings updated' : 'Failed to update settings'
        ]);
    }

    /**
     * DELETE /billing/credits/{transaction_id} - Refund credit purchase
     */
    public function delete($request, $response)
    {
        if (!$request->isValidCSRF()) {
            return $this->csrfError($response);
        }
        
        $transactionId = $request->path->slug; // Gets the transaction ID from URL
        
        $result = tiny::model('billing')->refundTransaction(
            tiny::user()->billing_account_id,
            $transactionId
        );
        
        return $response->sendJSON([
            'success' => $result['success'],
            'message' => $result['message'] ?? 'Refund processed'
        ]);
    }

    /**
     * Helper: Handle CSRF errors consistently
     */
    private function csrfError($response)
    {
        tiny::flash('toast')->set([
            'level' => 'error',
            'message' => 'Security token invalid. Please try again.'
        ]);
        return $response->redirect('/billing/credits');
    }
}
```

## URL Parameter Handling

Tiny provides flexible URL parameter access through the request object with automatic type conversion and validation:

### Basic Parameter Access

```php
public function get($request, $response)
{
    // URL: /billing/transactions/2024/03
    $year = $request->path->year;    // "2024" (string)
    $month = $request->path->month;  // "03" (string)
    
    // URL: /billing/invoice/inv_1234567890
    $invoiceId = $request->path->slug; // "inv_1234567890"
    
    // URL: /admin/users/123/edit
    $userId = $request->path->section; // "123"
    $action = $request->path->slug;    // "edit"
}
```

### Advanced Parameter Patterns

```php
// app/controllers/billing/transactions.php
class BillingTransactions extends TinyController
{
    public function get($request, $response)
    {
        // Parse different URL patterns:
        
        // Pattern 1: /billing/transactions
        if (empty($request->path->section)) {
            return $this->showAllTransactions($request, $response);
        }
        
        // Pattern 2: /billing/transactions/2024
        if (is_numeric($request->path->section) && strlen($request->path->section) === 4) {
            $year = (int)$request->path->section;
            $month = $request->path->slug ? (int)$request->path->slug : null;
            return $this->showTransactionsByDate($year, $month, $response);
        }
        
        // Pattern 3: /billing/transactions/txn_1234567890
        if (str_starts_with($request->path->section, 'txn_')) {
            $transactionId = $request->path->section;
            return $this->showTransactionDetails($transactionId, $response);
        }
        
        // Pattern 4: /billing/transactions/export
        if ($request->path->section === 'export') {
            return $this->exportTransactions($request, $response);
        }
        
        // Invalid pattern - show 404
        return $this->notFound($response);
    }
    
    private function showTransactionsByDate(int $year, ?int $month, $response)
    {
        $startDate = $month ? "$year-$month-01" : "$year-01-01";
        $endDate = $month ? date('Y-m-t', strtotime($startDate)) : "$year-12-31";
        
        $transactions = tiny::model('billing')->getTransactionsByDateRange(
            tiny::user()->billing_account_id,
            $startDate,
            $endDate
        );
        
        tiny::data()->merge([
            'transactions' => $transactions,
            'year' => $year,
            'month' => $month,
            'period' => $month ? date('F Y', strtotime($startDate)) : $year
        ]);
        
        $response->render('billing/transactions-by-date');
    }
}
```

### Parameter Validation and Sanitization

```php
public function get($request, $response)
{
    // Validate and sanitize parameters
    $userId = filter_var($request->path->section, FILTER_VALIDATE_INT);
    if (!$userId) {
        return $this->badRequest($response, 'Invalid user ID');
    }
    
    // Validate UUID format
    $uuid = $request->path->slug;
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
        return $this->badRequest($response, 'Invalid UUID format');
    }
    
    // Safe string parameter
    $action = preg_replace('/[^a-z0-9-]/', '', strtolower($request->path->action ?? ''));
}
```

## Query String Processing

Handle complex query parameters with validation, pagination, filtering, and sorting:

### Basic Query Parameter Access

```php
public function get($request, $response)
{
    // URL: /billing/transactions?page=2&limit=50&status=completed&date_from=2024-01-01
    $page = (int)($request->query['page'] ?? 1);
    $limit = min(100, max(10, (int)($request->query['limit'] ?? 25))); // Between 10-100
    $status = $request->query['status'] ?? null;
    $dateFrom = $request->query['date_from'] ?? null;
    $dateTo = $request->query['date_to'] ?? null;
}
```

### Advanced Query Processing with Validation

```php
// app/controllers/billing/transactions.php
class BillingTransactions extends TinyController
{
    public function get($request, $response)
    {
        // Parse and validate query parameters
        $params = $this->parseQueryParams($request->query);
        
        // Get filtered transactions
        $result = tiny::model('billing')->getTransactions(
            tiny::user()->billing_account_id,
            $params
        );
        
        // Prepare pagination data
        $pagination = [
            'current_page' => $params['page'],
            'per_page' => $params['limit'],
            'total' => $result['total'],
            'total_pages' => ceil($result['total'] / $params['limit']),
            'has_next' => $params['page'] < ceil($result['total'] / $params['limit']),
            'has_prev' => $params['page'] > 1
        ];
        
        tiny::data()->merge([
            'transactions' => $result['transactions'],
            'pagination' => $pagination,
            'filters' => $params['filters'],
            'sort' => $params['sort']
        ]);
        
        $response->render();
    }
    
    private function parseQueryParams(array $query): array
    {
        // Pagination
        $page = max(1, (int)($query['page'] ?? 1));
        $limit = min(100, max(10, (int)($query['limit'] ?? 25)));
        
        // Sorting
        $allowedSorts = ['date', 'amount', 'status', 'type'];
        $sort = in_array($query['sort'] ?? '', $allowedSorts) ? $query['sort'] : 'date';
        $direction = in_array(strtoupper($query['dir'] ?? ''), ['ASC', 'DESC']) ? strtoupper($query['dir']) : 'DESC';
        
        // Filters
        $filters = [];
        
        // Status filter
        $validStatuses = ['pending', 'completed', 'failed', 'refunded'];
        if (!empty($query['status']) && in_array($query['status'], $validStatuses)) {
            $filters['status'] = $query['status'];
        }
        
        // Transaction type filter
        $validTypes = ['purchase', 'refund', 'adjustment', 'subscription'];
        if (!empty($query['type']) && in_array($query['type'], $validTypes)) {
            $filters['type'] = $query['type'];
        }
        
        // Date range filter
        if (!empty($query['date_from']) && $this->isValidDate($query['date_from'])) {
            $filters['date_from'] = $query['date_from'];
        }
        if (!empty($query['date_to']) && $this->isValidDate($query['date_to'])) {
            $filters['date_to'] = $query['date_to'];
        }
        
        // Amount range filter
        if (!empty($query['amount_min']) && is_numeric($query['amount_min'])) {
            $filters['amount_min'] = (float)$query['amount_min'];
        }
        if (!empty($query['amount_max']) && is_numeric($query['amount_max'])) {
            $filters['amount_max'] = (float)$query['amount_max'];
        }
        
        // Search query
        if (!empty($query['q'])) {
            $filters['search'] = trim($query['q']);
        }
        
        return [
            'page' => $page,
            'limit' => $limit,
            'sort' => [$sort => $direction],
            'filters' => $filters
        ];
    }
    
    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
```

### Query Parameter Helpers

```php
// Helper class for common query operations
class QueryHelper
{
    public static function getPagination(array $query, int $defaultLimit = 25): array
    {
        return [
            'page' => max(1, (int)($query['page'] ?? 1)),
            'limit' => min(100, max(10, (int)($query['limit'] ?? $defaultLimit)))
        ];
    }
    
    public static function getDateRange(array $query): array
    {
        $from = null;
        $to = null;
        
        if (!empty($query['date_from'])) {
            $from = DateTime::createFromFormat('Y-m-d', $query['date_from']);
            $from = $from ? $from->format('Y-m-d') : null;
        }
        
        if (!empty($query['date_to'])) {
            $to = DateTime::createFromFormat('Y-m-d', $query['date_to']);
            $to = $to ? $to->format('Y-m-d') : null;
        }
        
        return compact('from', 'to');
    }
    
    public static function buildQueryString(array $params, array $exclude = []): string
    {
        $filtered = array_diff_key($params, array_flip($exclude));
        $filtered = array_filter($filtered, fn($v) => $v !== null && $v !== '');
        return http_build_query($filtered);
    }
}
```

## RESTful Resource Routing

Tiny encourages RESTful design patterns with semantic HTTP method usage:

### Complete REST Implementation

```php
// app/controllers/api/billing/accounts.php
class ApiBillingAccounts extends TinyController
{
    public function __construct()
    {
        parent::__construct();
        
        // Ensure API authentication
        if (!$this->isValidApiRequest()) {
            tiny::response()->sendJSON(['error' => 'Unauthorized'], 401);
            exit;
        }
    }
    
    /**
     * GET /api/billing/accounts - List billing accounts
     * GET /api/billing/accounts/{id} - Get specific account
     */
    public function get($request, $response)
    {
        $accountId = $request->path->section;
        
        if ($accountId) {
            // Get specific account
            $account = tiny::model('billing')->getBillingAccount((int)$accountId);
            
            if (!$account) {
                return $response->sendJSON(['error' => 'Account not found'], 404);
            }
            
            // Check permissions
            if (!$this->canAccessAccount($account)) {
                return $response->sendJSON(['error' => 'Access denied'], 403);
            }
            
            return $response->sendJSON([
                'success' => true,
                'data' => $this->formatAccountData($account)
            ]);
        } else {
            // List accounts with pagination
            $params = $this->parseListParams($request->query);
            $result = tiny::model('billing')->getAccountsList($params);
            
            return $response->sendJSON([
                'success' => true,
                'data' => array_map([$this, 'formatAccountData'], $result['accounts']),
                'pagination' => [
                    'page' => $params['page'],
                    'limit' => $params['limit'],
                    'total' => $result['total'],
                    'pages' => ceil($result['total'] / $params['limit'])
                ]
            ]);
        }
    }
    
    /**
     * POST /api/billing/accounts - Create new billing account
     */
    public function post($request, $response)
    {
        if (!$this->hasPermission('billing.accounts.create')) {
            return $response->sendJSON(['error' => 'Insufficient permissions'], 403);
        }
        
        $data = $request->body(true);
        
        // Validate input
        $validation = $this->validateAccountData($data);
        if (!$validation['valid']) {
            return $response->sendJSON([
                'error' => 'Validation failed',
                'details' => $validation['errors']
            ], 400);
        }
        
        try {
            $accountId = tiny::model('billing')->createBillingAccount($data);
            $account = tiny::model('billing')->getBillingAccount($accountId);
            
            return $response->sendJSON([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $this->formatAccountData($account)
            ], 201);
            
        } catch (Exception $e) {
            error_log("Account creation failed: " . $e->getMessage());
            return $response->sendJSON([
                'error' => 'Account creation failed',
                'message' => 'An error occurred while creating the account'
            ], 500);
        }
    }
    
    /**
     * PATCH /api/billing/accounts/{id} - Update billing account
     */
    public function patch($request, $response)
    {
        $accountId = (int)$request->path->section;
        if (!$accountId) {
            return $response->sendJSON(['error' => 'Account ID required'], 400);
        }
        
        $account = tiny::model('billing')->getBillingAccount($accountId);
        if (!$account) {
            return $response->sendJSON(['error' => 'Account not found'], 404);
        }
        
        if (!$this->canModifyAccount($account)) {
            return $response->sendJSON(['error' => 'Access denied'], 403);
        }
        
        $data = $request->body(true);
        
        // Validate partial update data
        $validation = $this->validateAccountData($data, true); // partial = true
        if (!$validation['valid']) {
            return $response->sendJSON([
                'error' => 'Validation failed',
                'details' => $validation['errors']
            ], 400);
        }
        
        try {
            $success = tiny::model('billing')->updateBillingAccount($accountId, $data);
            
            if ($success) {
                $updatedAccount = tiny::model('billing')->getBillingAccount($accountId);
                return $response->sendJSON([
                    'success' => true,
                    'message' => 'Account updated successfully',
                    'data' => $this->formatAccountData($updatedAccount)
                ]);
            } else {
                return $response->sendJSON(['error' => 'Update failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Account update failed: " . $e->getMessage());
            return $response->sendJSON(['error' => 'Update failed'], 500);
        }
    }
    
    /**
     * DELETE /api/billing/accounts/{id} - Deactivate billing account
     */
    public function delete($request, $response)
    {
        $accountId = (int)$request->path->section;
        if (!$accountId) {
            return $response->sendJSON(['error' => 'Account ID required'], 400);
        }
        
        $account = tiny::model('billing')->getBillingAccount($accountId);
        if (!$account) {
            return $response->sendJSON(['error' => 'Account not found'], 404);
        }
        
        if (!$this->canDeleteAccount($account)) {
            return $response->sendJSON(['error' => 'Access denied'], 403);
        }
        
        try {
            // Soft delete - deactivate rather than hard delete
            $success = tiny::model('billing')->deactivateAccount($accountId);
            
            if ($success) {
                return $response->sendJSON([
                    'success' => true,
                    'message' => 'Account deactivated successfully'
                ]);
            } else {
                return $response->sendJSON(['error' => 'Deactivation failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Account deactivation failed: " . $e->getMessage());
            return $response->sendJSON(['error' => 'Deactivation failed'], 500);
        }
    }
    
    // Helper methods
    private function isValidApiRequest(): bool
    {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        return !empty($token) && $this->validateApiToken($token);
    }
    
    private function formatAccountData(object $account): array
    {
        return [
            'id' => $account->billing_account_id,
            'uuid' => $account->billing_account_uuid,
            'name' => $account->name,
            'email' => $account->email,
            'status' => $account->status,
            'credits' => [
                'subscription' => $account->subscription_credits,
                'prepaid' => $account->prepaid_credits,
                'overage' => $account->overage_credits,
                'total' => $account->total_credits
            ],
            'settings' => [
                'allow_overage' => (bool)$account->allow_overage,
                'topup_threshold' => $account->topup_threshold,
                'topup_amount' => $account->topup_amount
            ],
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at
        ];
    }
}
```

## Advanced Response Handling

Tiny provides comprehensive response formatting for different content types and use cases:

### HTML Responses with Views

```php
public function get($request, $response)
{
    // Auto-render view based on controller/method
    $response->render(); // Renders app/views/billing/credits.php
    
    // Render specific view
    $response->render('billing/purchase-success');
    
    // Render with layout
    $response->render('billing/credits', 'layouts/billing');
    
    // Render partial (HTMX fragments)
    if ($request->isHTMX()) {
        $response->render('components/credit-balance');
    } else {
        $response->render(); // Full page
    }
}
```

### JSON API Responses

```php
public function post($request, $response)
{
    try {
        $result = $this->processPayment($request->body(true));
        
        // Success response with data
        return $response->sendJSON([
            'success' => true,
            'message' => 'Payment processed successfully',
            'data' => [
                'transaction_id' => $result['transaction_id'],
                'new_balance' => $result['new_balance'],
                'receipt_url' => $result['receipt_url']
            ]
        ], 201);
        
    } catch (ValidationException $e) {
        // Validation error response
        return $response->sendJSON([
            'success' => false,
            'error' => 'Validation failed',
            'details' => $e->getErrors()
        ], 400);
        
    } catch (PaymentException $e) {
        // Payment processing error
        return $response->sendJSON([
            'success' => false,
            'error' => 'Payment failed',
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ], 402); // Payment Required
        
    } catch (Exception $e) {
        // Generic server error
        error_log("Payment processing error: " . $e->getMessage());
        return $response->sendJSON([
            'success' => false,
            'error' => 'Internal server error',
            'message' => 'An unexpected error occurred'
        ], 500);
    }
}
```

### Redirect Responses

```php
public function post($request, $response)
{
    $result = $this->processForm($request->body(true));
    
    if ($result['success']) {
        // Success redirect with flash message
        tiny::flash('toast')->set([
            'level' => 'success',
            'message' => 'Changes saved successfully'
        ]);
        return $response->redirect('/billing');
    } else {
        // Error redirect back to form
        tiny::flash('toast')->set([
            'level' => 'error',
            'message' => 'Please correct the errors below'
        ]);
        tiny::data()->errors = $result['errors'];
        return $response->back(); // Redirect to previous page
    }
}
```

### File Download Responses

```php
public function get($request, $response)
{
    $invoiceId = $request->path->section;
    
    // Generate PDF invoice
    $pdf = tiny::model('billing')->generateInvoicePDF($invoiceId);
    
    if (!$pdf) {
        return $response->sendJSON(['error' => 'Invoice not found'], 404);
    }
    
    // Set headers for file download
    $response->setHeaders([
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="invoice-' . $invoiceId . '.pdf"',
        'Content-Length' => strlen($pdf),
        'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ]);
    
    echo $pdf;
    exit;
}
```

### Streaming Responses (SSE)

```php
public function get($request, $response)
{
    if ($request->headers['Accept'] === 'text/event-stream') {
        // Server-Sent Events for real-time updates
        $response->setHeaders([
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive'
        ]);
        
        // Stream billing updates
        while (true) {
            $updates = tiny::model('billing')->getRealtimeUpdates(
                tiny::user()->billing_account_id
            );
            
            if (!empty($updates)) {
                foreach ($updates as $update) {
                    echo "data: " . json_encode($update) . "\n\n";
                    flush();
                }
            }
            
            sleep(2); // Check every 2 seconds
        }
    } else {
        // Regular page response
        $response->render();
    }
}
```

### Response Status Codes

```php
// HTTP status code constants for clarity
class HttpStatus
{
    const OK = 200;
    const CREATED = 201;
    const ACCEPTED = 202;
    const NO_CONTENT = 204;
    const BAD_REQUEST = 400;
    const UNAUTHORIZED = 401;
    const PAYMENT_REQUIRED = 402;
    const FORBIDDEN = 403;
    const NOT_FOUND = 404;
    const METHOD_NOT_ALLOWED = 405;
    const CONFLICT = 409;
    const UNPROCESSABLE_ENTITY = 422;
    const TOO_MANY_REQUESTS = 429;
    const INTERNAL_SERVER_ERROR = 500;
}

public function patch($request, $response)
{
    $result = $this->updateResource($request->body(true));
    
    switch ($result['status']) {
        case 'success':
            return $response->sendJSON($result['data'], HttpStatus::OK);
        case 'not_found':
            return $response->sendJSON(['error' => 'Resource not found'], HttpStatus::NOT_FOUND);
        case 'validation_error':
            return $response->sendJSON(['errors' => $result['errors']], HttpStatus::UNPROCESSABLE_ENTITY);
        case 'conflict':
            return $response->sendJSON(['error' => 'Resource conflict'], HttpStatus::CONFLICT);
        default:
            return $response->sendJSON(['error' => 'Update failed'], HttpStatus::INTERNAL_SERVER_ERROR);
    }
}
```

## Middleware Integration

Middleware in Tiny provides powerful request/response filtering with route-specific application:

### Middleware Configuration

```php
<?php
// app/middleware.php - Route-specific middleware mapping

tiny::middleware('version'); // Global middleware
tiny::middleware('auth');    // Global authentication

// Route-specific middleware
return [
    // Authentication required
    'auth' => [
        '/account/*',
        '/billing/*',
        '/profile',
        '/usage',
        '/api/user/*'
    ],
    
    // Admin access only
    'admin' => [
        '/admin/*',
        '/api/admin/*'
    ],
    
    // Billing account ownership required
    'billing_owner' => [
        '/billing/subscription',
        '/billing/payment-methods',
        '/billing/credits',
        '/api/billing/purchase'
    ],
    
    // Rate limiting for API endpoints
    'rate_limit' => [
        '/api/*'
    ],
    
    // CORS for webhooks
    'cors' => [
        '/webhooks/*'
    ]
];
```

### Custom Middleware Example

```php
<?php
// app/middleware/billing_owner.php

class BillingOwnerMiddleware
{
    public function handle()
    {
        // Skip if not authenticated
        if (!tiny::user()->authenticated) {
            return true; // Let auth middleware handle it
        }
        
        // Check if user has a billing account
        $billingAccount = tiny::model('billing')->getBillingAccountByUser(
            tiny::user()->user_id
        );
        
        if (!$billingAccount) {
            // Redirect to billing setup
            tiny::response()->redirect('/billing/setup');
            exit;
        }
        
        // Check if user is the owner of the billing account
        $isOwner = $billingAccount->owner_account_id === tiny::user()->user_id;
        
        if (!$isOwner) {
            // Set non-owner flag and redirect to limited view
            tiny::user()->billing_account_is_owner = false;
            tiny::flash('info')->set([
                'message' => 'You have limited access to billing features. Contact your account administrator for full access.'
            ]);
            tiny::response()->redirect('/billing/view-only');
            exit;
        }
        
        // Set owner flag for use in controllers
        tiny::user()->billing_account_is_owner = true;
        tiny::user()->billing_account = $billingAccount;
        
        return true;
    }
}
```

### Advanced Middleware Patterns

```php
<?php
// app/middleware/rate_limit.php

class RateLimitMiddleware
{
    private array $limits = [
        '/api/billing/purchase' => ['requests' => 5, 'window' => 60],    // 5 per minute
        '/api/user/profile' => ['requests' => 20, 'window' => 60],       // 20 per minute
        '/api/*' => ['requests' => 100, 'window' => 60]                  // 100 per minute default
    ];
    
    public function handle()
    {
        $route = tiny::router()->route;
        $userId = tiny::user()->user_id ?? null;
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Determine rate limit for this route
        $limit = $this->getLimitForRoute($route);
        
        if (!$limit) {
            return true; // No limit configured
        }
        
        // Create unique key for this user/IP and route
        $key = "rate_limit:" . ($userId ?? $ip) . ":" . md5($route);
        
        // Get current count
        $current = (int)tiny::cache()->get($key);
        
        if ($current >= $limit['requests']) {
            // Rate limit exceeded
            $resetTime = tiny::cache()->getTTL($key);
            
            tiny::response()->setHeaders([
                'X-RateLimit-Limit' => $limit['requests'],
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => time() + $resetTime,
                'Retry-After' => $resetTime
            ]);
            
            tiny::response()->sendJSON([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Try again in {$resetTime} seconds."
            ], 429);
            exit;
        }
        
        // Increment counter
        if ($current === 0) {
            // First request in window
            tiny::cache()->set($key, 1, $limit['window']);
        } else {
            // Increment existing counter
            tiny::cache()->increment($key);
        }
        
        // Set rate limit headers
        tiny::response()->setHeaders([
            'X-RateLimit-Limit' => $limit['requests'],
            'X-RateLimit-Remaining' => max(0, $limit['requests'] - $current - 1),
            'X-RateLimit-Reset' => time() + tiny::cache()->getTTL($key)
        ]);
        
        return true;
    }
    
    private function getLimitForRoute(string $route): ?array
    {
        foreach ($this->limits as $pattern => $limit) {
            if ($this->matchesPattern($route, $pattern)) {
                return $limit;
            }
        }
        return null;
    }
    
    private function matchesPattern(string $route, string $pattern): bool
    {
        if ($pattern === $route) {
            return true;
        }
        
        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');
            return str_starts_with($route, $prefix);
        }
        
        return false;
    }
}
```

## Advanced Error Handling

Tiny provides comprehensive error handling with custom error pages and API error responses:

### Custom Error Controllers

```php
// app/controllers/404.php - Custom 404 handler
class NotFound extends TinyController
{
    public function get($request, $response)
    {
        // Log 404 for monitoring
        error_log(sprintf(
            "404 Not Found: %s %s - User: %s - Referrer: %s",
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            tiny::user()->user_id ?? 'anonymous',
            $_SERVER['HTTP_REFERER'] ?? 'direct'
        ));
        
        // Different responses for different request types
        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            // API 404 response
            return $response->sendJSON([
                'error' => 'Endpoint not found',
                'message' => 'The requested API endpoint does not exist',
                'code' => 'ENDPOINT_NOT_FOUND'
            ], 404);
        }
        
        if ($request->isHTMX()) {
            // HTMX partial response
            return $response->render('components/not-found-partial');
        }
        
        // Regular HTML 404 page
        tiny::data()->merge([
            'requested_url' => $_SERVER['REQUEST_URI'],
            'suggestions' => $this->getSuggestions($_SERVER['REQUEST_URI'])
        ]);
        
        $response->render('errors/404');
    }
    
    private function getSuggestions(string $url): array
    {
        // Simple suggestion logic
        $suggestions = [];
        
        if (str_contains($url, 'billing')) {
            $suggestions[] = ['url' => '/billing', 'title' => 'Billing Dashboard'];
            $suggestions[] = ['url' => '/billing/credits', 'title' => 'Purchase Credits'];
        }
        
        if (str_contains($url, 'account')) {
            $suggestions[] = ['url' => '/account', 'title' => 'Account Settings'];
            $suggestions[] = ['url' => '/profile', 'title' => 'User Profile'];
        }
        
        // Always suggest home
        $suggestions[] = ['url' => '/', 'title' => 'Home'];
        
        return $suggestions;
    }
}
```

```php
// app/controllers/500.php - Server error handler
class ServerError extends TinyController
{
    public function get($request, $response)
    {
        // Get error details if available
        $error = tiny::data()->error ?? null;
        $errorId = uniqid('ERR_');
        
        // Log detailed error information
        if ($error) {
            error_log(sprintf(
                "[%s] 500 Server Error: %s in %s:%d\nStack trace:\n%s\nUser: %s\nRequest: %s",
                $errorId,
                $error['message'] ?? 'Unknown error',
                $error['file'] ?? 'unknown',
                $error['line'] ?? 0,
                $error['trace'] ?? 'No trace available',
                tiny::user()->user_id ?? 'anonymous',
                json_encode([
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'uri' => $_SERVER['REQUEST_URI'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ip' => $_SERVER['REMOTE_ADDR']
                ])
            ));
        }
        
        // Send error notification to admin for critical paths
        if ($this->isCriticalPath($_SERVER['REQUEST_URI'])) {
            $this->notifyAdmin($errorId, $error);
        }
        
        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            // API error response
            return $response->sendJSON([
                'error' => 'Internal server error',
                'message' => 'An unexpected error occurred',
                'error_id' => $errorId
            ], 500);
        }
        
        // HTML error page
        tiny::data()->merge([
            'error_id' => $errorId,
            'show_details' => tiny::user()->is_admin ?? false
        ]);
        
        $response->render('errors/500');
    }
    
    private function isCriticalPath(string $uri): bool
    {
        $criticalPaths = [
            '/billing/',
            '/webhooks/',
            '/api/billing/',
            '/api/payments/'
        ];
        
        foreach ($criticalPaths as $path) {
            if (str_starts_with($uri, $path)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function notifyAdmin(string $errorId, ?array $error): void
    {
        try {
            tiny::helper('email')->sendAdminAlert(
                "Critical Error: {$errorId}",
                "A critical error occurred in the billing system",
                $error ?? []
            );
        } catch (Exception $e) {
            error_log("Failed to send admin alert: " . $e->getMessage());
        }
    }
}
```

### Global Error Handler

```php
// app/common.php - Global error and exception handling

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($exception) {
    // Don't show detailed errors in production
    $showDetails = $_SERVER['ENV'] === 'local' || (tiny::user()->is_admin ?? false);
    
    tiny::data()->error = [
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];
    
    // Route to appropriate error handler
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Internal server error',
            'message' => $showDetails ? $exception->getMessage() : 'An unexpected error occurred'
        ]);
    } else {
        // Include and execute 500 controller
        require_once 'app/controllers/500.php';
        $controller = new ServerError();
        $controller->get(null, tiny::response());
    }
    
    exit;
});
```

## Production Routing Best Practices

### 1. RESTful Design Patterns

```php
// Resource-based routing with consistent patterns

// Collections and Items
GET    /api/billing/transactions          // List transactions
POST   /api/billing/transactions          // Create transaction
GET    /api/billing/transactions/{id}     // Get specific transaction
PATCH  /api/billing/transactions/{id}     // Update transaction
DELETE /api/billing/transactions/{id}     // Delete transaction

// Nested Resources
GET    /api/accounts/{id}/billing         // Get account billing info
POST   /api/accounts/{id}/billing/credits // Purchase credits for account
GET    /api/accounts/{id}/transactions    // Get account transactions

// Actions on Resources
POST   /api/billing/transactions/{id}/refund    // Refund a transaction
POST   /api/billing/accounts/{id}/suspend       // Suspend an account
POST   /api/billing/accounts/{id}/activate      // Activate an account
```

### 2. Controller Organization Strategy

```php
// Organize by feature, not by HTTP method
app/controllers/
├── billing/
│   ├── index.php           // Billing dashboard
│   ├── credits.php         // Credit management
│   ├── subscription.php    // Subscription management
│   ├── invoices.php        // Invoice handling
│   └── settings.php        // Billing settings
├── account/
│   ├── index.php           // Account overview
│   ├── profile.php         // Profile management
│   ├── security.php        // Security settings
│   └── preferences.php     // User preferences
└── api/
    ├── v1/
    │   ├── billing/
    │   │   ├── accounts.php
    │   │   ├── transactions.php
    │   │   └── credits.php
    │   └── users/
    │       ├── profile.php
    │       └── preferences.php
    └── webhooks/
        ├── stripe.php
        ├── mailgun.php
        └── analytics.php
```

### 3. Security-First Routing

```php
// Security considerations in every controller
class BillingCredits extends TinyController
{
    public function __construct()
    {
        parent::__construct();
        
        // 1. Authentication check
        if (!tiny::user()->authenticated) {
            tiny::response()->redirect('/login');
            exit;
        }
        
        // 2. Rate limiting for sensitive operations
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->enforceRateLimit('credit_purchase', 5, 300); // 5 per 5 minutes
        }
        
        // 3. HTTPS enforcement for billing
        if (!$this->isHTTPS() && $_SERVER['ENV'] !== 'local') {
            $httpsUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            tiny::response()->redirect($httpsUrl);
            exit;
        }
    }
    
    public function post($request, $response)
    {
        // 4. CSRF protection
        if (!$request->isValidCSRF()) {
            return $this->securityViolation($response, 'Invalid CSRF token');
        }
        
        // 5. Input validation and sanitization
        $data = $this->validateAndSanitizeInput($request->body(true));
        if (!$data) {
            return $this->validationError($response);
        }
        
        // 6. Authorization (ownership) check
        if (!$this->canAccessBillingAccount($data['billing_account_id'])) {
            return $this->accessDenied($response);
        }
        
        // 7. Business logic with error handling
        try {
            $result = $this->processCreditPurchase($data);
            return $this->successResponse($response, $result);
        } catch (Exception $e) {
            return $this->handleError($response, $e);
        }
    }
}
```

### 4. Performance Optimization

```php
// Caching strategies for route performance
class BillingTransactions extends TinyController
{
    public function get($request, $response)
    {
        // 1. Route-based caching
        $cacheKey = $this->getCacheKey($request);
        $cached = tiny::cache()->get($cacheKey);
        
        if ($cached && !$this->shouldBypassCache($request)) {
            return $response->sendJSON($cached);
        }
        
        // 2. Database query optimization
        $params = $this->parseAndValidateParams($request->query);
        $transactions = tiny::model('billing')->getTransactionsOptimized(
            tiny::user()->billing_account_id,
            $params
        );
        
        // 3. Response caching with appropriate TTL
        $responseData = $this->formatTransactions($transactions);
        $ttl = $this->getCacheTTL($params);
        tiny::cache()->set($cacheKey, $responseData, $ttl);
        
        return $response->sendJSON($responseData);
    }
    
    private function getCacheKey($request): string
    {
        $params = $request->query;
        ksort($params); // Ensure consistent key generation
        
        return sprintf(
            'billing_transactions_%d_%s',
            tiny::user()->billing_account_id,
            md5(serialize($params))
        );
    }
    
    private function getCacheTTL(array $params): int
    {
        // Real-time data (current month) - shorter cache
        if ($this->isCurrentMonth($params)) {
            return 60; // 1 minute
        }
        
        // Historical data - longer cache
        return 3600; // 1 hour
    }
}
```

### 5. API Versioning Strategy

```php
// API versioning through URL structure
app/controllers/api/
├── v1/
│   ├── billing/
│   │   └── accounts.php    // /api/v1/billing/accounts
│   └── users/
│       └── profile.php     // /api/v1/users/profile
└── v2/
    ├── billing/
    │   └── accounts.php    // /api/v2/billing/accounts
    └── users/
        └── profile.php     // /api/v2/users/profile

// Version-aware base controller
abstract class ApiController extends TinyController
{
    protected string $version;
    
    public function __construct()
    {
        parent::__construct();
        
        // Extract version from URL
        $this->version = $this->extractVersion();
        
        // Set version-specific headers
        tiny::response()->setHeaders([
            'API-Version' => $this->version,
            'Content-Type' => 'application/json'
        ]);
    }
    
    private function extractVersion(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (preg_match('/\/api\/(v\d+)\//i', $path, $matches)) {
            return $matches[1];
        }
        return 'v1'; // Default version
    }
}
```

### 6. Monitoring and Analytics

```php
// Request monitoring and metrics collection
class RequestMetrics
{
    public static function track()
    {
        $start = microtime(true);
        
        register_shutdown_function(function() use ($start) {
            $duration = microtime(true) - $start;
            $memory = memory_get_peak_usage(true);
            
            // Log performance metrics
            error_log(sprintf(
                "[METRICS] %s %s - Duration: %.3fs, Memory: %s, User: %s",
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI'],
                $duration,
                self::formatBytes($memory),
                tiny::user()->user_id ?? 'anonymous'
            ));
            
            // Send to monitoring service
            if ($duration > 1.0) { // Slow request
                self::alertSlowRequest($duration);
            }
        });
    }
    
    private static function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}

// Enable metrics in common.php
RequestMetrics::track();
```

---

## Next Steps in Routing Mastery

1. **Study [Controllers](controllers.md)**: Learn request handling and business logic coordination
2. **Master [Middleware](middleware.md)**: Implement authentication, authorization, and cross-cutting concerns
3. **Explore [Database](database.md)**: Optimize data access patterns for your routes
4. **Build [Views](views.md)**: Create dynamic, responsive user interfaces

Each section provides deeper insights into building production-ready applications with Tiny's routing system.
