#!/opt/alt/python312/bin/python3
import os
import sys
import traceback

HERE = os.path.dirname(os.path.abspath(__file__))
os.chdir(HERE)
sys.path.insert(0, HERE)
os.environ.setdefault("PYTHONUNBUFFERED", "1")

try:
    from wsgiref.handlers import CGIHandler
    from wsgiapp import application

    CGIHandler().run(application)
except Exception:
    sys.stdout.write("Status: 500 Internal Server Error\r\n")
    sys.stdout.write("Content-Type: text/html; charset=utf-8\r\n\r\n")
    sys.stdout.write("<!DOCTYPE html><html><head><meta charset='utf-8'><title>Python edition</title></head><body>")
    sys.stdout.write("<h1>Python edition error</h1><pre>")
    sys.stdout.write(traceback.format_exc().replace("&", "&amp;").replace("<", "&lt;"))
    sys.stdout.write("</pre></body></html>")
    sys.stderr.write(traceback.format_exc())
