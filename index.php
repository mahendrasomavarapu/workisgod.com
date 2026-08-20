<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('/editor.php');
}

render_header(SITE_NAME, ['body' => 'page-home']);
?>
<main class="wrap hero-wrap">
    <section class="hero">
        <p class="eyebrow">Any email. One-time code. No password.</p>
        <h1>Put your work on a page<br>worth sharing.</h1>
        <p class="lede">Sign in with Gmail or any other email, paste a text resume, pick a Gen Z or millennial theme, and keep a stable public URL.</p>
        <form class="hero-form" method="post" action="/login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send">
            <label class="sr-only" for="email">Email</label>
            <input id="email" name="email" type="email" required placeholder="you@gmail.com" autocomplete="email">
            <button type="submit">Send login code</button>
        </form>
        <p class="fine">We’ll email a 6-digit code. Nothing else is stored as a password.</p>
    </section>

    <ol class="steps">
        <li>
            <span>01</span>
            <h2>Sign in</h2>
            <p>Gmail, Outlook, or any inbox. Enter the OTP and you’re in.</p>
        </li>
        <li>
            <span>02</span>
            <h2>Paste or upload</h2>
            <p>Drop in plain text, a LinkedIn URL, any public page, or a PDF. We capture the profile and write the resume.</p>
        </li>
        <li>
            <span>03</span>
            <h2>Share a URL</h2>
            <p>Your resume lives at a clean link, like <code>/r/your-name</code>.</p>
        </li>
    </ol>

    <section class="sample">
        <div class="sample-copy">
            <h2>A theme, not a template maze.</h2>
            <p>Write the way you already write. We turn that text into a readable page. Edit anytime. The URL stays the same.</p>
            <a class="text-link" href="/r/demo">See a sample resume →</a>
        </div>
        <div class="sample-frame">
            <?= render_resume_html(parse_resume(sample_resume_text()), 'classic') ?>
        </div>
    </section>
</main>
<?php
render_footer();
