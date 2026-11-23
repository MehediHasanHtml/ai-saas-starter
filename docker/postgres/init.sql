-- Enable pgvector and create the test database used by `php artisan test`.
CREATE EXTENSION IF NOT EXISTS vector;
CREATE DATABASE testing;
\c testing
CREATE EXTENSION IF NOT EXISTS vector;
