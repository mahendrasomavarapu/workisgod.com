<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

render_header('About', [
    'body' => 'page-about',
    'description' => 'Work is God is a small site for writing a text resume, theming it, and sharing a stable public URL. Sign in with any email using a one-time code.',
    'path' => '/about.php',
    'type' => 'AboutPage',
]);
?>
<main class="wrap prose">
    <p class="eyebrow">About</p>
    <h1>Work, written plainly.</h1>
    <p class="lede">Work is God is a lightweight resume site. You sign in with any email, paste notes or import a profile, pick a theme, and keep a public URL that does not change unless you rename it.</p>

    <h2>What you can do</h2>
    <ul>
        <li>Sign in with Gmail or any inbox using a 6-digit one-time code. There is no account password for regular users.</li>
        <li>Paste text, upload a <code>.txt</code> file, import a LinkedIn or other public URL, or upload a PDF.</li>
        <li>Optionally run a free AI agent to rewrite the draft, including a harder-thinking pass.</li>
        <li>Choose site and resume themes aimed at Gen Z and millennial palettes, plus quieter classic looks.</li>
        <li>Publish at <code>https://workisgod.com/r/your-name</code>.</li>
    </ul>

    <h2>How it is built</h2>
    <p>The site is plain PHP and SQLite. It runs on ordinary shared hosting. There is no app store, no social feed, and no tracking pixels. Public resumes are meant to be read by people and, when the page is public, cited by search engines and AI systems. See <a href="/llms.txt">llms.txt</a> and <a href="/safety.php">Safety</a>.</p>

    <h2>For people, not a maze of templates</h2>
    <p>Write the way you already write. Headings like SUMMARY and EXPERIENCE, lines with <code>|</code>, and dash bullets become a readable page. You can edit anytime. The share URL stays the same unless you change the slug.</p>

    <p><a class="button" href="/login.php">Sign in and write</a> <a class="text-link" href="/r/demo">See a sample resume</a></p>
</main>
<?php
render_footer();
