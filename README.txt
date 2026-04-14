GenzLAB setup:
1. Copy `.env.example` to `.env` and set your MySQL credentials.
2. Create the database named in `DB_NAME`.
3. Import `migrations/001_schema.sql`.
4. If you imported the database before Phase 2, also import `migrations/002_phase2_sandbox_user.sql`.
5. Point your web server document root at `sqlab/public` or set `APP_BASE_PATH`.

Python/Java runner setup (Judge0 with Docker):
1. Install Docker Desktop for Windows:
	- Download from https://www.docker.com/products/docker-desktop/
	- Enable WSL2 during installation.
	- Restart Windows if prompted.
2. Open PowerShell and verify Docker:
	- `docker --version`
	- `docker compose version`
3. Pull and run Judge0 CE quickly:
	- `docker volume create judge0-db`
	- `docker volume create judge0-redis`
	- `docker run -d --name judge0-db -e POSTGRES_PASSWORD=postgres -e POSTGRES_USER=postgres -e POSTGRES_DB=judge0 -v judge0-db:/var/lib/postgresql/data postgres:13`
	- `docker run -d --name judge0-redis redis:7-alpine`
	- `docker run -d --name judge0 -p 2358:2358 --link judge0-db:db --link judge0-redis:redis -e REDIS_HOST=redis -e POSTGRES_HOST=db -e POSTGRES_PORT=5432 -e POSTGRES_DB=judge0 -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres judge0/judge0:latest`
4. Verify Judge0 is up:
	- Open `http://localhost:2358/languages` in browser.
	- You should see JSON with language IDs.
5. Set env values in `.env`:
	- `JUDGE0_URL=http://127.0.0.1:2358`
	- `JUDGE0_WAIT=true`
	- Keep the default language IDs unless your runner returns different ones.
6. Restart Apache/PHP (XAMPP) after updating `.env`.

Recommended DB migration for subjects:
1. Import `migrations/004_subjects.sql` on existing DBs.
2. This enables subject-backed problem filtering and smooth Python/Java rollout.

Seed starter Python/Java questions:
1. Import `migrations/005_seed_python_java_problems.sql`.
2. This adds initial coding questions for Python and Java subjects (idempotent).

Quizathon setup:
1. Import `migrations/006_quizathon.sql`.
2. This creates quiz tables and seeds Quiz 1, Quiz 2, and Endterm samples for SQL/Python/Java.

SQL Practice sandbox storage notes:
1. SQL Practice uses per-user temporary databases named like `sqlab_practice_*`.
2. Sandboxes are cleaned automatically on logout.
3. Stale sandboxes are pruned automatically (default: 24 hours, configurable via `PRACTICE_SANDBOX_MAX_AGE_HOURS`).
