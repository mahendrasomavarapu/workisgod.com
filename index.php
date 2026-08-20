<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$guest = current_user();
if ($guest) {
    redirect(user_status($guest) === 'pending' ? '/waiting.php' : '/editor.php');
}

if (isset($_GET['deleted'])) {
    flash_set('ok', 'Your rooms are closed. You may return whenever you wish.');
}

render_header(SITE_NAME, ['body' => 'page-home']);
?>
<main id="main" class="wrap hero-wrap">
    <section class="hero">
        <p class="eyebrow">A private table. A public page.</p>
        <h1>Your work is received<br>with full honors.</h1>
        <p class="lede">Sign in with any email. Paste notes, a URL, or a PDF. We dress the page. You keep a quiet, lasting link.</p>
        <form class="hero-form" method="post" action="/login.php">
            <?= csrf_field() ?>
            <?= honeypot_field() ?>
            <input type="hidden" name="action" value="send">
            <label class="sr-only" for="email">Email</label>
            <input id="email" name="email" type="email" required maxlength="180" placeholder="you@gmail.com" autocomplete="email">
            <?= captcha_widget() ?>
            <button type="submit">Send my private code</button>
        </form>
        <p class="fine">A six-digit code arrives in your inbox. No password is kept. You are never treated as a ticket number.</p>
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
            <p>Your resume lives at a clean link, like <code>/resumes/your-name</code>.</p>
        </li>
    </ol>

    <section class="sample">
        <div class="sample-copy">
            <h2>A theme, not a template maze.</h2>
            <p>Write the way you already write. We turn that text into a readable page. Edit anytime. The URL stays the same.</p>
            <a class="text-link" href="/resumes/demo">See a sample resume →</a>
        </div>
        <div class="sample-frame">
            <?= render_resume_html(parse_resume(sample_resume_text()), 'classic') ?>
        </div>
    </section>
</main>
<?php
render_footer();
