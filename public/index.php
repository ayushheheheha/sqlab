<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = Auth::getCurrentUser();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GenzLAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/style.css')) ?>">
</head>
<body>
    <button class="theme-toggle" data-theme-toggle type="button" aria-label="Toggle theme">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
        </svg>
    </button>
    <header class="landing-nav-wrap">
        <nav class="landing-nav public-main">
            <a class="landing-logo" href="<?= e(app_url()) ?>">GenzLAB</a>
            <div class="landing-nav-links">
                <a href="#features">Features</a>
                <a href="#tracks">Tracks</a>
                <a href="#why-genzlab">Why GenzLAB</a>
            </div>
            <a class="btn-primary landing-nav-cta" href="<?= e($user ? app_url('dashboard.php') : app_url('register.php')) ?>"><?= $user ? 'Open Dashboard' : 'Create Free Account' ?></a>
        </nav>
    </header>

    <main class="public-main landing-main">
        <section class="landing-hero" id="features">
            <div class="card landing-copy">
                <span class="landing-kicker">Student Upskilling Platform</span>
                <h1>Build skills for <span class="landing-hero-accent">real careers</span> with hands-on daily practice.</h1>
                <p class="page-subtitle">GenzLAB helps students upskill with practical learning tracks in SQL, Python, Java, and more. Practice with instant feedback, track growth, and stay consistent.</p>
                <div class="landing-cta-row">
                    <a class="btn-primary" href="<?= e($user ? app_url('dashboard.php') : app_url('register.php')) ?>"><?= $user ? 'Continue Learning' : 'Start Upskilling' ?></a>
                    <a class="btn-ghost" href="<?= e($user ? app_url('problems.php') : app_url('login.php')) ?>"><?= $user ? 'Open Practice' : 'Log In' ?></a>
                </div>
                <p class="landing-caption">Trusted by students preparing for internships, placements, and technical interviews.</p>
            </div>
            <aside class="card landing-terminal-card" aria-label="Live demo terminal">
                <div class="terminal-head">
                    <span></span><span></span><span></span>
                    <p>genzlab-demo.sql</p>
                </div>
                <div class="terminal-body">
                    <p class="terminal-prompt">student@genzlab:~$</p>
                    <p class="terminal-code">
                        <span class="terminal-typed">SELECT skill, level FROM upskilling_tracks WHERE career_ready = 1 ORDER BY level DESC;</span>
                    </p>
                    <p class="terminal-result">→ 3 active skills loaded • feedback ready</p>
                </div>
                <div class="terminal-pills">
                    <span>Instant feedback</span>
                    <span>XP tracking</span>
                    <span>Daily streaks</span>
                </div>
            </aside>
        </section>

        <section class="landing-split" aria-label="Practice and progress cards">
            <article class="landing-split-card">
                <p class="landing-split-index">01</p>
                <h2>Practice</h2>
                <p>Work through focused coding challenges designed for actual skill growth, not passive watching.</p>
                <a href="#tracks">Learn more →</a>
            </article>
            <article class="landing-split-card">
                <p class="landing-split-index">02</p>
                <h2>Progress</h2>
                <p>Measure consistency with XP, streaks, and challenge completion that keeps your momentum visible.</p>
                <a href="#why-genzlab">Learn more →</a>
            </article>
        </section>

        <section class="landing-tracks" id="tracks">
            <p class="landing-section-label">Active Tracks</p>
            <div class="landing-track-grid">
                <article class="landing-track-card">
                    <div class="landing-track-head">
                        <h3>SQL</h3>
                        <span class="landing-track-badge is-active">Active track</span>
                    </div>
                    <p>SELECT, JOINs, Aggregations, Window Functions, Subqueries, Query Optimization.</p>
                </article>
                <article class="landing-track-card">
                    <div class="landing-track-head">
                        <h3>Python</h3>
                        <span class="landing-track-badge is-next">Coming next</span>
                    </div>
                    <p>Core syntax, data structures, scripting, data workflows, interview-style coding drills.</p>
                </article>
                <article class="landing-track-card">
                    <div class="landing-track-head">
                        <h3>Java</h3>
                        <span class="landing-track-badge is-roadmap">In roadmap</span>
                    </div>
                    <p>OOP, collections, streams, backend fundamentals, and production-ready coding patterns.</p>
                </article>
            </div>
        </section>

        <section class="landing-split" id="why-genzlab" aria-label="Why GenzLAB information">
            <article class="landing-split-card landing-info-card">
                <div class="landing-info-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16M4 12h16M4 18h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </div>
                <h2>Why GenzLAB</h2>
                <p>Most learning tools stop at explanations. GenzLAB is built around deliberate practice so students can improve through feedback loops, visible progress, and consistent challenge exposure.</p>
            </article>
            <article class="landing-split-card landing-info-card">
                <div class="landing-info-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 4v16M4 12h16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                </div>
                <h2>More tracks, one platform</h2>
                <p>Start where you are, then grow across domains inside one warm, consistent learning workspace. SQL is live now, Python is next, and Java plus more tracks are being rolled out.</p>
            </article>
        </section>

        <section class="landing-footer-cta">
            <h2>Your upskilling journey starts now.</h2>
            <p>Learn with clarity, practice with intent, and build confidence for interviews and real-world roles.</p>
            <a class="landing-footer-button" href="<?= e($user ? app_url('dashboard.php') : app_url('register.php')) ?>"><?= $user ? 'Open Dashboard' : 'Create Free Account' ?></a>
            <div class="landing-footer-stats">
                <div>
                    <strong>3+</strong>
                    <span>Tracks</span>
                </div>
                <div>
                    <strong>100+</strong>
                    <span>Challenges</span>
                </div>
                <div>
                    <strong>0₹</strong>
                    <span>To start</span>
                </div>
            </div>
        </section>
    </main>
    <script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
