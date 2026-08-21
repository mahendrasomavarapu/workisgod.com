# Work is God

A small PHP site for [workisgod.com](https://workisgod.com):

1. Sign in with Gmail or any email using a one-time code
2. Paste text, import a LinkedIn/public URL, or upload a PDF
3. Optionally improve with a free AI agent (including a harder-thinking pass)
4. Pick a Gen Z or millennial theme
5. Share a stable URL: `/resumes/your-name`
6. Use twenty browser-only ops tools at `/tools.php` (curl, jq, openssl stand-ins)
7. Read technical news at `/news/telecom` and `/news/banking`
8. Watch embed-only open-web video at `/videos` (max 1,000 links, infinite slide)
9. Python edition of the same rooms at [`/pythonversion`](https://workisgod.com/pythonversion/)

No Node, no Composer, no database server. SQLite lives under `data/` (not in git). The Python edition is stdlib WSGI and shares that SQLite.

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
