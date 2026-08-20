<?php

declare(strict_types=1);

require __DIR__ . '/includes/init.php';

$user = require_login();
$resume = user_resume((int) $user['id']);
$error = '';
$alerts = [];
$useAi = false;

$slug = $resume['slug'] ?? slugify(explode('@', $user['email'])[0]);
$theme = $resume['theme'] ?? 'classic';
$text = $resume['raw_text'] ?? sample_resume_text();
$sourceText = $resume['source_text'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $theme = (string) ($_POST['theme'] ?? 'classic');
    $text = (string) ($_POST['raw_text'] ?? '');
    $useAi = isset($_POST['use_ai']);
    $intent = (string) ($_POST['intent'] ?? 'save');
    $notes = $text;

    if (isset($_FILES['resume_file']) && is_uploaded_file($_FILES['resume_file']['tmp_name'])) {
        $name = (string) ($_FILES['resume_file']['name'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'md', 'text', ''], true)) {
            $error = 'Upload a .txt or .md file.';
        } elseif (($_FILES['resume_file']['size'] ?? 0) > RESUME_MAX_CHARS) {
            $error = 'That file is too large.';
        } else {
            $uploaded = file_get_contents($_FILES['resume_file']['tmp_name']);
            if ($uploaded === false) {
                $error = 'Could not read the uploaded file.';
            } else {
                $text = $uploaded;
                $notes = $uploaded;
            }
        }
    }

    if ($error === '' && $intent === 'restore') {
        $text = (string) ($resume['source_text'] ?? $text);
        $notes = $text;
        $useAi = false;
    }

    if ($error === '' && $useAi && $intent !== 'restore') {
        try {
            $text = ai_improve_resume($notes);
        } catch (Throwable $e) {
            $error = $e->getMessage();
            $text = $notes;
        }
    }

    if ($error === '' && $intent !== 'restore') {
        $mod = moderate_profile_text($text);
        $alerts = $mod['alerts'];
        if (!$mod['allow']) {
            $error = moderation_message($mod);
        }
    }

    if ($error === '') {
        try {
            if ($slug !== '' && !is_valid_slug($slug)) {
                throw new InvalidArgumentException('URL slug can use lowercase letters, numbers, and hyphens only.');
            }
            $resume = save_user_resume(
                (int) $user['id'],
                $slug !== '' ? $slug : explode('@', $user['email'])[0],
                $theme,
                $text,
                $notes,
                $useAi
            );
            $slug = $resume['slug'];
            $theme = $resume['theme'];
            $text = $resume['raw_text'];
            $sourceText = $resume['source_text'] ?? $notes;
            flash_set('ok', $useAi
                ? 'The draft has been dressed. Your public page is live.'
                : 'Saved. Your public page is ready for guests.');
            redirect('/editor.php?saved=1');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$share = $resume ? public_resume_url($resume['slug']) : '';
$canRestore = $resume && trim((string) ($resume['source_text'] ?? '')) !== ''
    && trim((string) $resume['source_text']) !== trim((string) $resume['raw_text']);
$useAiChecked = $useAi || (!empty($resume['ai_used']) && $_SERVER['REQUEST_METHOD'] !== 'POST');
render_header('My resume', ['body' => 'page-editor']);
?>
<main id="main" class="wrap editor-wrap">
    <header class="editor-head">
        <div>
            <p class="eyebrow">Your rooms</p>
            <h1>Write in peace. Publish when ready.</h1>
            <p class="fine">Signed in as <?= h($user['email']) ?> · <a href="/account.php">Delete resume or account</a></p>
        </div>
        <?php if ($share): ?>
            <div class="share-box">
                <label for="share-url">Public URL</label>
                <div class="share-row">
                    <input id="share-url" type="text" readonly value="<?= h($share) ?>">
                    <button type="button" class="secondary" data-copy="#share-url">Copy</button>
                    <a class="button" href="<?= h($share) ?>" target="_blank" rel="noopener">View</a>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($error): ?>
        <p class="form-error" role="alert"><?= h($error) ?></p>
    <?php endif; ?>
    <?php if ($alerts): ?>
        <div class="alert-box" role="alert">
            <p><strong>Please revise before publishing.</strong></p>
            <ul>
                <?php foreach ($alerts as $alert): ?>
                    <li><?= h($alert) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="editor-grid" id="resume-form">
        <?= csrf_field() ?>
        <input type="hidden" name="intent" id="intent" value="save">
        <section class="editor-controls">
            <div class="field">
                <label for="slug">URL slug</label>
                <div class="slug-row">
                    <span><?= h(rtrim(SITE_URL, '/') . '/resumes/') ?></span>
                    <input id="slug" name="slug" required value="<?= h($slug) ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="<?= SLUG_MAX ?>">
                </div>
            </div>
            <div class="field">
                <label for="theme">Resume theme</label>
                <select id="theme" name="theme">
                    <?php foreach (resume_themes() as $key => $label): ?>
                        <option value="<?= h($key) ?>" <?= $theme === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="resume_file">Upload .txt</label>
                <input id="resume_file" name="resume_file" type="file" accept=".txt,.md,.text,text/plain">
            </div>
            <div class="import-box">
                <p class="import-label">Build from a profile</p>
                <div class="field">
                    <label for="profile_url">LinkedIn or any public URL</label>
                    <input id="profile_url" name="profile_url" type="text" placeholder="https://www.linkedin.com/in/you" autocomplete="off">
                </div>
                <div class="field">
                    <label for="profile_pdf">Or upload a PDF</label>
                    <input id="profile_pdf" name="profile_pdf" type="file" accept="application/pdf,.pdf">
                </div>
                <button type="button" class="secondary" id="profile-btn">Generate resume from profile</button>
                <p class="hint">If LinkedIn blocks the page, export the profile as PDF and upload it.</p>
            </div>
            <label class="check">
                <input type="checkbox" name="use_ai" id="use_ai" value="1" <?= $useAiChecked ? 'checked' : '' ?>>
                <span>
                    Also improve with AI when I save
                    <small>Optional. The buttons below let you keep rewriting first, then save when it looks right.</small>
                </span>
            </label>
            <div class="ai-actions">
                <button type="button" class="secondary" id="ai-improve-btn">Improve with AI</button>
                <button type="button" class="secondary" id="ai-harder-btn">Retry with harder thinking</button>
                <p id="ai-status" class="hint" role="status"></p>
            </div>
            <button type="submit" id="save-btn">Save resume</button>
            <?php if ($canRestore): ?>
                <button type="submit" class="secondary" id="restore-btn">Restore original notes</button>
            <?php endif; ?>
            <p class="hint">Use ALL CAPS or <code>## Heading</code> for sections. Lines with <code>|</code> become a role row. Dashes become bullets.</p>
        </section>

        <section class="editor-text">
            <label for="raw_text"><?= !empty($resume['ai_used']) ? 'Resume text (AI draft)' : 'Resume text' ?></label>
            <textarea id="raw_text" name="raw_text" required maxlength="<?= RESUME_MAX_CHARS ?>"><?= h($text) ?></textarea>
        </section>

        <section class="editor-preview">
            <div class="preview-label">Live preview</div>
            <div id="preview-mount" class="preview-mount">
                <?= render_resume_html(parse_resume($text), $theme) ?>
            </div>
        </section>
    </form>
</main>
<?php
render_footer();
