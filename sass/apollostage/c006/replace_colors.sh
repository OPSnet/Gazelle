#!/bin/bash

# Define the input file (change this to your actual file)
input_file="./_variables.scss"

# Perform the replacements using sed
sed -i -e 's/$soil-100/$back-100/g' -e 's/$soil-200/$back-200/g' -e 's/$soil-300/$back-300/g' -e 's/$soil-400/$back-400/g' -e 's/$soil-500/$back-500/g' -e 's/$soil-600/$back-600/g' -e 's/$grass-200/$mid-200/g' -e 's/$grass-300/$mid-300/g' -e 's/$grass-400/$mid-400/g' -e 's/$grass-500/$mid-500/g' -e 's/$grass-600/$mid-600/g' -e 's/$grass-700/$mid-700/g' -e 's/$grass-800/$mid-800/g' -e 's/$grass-900/$mid-900/g' -e 's/$grass-1000/$mid-1000/g' -e 's/$grass-2200/$fore-600/g' -e 's/$grass-2300/$fore-700/g' -e 's/$grass-2400/$fore-800/g' -e 's/$grass-2500/$fore-900/g' ""

echo "Replacements complete."
