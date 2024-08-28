# Tiny: PHP Framework

Tiny is a lightweight PHP framework designed to provide a simple and efficient way to build web applications with minimal setup and configuration. It was developed by [Ran Aroussi](https://x.com/aroussi) for internal projects and offers a range of features to streamline development while maintaining flexibility.

## Features

- PHP 8.3 support
- Routing system
- MVC architecture
- Database abstraction
- Caching with Memcached
- Component-based views
- Middleware support
- Environment-based configuration
- Job/task Scheduler
- Utility functions for everyday tasks
- Migration system
- Deployment using `git push`

## Getting Started

1. Clone the repository or download the Tiny framework files.

```php
$ git clone https://github.com/ranaroussi/tiny.git
```

3. From inside the project directory, execute the following command to create the project:

```bash
$ php tiny/cli.php create-project
```

4. Install dependencies:

```bash
$ composer install
```

The resulting directory structure should look like this:

```
/your-project
├── app
│   ├── controllers
│   ├── models
│   ├── views
│   │   ├── components
│   │   └── layouts
│   └── middleware
├── html (public directory)
│   └── index.php
├── migrations
├── tiny
├── vendor
├── .env.example
├── composer.json
└── env.php
```

5. Edit the `.env.local` file to match your environment (rename it to `.env.prod` for production, or use environment variables).

6. Set up your web server to point to the `html` directory as the document root.

## Configuration

Tiny uses environment variables for configuration. You can set these in your `.env.*` files or directly in your server environment.

Key configuration options:

- `DB_TYPE`: Database type (mysql, postgresql, sqlite)
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`: Database connection details
- `MEMCACHED_HOST`, `MEMCACHED_PORT`: Memcached connection details
- `AUTOLOAD_HELPERS`: Comma-separated list of helpers to autoload

## Routing

Tiny uses a simple routing system based on the URL structure. Controllers are automatically mapped based on the URL path.

For example:

- `/` maps to `app/controllers/home.php`
- `/users` maps to `app/controllers/users.php`
- `/users/profile` maps to `app/controllers/users/profile.php`

## Controllers

Controllers handle the logic for each route. They should be placed in the `app/controllers` directory.

Example controller:

```php
<?php

class Users extends TinyController
{

    public function get($request, $response)
    {
        // Handle GET request
        $response->render('users/index');
    }

    public function post($request, $response)
    {
        // Handle POST request
    }
}
```

## Views

Views are PHP files that handle the presentation layer. They should be placed in the `app/views` directory.

Tiny supports components and layouts for reusable view elements:

```php
<?php
Component::render('header', ['title' => 'Home']);
?>

<h1>Hello, world</h1>
<p>This is the defalut home page</p>

<?php
Component::render('footer');
?>
```

## Models

Models handle data and business logic. They should be placed in the `app/models` directory.

Example model:

```php
<?php

class UserModel extends TinyModel
{
    public function getUsers()
    {
        return tiny::db()->query("SELECT * FROM users");
    }
}
```

## Middleware

Middleware can be used to perform actions before or after request processing. Place middleware files in the `app/middleware` directory.


```
$ ls -la /app/middleware
$ 00-auth.php   01-some-other-file.php
```

## Helpers

Tiny provides various helper functions for common tasks. You can autoload helpers by listing them in the `AUTOLOAD_HELPERS` environment variable.

## Database

Tiny supports MySQL, PostgreSQL, and SQLite databases. Configure your database connection in the `.env.*` file.

To query the database:

```php
$results = tiny::db()->getQuery("SELECT * FROM users WHERE id = 1");
```

You can also use Tiny's utility function to build queries using placeholders:

```php
$query = tiny::query("SELECT * FROM users WHERE id = ? AND is_deleted = ?", [$userId, false]);
$results = tiny::db()->getQuery($query);
```

## Caching

Tiny uses Memcached for caching. To use the cache:

```php
$value = tiny::cache()->get('key');
tiny::cache()->set('key', $value, 3600); // Cache for 1 hour
```


## Sharing variables across modules

If you need to share variables across modules, you can use the `tiny::data()` method:

```php
// controller
tiny::data()->site_name = 'My Site';

// or
tiny::set('site_name', 'My Site');
```

```php
// view
<h1><?php echo tiny::data()->site_name; ?></h1>

// or
<h1><?php echo tiny::get('site_name'); ?></h1>
```


## Deployment

It is recommended that only the `/html` directory be exposed to the world and that `/tiny`, `/app`, and `/vendor` be kept in the parent directory.


#### Example directory structure

```
/home/webapp/html (exposed to public)
/home/webapp/tiny
/home/webapp/app
/home/webapp/vendor
/home/webapp/.env.prod
/home/webapp/composer.json
/home/webapp/env.php
```

For information on how to deploy using `git push`, see [GIT-DEPLOY.md](GIT-DEPLOY.md).

---

# Migrations

Tiny includes a simple migration system to help manage database schema changes. The migration system uses a SQLite database to track which migrations have been run.

### Creating a Migration

To create a new migration, use the following command:

```bash
php tiny/cli.php migration create migration_name
```

This will create a new migration file in the `migrations` directory with a timestamp prefix.

### Running Migrations

To run all pending migrations:

```bash
php tiny/cli.php migration up
```

This command will execute all migrations that haven't been run yet.

### Rolling Back Migrations

To roll back the last batch of migrations:

```bash
php tiny/cli.php migration down
```

This command will revert the most recent batch of migrations.

### Removing a Migration

To remove a migration file that hasn't been run yet:

```bash
php tiny/cli.php migration remove migration_name
```

This command will remove the specified migration file if it hasn't been applied to the database.

### Migration File Structure

Each migration file should contain an `up` method for applying the migration and a `down` method for reverting it. Here's an example:

```php
<?php

class CreateUsersTable
{
    private PDO $db;

    public function __construct()
    {
        $this->db = tiny::db()->getPdo();
    }

    public function up(): void
    {
        $this->db->execute("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function down(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS users");
    }
}
```

The `$this->db` parameter is an instance of the database connection, allowing you to execute SQL statements or use the query builder methods.

### Best Practices

1. Keep migrations small and focused on a single change.
2. Use descriptive names for your migrations (e.g., `create_users_table`, `add_email_to_users`).
3. Always provide a `down` method that reverts the changes made in the `up` method.
4. Test your migrations thoroughly, especially the `down` method, before applying them to production.

---

# License

Tiny PHP Framework is distributed "As is" under the Apache 2.0 License.

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
