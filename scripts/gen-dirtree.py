#!/usr/bin/env python3
"""
MkDocs gen-files script for directory tree.
"""
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).parent))
from gen_dirtree import main

if __name__ == "__main__":
    main()