# Work is God

A small PHP site for [workisgod.com](https://workisgod.com):

1. Sign in with Gmail or any email using a one-time code
2. Paste text, import a LinkedIn/public URL, or upload a PDF
3. Optionally improve with a free AI agent (including a harder-thinking pass)
4. Pick a Gen Z or millennial theme
5. Share a stable URL: `/resumes/your-name`

No Node, no Composer, no database server. SQLite lives under `data/` (not in git).

## Docs

- [About](https://workisgod.com/about.php)
- [Safety](https://workisgod.com/safety.php)
- [llms.txt](https://workisgod.com/llms.txt) for AI systems that cite the site

## Local preview

```bash
php -S localhost:8080
```

Open http://localhost:8080

## Production

See [INSTALL-NAMECHEAP.md](INSTALL-NAMECHEAP.md).
