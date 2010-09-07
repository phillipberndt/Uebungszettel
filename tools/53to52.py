#!/usr/bin/python
#
# REALLY quick&dirty 5.3 > 5.2 converter
#
import sys
import re
import glob

def dorepl(fil):
	data = open(fil).read()
	def repl(match):
		rest = ""
		n_open = 1
		done = False
		for i in match.group(4):
			if i == "{": n_open += 1
			if i in ("'", "\\") and not done: rest += "\\"
			if i == "}":
				n_open -= 1
				if not done and n_open == 0:
					rest += "')"
					done = True
					continue
			rest += i
		return match.group(1) + "create_function('" + match.group(2) + "', 'global " + match.group(3) + ";" + rest
	pattern = re.compile("^(.*\S.*)function\s*\(([^)]+)\)\s*(?:use\s*\(([^)]+)\))\s*{([^\xff]+)", re.M | re.I)
	data = re.sub(pattern, repl, data, 9999)
	open(fil, "w").write(data)
for fil in glob.glob("*.php"): dorepl(fil)
