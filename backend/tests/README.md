# Test Database

Feature tests require an isolated PostgreSQL database configured through these environment variables:

```sh
DB_TEST_HOST=127.0.0.1 \
DB_TEST_PORT=5432 \
DB_TEST_DATABASE=cooperative_system_test \
DB_TEST_USERNAME=cooperative_test \
DB_TEST_PASSWORD='provided-outside-the-repository' \
php artisan test
```

The test bootstrap explicitly selects PostgreSQL and fails before Laravel boots if any required variable is missing. It never falls back to SQLite or the development database. Create the database and role separately with PostgreSQL administration tooling.
