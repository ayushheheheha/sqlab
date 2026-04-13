SQLab setup:
1. Copy `.env.example` to `.env` and set your MySQL credentials.
2. Create the database named in `DB_NAME`.
3. Import `migrations/001_schema.sql`.
4. If you imported the database before Phase 2, also import `migrations/002_phase2_sandbox_user.sql`.
5. Point your web server document root at `sqlab/public` or set `APP_BASE_PATH`.
