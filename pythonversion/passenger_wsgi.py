import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from wsgiapp import application  # noqa: E402,F401
