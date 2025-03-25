#!/bin/bash
# To run this file, add this line to your crontab:
# * * * * * /path/to/scheduler.sh > /dev/null 2>&1

i=0

while [ $i -lt 119 ]; do # 119 one-second intervals in 1 minute
  # Get the directory of the current script
  SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

  # Use the script directory to find scheduler.php
#   php "$SCRIPT_DIR/scheduler.php" > /dev/null 2>&1
  php "${SCRIPT_DIR}/scheduler.php"
  sleep 0.5
  i=$(( i + 1 ))
done
