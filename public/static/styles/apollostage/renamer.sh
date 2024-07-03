#!/bin/bash

# Directory where your font files are stored
FONT_DIR="."

# Function to rename fonts based on pattern matching
rename_font() {
    local old_name=$1
    local family=$2
    local weight=$3
    local style=$4
    local new_name="${family}-${weight}-${style}.ttf"

    cp "$FONT_DIR/$old_name" "$FONT_DIR/$new_name"
}

# JetBrains Mono fonts
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO-Lf1OQ.ttf" "JetBrains_Mono" "100" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO8LflOQ.ttf" "JetBrains_Mono" "200" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO_VflOQ.ttf" "JetBrains_Mono" "300" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO-LflOQ.ttf" "JetBrains_Mono" "400" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO-5flOQ.ttf" "JetBrains_Mono" "500" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO9VeVOQ.ttf" "JetBrains_Mono" "600" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO9seVOQ.ttf" "JetBrains_Mono" "700" "Italic"
rename_font "tDba2o-flEEny0FZhsfKu5WU4xD-IQ-PuZJJXxfpAO8LeVOQ.ttf" "JetBrains_Mono" "800" "Italic"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yK1jPQ.ttf" "JetBrains_Mono" "100" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8SKxjPQ.ttf" "JetBrains_Mono" "200" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8lqxjPQ.ttf" "JetBrains_Mono" "300" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8yKxjPQ.ttf" "JetBrains_Mono" "400" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8-qxjPQ.ttf" "JetBrains_Mono" "500" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8FqtjPQ.ttf" "JetBrains_Mono" "600" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8L6tjPQ.ttf" "JetBrains_Mono" "700" "Normal"
rename_font "tDbY2o-flEEny0FZhsfKu5WU4zr3E_BX0PnT8RD8SKtjPQ.ttf" "JetBrains_Mono" "800" "Normal"

# Open Sans fonts
rename_font "memQYaGs126MiZpBA-UFUIcVXSCEkx2cmqvXlWq8tWZ0Pw86hd0Rk5hkaVc.ttf" "Open_Sans" "300" "Italic"
rename_font "memQYaGs126MiZpBA-UFUIcVXSCEkx2cmqvXlWq8tWZ0Pw86hd0Rk8ZkaVc.ttf" "Open_Sans" "400" "Italic"
rename_font "memQYaGs126MiZpBA-UFUIcVXSCEkx2cmqvXlWq8tWZ0Pw86hd0Rk_RkaVc.ttf" "Open_Sans" "500" "Italic"
rename_font "memQYaGs126MiZpBA-UFUIcVXSCEkx2cmqvXlWq8tWZ0Pw86hd0RkxhjaVc.ttf" "Open_Sans" "600" "Italic"
rename_font "memQYaGs126MiZpBA-UFUIcVXSCEkx2cmqvXlWq8tWZ0Pw86hd0RkyFjaVc.ttf" "Open_Sans" "700" "Italic"
rename_font "memQYaGs126MiZpBA-UFUIcVXSCEkx2cmqvXlWq8tWZ0Pw86hd0Rk0ZjaVc.ttf" "Open_Sans" "800" "Italic"
rename_font "memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsiH0C4n.ttf" "Open_Sans" "300" "Normal"
rename_font "memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0C4n.ttf" "Open_Sans" "400" "Normal"
rename_font "memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjr0C4n.ttf" "Open_Sans" "500" "Normal"
rename_font "memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsgH1y4n.ttf" "Open_Sans" "600" "Normal"
rename_font "memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsg-1y4n.ttf" "Open_Sans" "700" "Normal"
rename_font "memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgshZ1y4n.ttf" "Open_Sans" "800" "Normal"

echo "Font files renamed successfully."
